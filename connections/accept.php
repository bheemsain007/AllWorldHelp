<?php
// connections/accept.php
// Task T17 — Accept a pending connection request (POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("requests.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("requests.php?error=csrf");
}

$userId       = (int) SessionManager::get('user_id');
$connectionId = (int) ($_POST['connection_id'] ?? 0);

if ($connectionId <= 0) {
    redirect("requests.php?error=invalid");
}

// ── Fetch Connection ──────────────────────────────────────────────────────────
$connection = Connection::findById($connectionId);

if (!$connection) {
    redirect("requests.php?error=notfound");
}

// ── Permission: Only the receiver (friend_id) can accept ─────────────────────
if ((int) $connection['friend_id'] !== $userId) {
    redirect("requests.php?error=permission");
}

// ── Guard: Must still be pending ─────────────────────────────────────────────
if ($connection['status'] !== 'pending') {
    redirect("requests.php?error=already_processed");
}

// ── Accept ────────────────────────────────────────────────────────────────────
$success = Connection::accept($connectionId);

if ($success) {
    createNotification(
        (int) $connection['user_id'],
        'connection_accepted',
        'Connection Accepted',
        'Your connection request was accepted',
        "../connections/connections.php"
    );
    // Future T34-T38: CoinManager::award($userId, 'connection_made', 10);
    redirect("requests.php?success=accepted");
} else {
    redirect("requests.php?error=failed");
}
?>
