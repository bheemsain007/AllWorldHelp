<?php
// auth/auth_check.php
// Updated in T7: uses SessionManager::isActive() + updateActivity()
//
// Include at the TOP of any page that requires login.
// Usage: require_once 'auth/auth_check.php';    (from root)
//        require_once '../auth/auth_check.php';  (from subdirectory)

require_once __DIR__ . '/../includes/SessionManager.php';

SessionManager::start();

// ── Login & Timeout Check ────────────────────────────────────────────────────
if (!SessionManager::isActive()) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header("Location: auth/login.php?error=timeout&redirect=" . $redirect);
    exit;
}

// ── Refresh Activity Timestamp ───────────────────────────────────────────────
SessionManager::updateActivity();

// ── Optional: Fingerprint Validation (high-security pages) ──────────────────
// Uncomment on sensitive pages to detect possible session hijacking:
// if (!SessionManager::validateFingerprint()) {
//     header("Location: auth/login.php?error=security");
//     exit;
// }
?>
