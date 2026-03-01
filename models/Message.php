<?php
// models/Message.php
// Task T19 — Message model: send, fetch conversation, inbox, unread count, mark-read

class Message {

    private const TABLE = 'ft_messages';

    // ── Send Message ──────────────────────────────────────────────────────────
    /**
     * Insert a new message row.
     * $data must contain: sender_id, receiver_id, message
     * Returns true on success.
     */
    public static function send(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_messages (sender_id, receiver_id, message, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['sender_id'],
                (int) $data['receiver_id'],
                $data['message'],
            ]);
        } catch (PDOException $e) {
            error_log("Message::send error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Conversation ──────────────────────────────────────────────────────
    /**
     * Return all messages between user1 and user2 in chronological order.
     */
    public static function getConversation(int $user1, int $user2, int $limit = 200): array {
        global $pdo;

        $sql = "SELECT message_id, sender_id, receiver_id, message, is_read, created_at
                FROM ft_messages
                WHERE (sender_id = :s1 AND receiver_id = :r1)
                   OR (sender_id = :s2 AND receiver_id = :r2)
                ORDER BY created_at ASC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':s1',  $user1, PDO::PARAM_INT);
            $stmt->bindValue(':r1',  $user2, PDO::PARAM_INT);
            $stmt->bindValue(':s2',  $user2, PDO::PARAM_INT);
            $stmt->bindValue(':r2',  $user1, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Message::getConversation error: " . $e->getMessage());
            return [];
        }
    }

    // ── Get Inbox / Conversations List ────────────────────────────────────────
    /**
     * Return one row per conversation partner showing the last message,
     * its timestamp, and unread count for $userId.
     *
     * Strategy:
     *  1. Derived table `lm` picks the MAX(message_id) per conversation pair
     *     (normalised so the lower user_id is always "first" for grouping).
     *  2. Join back to ft_messages for the actual last message text/time.
     *  3. Correlated subquery counts unread messages FROM that partner TO $userId.
     */
    public static function getConversations(int $userId): array {
        global $pdo;

        $sql = "SELECT
                    lm.other_user_id,
                    u.name,
                    u.username,
                    u.profile_photo,
                    m.message        AS last_message,
                    m.created_at     AS last_message_time,
                    (SELECT COUNT(*)
                     FROM ft_messages m2
                     WHERE m2.receiver_id = :uid_unread
                       AND m2.sender_id   = lm.other_user_id
                       AND m2.is_read     = 0
                    ) AS unread_count
                FROM (
                    SELECT
                        CASE WHEN sender_id = :uid_case THEN receiver_id
                             ELSE sender_id
                        END  AS other_user_id,
                        MAX(message_id) AS last_msg_id
                    FROM ft_messages
                    WHERE sender_id = :uid_w1 OR receiver_id = :uid_w2
                    GROUP BY other_user_id
                ) lm
                JOIN ft_messages m ON m.message_id = lm.last_msg_id
                JOIN ft_users    u ON u.user_id    = lm.other_user_id
                ORDER BY m.created_at DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':uid_unread', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid_case',   $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid_w1',     $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid_w2',     $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Message::getConversations error: " . $e->getMessage());
            return [];
        }
    }

    // ── Total Unread Count ────────────────────────────────────────────────────
    /**
     * Count all unread messages received by $userId (for nav badge).
     */
    public static function getUnreadCount(int $userId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_messages
                WHERE receiver_id = ? AND is_read = 0";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Mark Messages As Read ─────────────────────────────────────────────────
    /**
     * Mark all messages from $senderId to $receiverId as read.
     */
    public static function markAsRead(int $receiverId, int $senderId): bool {
        global $pdo;

        $sql = "UPDATE ft_messages
                SET is_read = 1
                WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$receiverId, $senderId]);
        } catch (PDOException $e) {
            error_log("Message::markAsRead error: " . $e->getMessage());
            return false;
        }
    }
}
?>
