<?php
// includes/create_notification.php
// Task T26 — Convenience wrapper to create a notification row

require_once __DIR__ . '/../models/Notification.php';

/**
 * Create a notification for $userId.
 *
 * @param int    $userId   Recipient user ID
 * @param string $type     Notification type (trip_update, new_message, etc.)
 * @param string $title    Short title shown in the notification list
 * @param string $message  Longer description text
 * @param string $link     Relative URL the notification links to
 * @return bool            True on success
 */
function createNotification(int $userId, string $type, string $title, string $message, string $link): bool {
    return Notification::create([
        'user_id' => $userId,
        'type'    => $type,
        'title'   => $title,
        'message' => $message,
        'link'    => $link,
    ]);
}
?>
