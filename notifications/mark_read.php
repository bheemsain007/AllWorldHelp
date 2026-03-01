<?php
// notifications/mark_read.php
// Task T26 — Mark a notification as read and redirect to its link

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Notification.php";
require_once "../includes/helpers.php";

$userId         = (int) SessionManager::get('user_id');
$notificationId = (int) ($_GET['id'] ?? 0);
$redirectUrl    = $_GET['redirect'] ?? '';

if ($notificationId <= 0) {
    redirect("notifications.php");
}

// ── Verify ownership before marking read ──────────────────────────────────────
$notification = Notification::findById($notificationId);

if ($notification && (int) $notification['user_id'] === $userId) {
    Notification::markAsRead($notificationId);
    // Use the notification's stored link as the redirect target
    $dest = $redirectUrl ?: $notification['link'];
} else {
    $dest = 'notifications.php';
}

// Ensure redirect is a relative path (prevent open redirect)
if (filter_var($dest, FILTER_VALIDATE_URL)) {
    $dest = 'notifications.php';
}

redirect($dest);
?>
