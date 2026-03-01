<?php
// messages/send_message.php
// Task T19 — Handle POST: validate message, permission check, insert, redirect back to chat

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Message.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("inbox.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("inbox.php?error=csrf");
}

$senderId   = (int) SessionManager::get('user_id');
$receiverId = (int) ($_POST['receiver_id'] ?? 0);

if ($receiverId <= 0 || $receiverId === $senderId) {
    redirect("inbox.php");
}

// ── Sanitize & Validate Message ───────────────────────────────────────────────
$message = InputValidator::sanitizeString($_POST['message'] ?? '');

if ($message === '' || mb_strlen($message) > 1000) {
    redirect("chat.php?user_id={$receiverId}&error=invalid_message");
}

// ── Permission: Only connected users can message ──────────────────────────────
if (!Connection::areConnected($senderId, $receiverId)) {
    redirect("inbox.php?error=not_connected");
}

// ── Send ──────────────────────────────────────────────────────────────────────
$success = Message::send([
    'sender_id'   => $senderId,
    'receiver_id' => $receiverId,
    'message'     => $message,
]);

if ($success) {
    createNotification(
        $receiverId,
        'new_message',
        'New Message',
        'You have a new message',
        "../messages/chat.php?user_id={$senderId}"
    );
    redirect("chat.php?user_id={$receiverId}");
} else {
    redirect("chat.php?user_id={$receiverId}&error=failed");
}
?>
