<?php
// connections/reject.php
// Task T17 — Reject (receiver) or cancel (sender) a pending connection request (POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

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

// ── Permission: Receiver can reject; Sender can cancel ───────────────────────
$isReceiver = (int) $connection['friend_id'] === $userId;
$isSender   = (int) $connection['user_id']   === $userId;

if (!$isReceiver && !$isSender) {
    redirect("requests.php?error=permission");
}

// ── Reject / Cancel ───────────────────────────────────────────────────────────
$success = Connection::reject($connectionId);

if ($success) {
    $msg = $isSender ? 'cancelled' : 'rejected';
    redirect("requests.php?success={$msg}");
} else {
    redirect("requests.php?error=failed");
}
?>
