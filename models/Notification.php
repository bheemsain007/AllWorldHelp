<?php
// models/Notification.php
// Task T26 — Notification model: create, fetch, mark as read, delete, unread count

class Notification {

    private const TABLE = 'ft_notifications';

    // Supported notification types
    private const TYPES = [
        'trip_update', 'trip_comment', 'trip_join_request', 'trip_join_approved',
        'new_message', 'expense_added',
        'connection_request', 'connection_accepted',
    ];

    // ── Create ────────────────────────────────────────────────────────────────
    /**
     * Insert a new notification row.
     * $data: user_id, type, title, message, link
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_notifications
                    (user_id, type, title, message, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['user_id'],
                      $data['type'],
                      $data['title'],
                      $data['message'],
                      $data['link'],
            ]);
        } catch (PDOException $e) {
            error_log("Notification::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get All For User ──────────────────────────────────────────────────────
    /**
     * Return all notifications for a user, newest first.
     */
    public static function getNotifications(int $userId, int $limit = 50): array {
        global $pdo;

        $sql = "SELECT notification_id, user_id, type, title, message,
                       link, is_read, created_at
                FROM ft_notifications
                WHERE user_id = :uid
                ORDER BY created_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Notification::getNotifications error: " . $e->getMessage());
            return [];
        }
    }

    // ── Unread Count ──────────────────────────────────────────────────────────
    public static function getUnreadCount(int $userId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_notifications WHERE user_id = ? AND is_read = 0";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Find By ID ────────────────────────────────────────────────────────────
    public static function findById(int $notificationId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_notifications WHERE notification_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$notificationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Mark As Read ──────────────────────────────────────────────────────────
    public static function markAsRead(int $notificationId): bool {
        global $pdo;

        $sql = "UPDATE ft_notifications SET is_read = 1 WHERE notification_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$notificationId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Mark All As Read ──────────────────────────────────────────────────────
    public static function markAllAsRead(int $userId): bool {
        global $pdo;

        $sql = "UPDATE ft_notifications SET is_read = 1 WHERE user_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    public static function delete(int $notificationId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_notifications WHERE notification_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$notificationId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Icon Map ──────────────────────────────────────────────────────────────
    public static function getIcons(): array {
        return [
            'trip_update'        => '📢',
            'trip_comment'       => '💬',
            'trip_join_request'  => '🎫',
            'trip_join_approved' => '✅',
            'new_message'        => '✉️',
            'expense_added'      => '💰',
            'connection_request' => '👥',
            'connection_accepted'=> '🤝',
        ];
    }
}

// ── Time-Ago Helper ───────────────────────────────────────────────────────────
if (!function_exists('timeAgo')) {
    function timeAgo(string $datetime): string {
        $ts   = strtotime($datetime);
        $diff = time() - $ts;

        if ($diff < 60)   return 'Just now';
        if ($diff < 3600) return (int)($diff / 60)   . 'm ago';
        if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
        if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}
?>
