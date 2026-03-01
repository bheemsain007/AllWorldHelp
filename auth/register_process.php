<?php
// auth/register_process.php
// Updated in T8: CSRF validation added
// Updated in T9: InputValidator sanitization
// Updated in T10: RateLimiter registration throttle

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
    redirect("register.php");
}

// ── CSRF Validation (MUST be first check) ────────────────────────────────────
CSRF::validateOrDie($_POST['csrf_token'] ?? '');

// ── Registration Rate Limit (T10): max 3 registrations per IP per day ─────────
$ip              = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$registerRateKey = 'register_' . $ip;

if (RateLimiter::isBlocked($registerRateKey, RateLimiter::LIMIT_REGISTER, RateLimiter::WINDOW_REGISTER)) {
    redirect("register.php?error=ratelimit");
}

// ── Collect & Sanitize (T9: using InputValidator) ────────────────────────────
$email    = InputValidator::sanitizeString($_POST['email']    ?? '');
$username = InputValidator::sanitizeString($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';          // Do NOT sanitize password before hashing
$name     = InputValidator::sanitizeString($_POST['name']     ?? '');
$phone    = !empty($_POST['phone']) ? InputValidator::sanitizeString($_POST['phone']) : null;
$city     = !empty($_POST['city'])  ? InputValidator::sanitizeString($_POST['city'])  : null;

// ── Validation (T9: using InputValidator) ─────────────────────────────────────
if (!InputValidator::validateEmail($email)) {
    redirect("register.php?error=invalid");
}

if (!InputValidator::validateUsername($username)) {
    redirect("register.php?error=invalid");
}

$pwCheck = InputValidator::validatePassword($password, 6);
if (!$pwCheck['valid']) {
    redirect("register.php?error=invalid");
}

if ($phone && !InputValidator::validatePhone($phone)) {
    redirect("register.php?error=invalid");
}

if (!InputValidator::validateLength($name, 2, 50)) {
    redirect("register.php?error=invalid");
}

// ── Duplicate Check ──────────────────────────────────────────────────────────
if (User::exists($email, $username)) {
    redirect("register.php?error=exists");
}

// ── Create User ──────────────────────────────────────────────────────────────
$userId = User::create([
    'email'    => $email,
    'username' => $username,
    'password' => $password,
    'name'     => $name,
    'phone'    => $phone,
    'city'     => $city,
]);

if ($userId) {
    // Record successful registration against IP rate limit
    RateLimiter::increment($registerRateKey);

    // ── Award registration coins (implement in T34-T38) ─────────────────────
    // CoinManager::award($userId, 'profile_complete', 100);

    // ── Welcome email (implement in T40) ────────────────────────────────────
    // EmailService::sendWelcome($email, $name);

    redirect("login.php?registered=1");
} else {
    redirect("register.php?error=failed");
}
?>
