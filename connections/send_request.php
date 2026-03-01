<?php
// connections/send_request.php
// Task T17 — Handle connection request POST: validate, prevent duplicates, insert row

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
    redirect("../trips/search.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("../trips/search.php?error=csrf");
}

$senderId   = (int) SessionManager::get('user_id');
$receiverId = (int) ($_POST['user_id'] ?? 0);

if ($receiverId <= 0) {
    redirect("../trips/search.php?error=invalid");
}

// ── Guard 1: Cannot connect with yourself ────────────────────────────────────
if ($senderId === $receiverId) {
    redirect("../profile/public_profile.php?user_id={$receiverId}&error=self");
}

// ── Guard 2: Already connected ────────────────────────────────────────────────
if (Connection::areConnected($senderId, $receiverId)) {
    redirect("../profile/public_profile.php?user_id={$receiverId}&error=already_connected");
}

// ── Guard 3: Request already sent ────────────────────────────────────────────
if (Connection::requestExists($senderId, $receiverId)) {
    redirect("../profile/public_profile.php?user_id={$receiverId}&error=request_exists");
}

// ── Guard 4: They already sent you a request → prompt to accept instead ───────
if (Connection::requestExists($receiverId, $senderId)) {
    redirect("requests.php?info=they_sent_first");
}

// ── Send Request ──────────────────────────────────────────────────────────────
$success = Connection::sendRequest($senderId, $receiverId);

if ($success) {
    createNotification(
        $receiverId,
        'connection_request',
        'New Connection Request',
        'You have a new connection request',
        "../connections/requests.php"
    );
    redirect("../profile/public_profile.php?user_id={$receiverId}&success=request_sent");
} else {
    redirect("../profile/public_profile.php?user_id={$receiverId}&error=failed");
}
?>
