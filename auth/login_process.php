<?php
// auth/login_process.php
// Updated in T7: uses SessionManager
// Updated in T8: CSRF validation added
// Updated in T9: uses InputValidator for sanitization
// Updated in T10: RateLimiter brute-force protection

require_once "../includes/SessionManager.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/RateLimiter.php";
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/helpers.php";

SessionManager::start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("login.php");
}

// ── CSRF Validation (MUST be first check) ────────────────────────────────────
CSRF::validateOrDie($_POST['csrf_token'] ?? '');

// ── Collect & Sanitize ───────────────────────────────────────────────────────
$email    = InputValidator::sanitizeString($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';           // Do NOT sanitize before bcrypt check

if (empty($email) || empty($password)) {
    redirect("login.php?error=invalid");
}

// ── Rate Limit Check (T10) ────────────────────────────────────────────────────
// Key combines email + IP so neither can be used alone to bypass limit
$ip           = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'login_' . md5($email . $ip);

if (RateLimiter::isBlocked($rateLimitKey, RateLimiter::LIMIT_LOGIN, RateLimiter::WINDOW_LOGIN)) {
    $wait    = RateLimiter::getTimeUntilReset($rateLimitKey, RateLimiter::WINDOW_LOGIN);
    $minutes = max(1, (int)ceil($wait / 60));
    redirect("login.php?error=ratelimit&wait=" . $minutes);
}

// ── Find User ────────────────────────────────────────────────────────────────
$user = User::findByEmail($email);

if (!$user) {
    // Record failed attempt even for unknown email (prevents user enumeration timing)
    RateLimiter::increment($rateLimitKey);
    password_verify($password, '$2y$12$invalidhashtopreventtimingattack..');
    $remaining = RateLimiter::getRemainingAttempts($rateLimitKey, RateLimiter::LIMIT_LOGIN, RateLimiter::WINDOW_LOGIN);
    redirect("login.php?error=invalid&remaining=" . $remaining);
}

// ── Account Status Check ─────────────────────────────────────────────────────
if (in_array($user['status'], ['deleted', 'banned'])) {
    redirect("login.php?error=deleted");
}

// ── Password Verification ────────────────────────────────────────────────────
if (!User::checkPassword($password, $user['password'])) {
    // Record this failed attempt
    RateLimiter::increment($rateLimitKey);
    $remaining = RateLimiter::getRemainingAttempts($rateLimitKey, RateLimiter::LIMIT_LOGIN, RateLimiter::WINDOW_LOGIN);
    redirect("login.php?error=invalid&remaining=" . $remaining);
}

// ── Login Successful ─────────────────────────────────────────────────────────

// Clear rate limit counter on success
RateLimiter::reset($rateLimitKey);

// Store user info via SessionManager
SessionManager::set('user_id',     $user['id']);
SessionManager::set('email',       $user['email']);
SessionManager::set('username',    $user['username']);
SessionManager::set('name',        $user['name']);
SessionManager::set('is_premium',  $user['is_premium']  ?? 0);
SessionManager::set('is_verified', $user['is_verified'] ?? 0);
SessionManager::set('logged_in_at', time());

// Regenerate session ID after privilege change (prevents session fixation)
SessionManager::regenerate();

// Stamp activity
SessionManager::updateActivity();

// ── Update last_login timestamp ──────────────────────────────────────────────
User::update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

// ── Award daily login coins (implement in T34-T38) ───────────────────────────
// CoinManager::award($user['id'], 'daily_login', 10);

// ── Redirect to dashboard ────────────────────────────────────────────────────
redirect("../dashboard.php");
?>
