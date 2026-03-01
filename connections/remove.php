<?php
// connections/remove.php
// Task T18 — Remove (unfriend) an accepted connection (POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("connections.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("connections.php?error=csrf");
}

$userId   = (int) SessionManager::get('user_id');
$friendId = (int) ($_POST['friend_id'] ?? 0);

if ($friendId <= 0 || $friendId === $userId) {
    redirect("connections.php?error=not_connected");
}

// ── Verify They Are Actually Connected ────────────────────────────────────────
if (!Connection::areConnected($userId, $friendId)) {
    redirect("connections.php?error=not_connected");
}

// ── Remove Connection ─────────────────────────────────────────────────────────
$success = Connection::remove($userId, $friendId);

if ($success) {
    redirect("connections.php?success=removed");
} else {
    redirect("connections.php?error=failed");
}
?>
