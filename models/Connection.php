<?php
// models/Connection.php
// Task T17 — Connection model: send/accept/reject requests, check status, fetch lists

class Connection {

    private const TABLE = 'ft_connections';

    // ── Send Request ─────────────────────────────────────────────────────────
    /**
     * Insert a new pending connection request from $senderId to $receiverId.
     * Returns true on success, false on failure (e.g. duplicate key).
     */
    public static function sendRequest(int $senderId, int $receiverId): bool {
        global $pdo;

        $sql = "INSERT INTO ft_connections (user_id, friend_id, status, created_at)
                VALUES (?, ?, 'pending', NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$senderId, $receiverId]);
        } catch (PDOException $e) {
            error_log("Connection::sendRequest error: " . $e->getMessage());
            return false;
        }
    }

    // ── Accept Request ────────────────────────────────────────────────────────
    /**
     * Set status = 'accepted' for the given connection row.
     */
    public static function accept(int $connectionId): bool {
        global $pdo;

        $sql = "UPDATE ft_connections
                SET status = 'accepted', updated_at = NOW()
                WHERE connection_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$connectionId]);
        } catch (PDOException $e) {
            error_log("Connection::accept error: " . $e->getMessage());
            return false;
        }
    }

    // ── Reject / Cancel Request ───────────────────────────────────────────────
    /**
     * Delete the connection row (covers both reject by receiver and cancel by sender).
     */
    public static function reject(int $connectionId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_connections WHERE connection_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$connectionId]);
        } catch (PDOException $e) {
            error_log("Connection::reject error: " . $e->getMessage());
            return false;
        }
    }

    // ── Find by ID ─────────────────────────────────────────────────────────────
    /**
     * Fetch a single connection row by its primary key.
     */
    public static function findById(int $connectionId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_connections WHERE connection_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$connectionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Are Connected? ─────────────────────────────────────────────────────────
    /**
     * Return true if user1 and user2 have an accepted connection (in either direction).
     */
    public static function areConnected(int $user1, int $user2): bool {
        global $pdo;

        $sql = "SELECT 1 FROM ft_connections
                WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
                  AND status = 'accepted'
                LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user1, $user2, $user2, $user1]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Request Exists? ────────────────────────────────────────────────────────
    /**
     * Return true if a pending request from $senderId to $receiverId already exists.
     */
    public static function requestExists(int $senderId, int $receiverId): bool {
        global $pdo;

        $sql = "SELECT 1 FROM ft_connections
                WHERE user_id = ? AND friend_id = ? AND status = 'pending'
                LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$senderId, $receiverId]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Pending Requests TO User ───────────────────────────────────────────────
    /**
     * Return all pending requests received by $userId (with sender info).
     */
    public static function getPendingRequests(int $userId): array {
        global $pdo;

        $sql = "SELECT c.connection_id, c.user_id, c.created_at,
                       u.name, u.username, u.profile_photo
                FROM ft_connections c
                JOIN ft_users u ON c.user_id = u.user_id
                WHERE c.friend_id = ? AND c.status = 'pending'
                ORDER BY c.created_at DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Sent Requests FROM User ────────────────────────────────────────────────
    /**
     * Return all pending requests sent by $userId (with receiver info).
     */
    public static function getSentRequests(int $userId): array {
        global $pdo;

        $sql = "SELECT c.connection_id, c.friend_id AS user_id, c.created_at,
                       u.name, u.username, u.profile_photo
                FROM ft_connections c
                JOIN ft_users u ON c.friend_id = u.user_id
                WHERE c.user_id = ? AND c.status = 'pending'
                ORDER BY c.created_at DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Get All Accepted Connections ───────────────────────────────────────────
    /**
     * Return all accepted connections for $userId with the other user's info.
     */
    public static function getConnections(int $userId, int $limit = 100): array {
        global $pdo;

        $sql = "SELECT
                    CASE
                        WHEN c.user_id = :uid1 THEN c.friend_id
                        ELSE c.user_id
                    END AS user_id,
                    u.name, u.username, u.profile_photo,
                    c.updated_at
                FROM ft_connections c
                JOIN ft_users u ON (
                    (c.user_id   = :uid2 AND u.user_id = c.friend_id) OR
                    (c.friend_id = :uid3 AND u.user_id = c.user_id)
                )
                WHERE (c.user_id = :uid4 OR c.friend_id = :uid5)
                  AND c.status = 'accepted'
                ORDER BY c.updated_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':uid1', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid4', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':uid5', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Connection::getConnections error: " . $e->getMessage());
            return [];
        }
    }

    // ── Count Pending Received ─────────────────────────────────────────────────
    /**
     * Return number of pending requests received by $userId (for notification badge).
     */
    public static function countPending(int $userId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_connections
                WHERE friend_id = ? AND status = 'pending'";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Remove Connection (Unfriend) ───────────────────────────────────────────
    /**
     * Delete an accepted connection row in either direction between user1 and user2.
     * Returns true if a row was deleted.
     */
    public static function remove(int $user1, int $user2): bool {
        global $pdo;

        $sql = "DELETE FROM ft_connections
                WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
                  AND status = 'accepted'";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user1, $user2, $user2, $user1]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Connection::remove error: " . $e->getMessage());
            return false;
        }
    }

    // ── Mutual Connections ────────────────────────────────────────────────────
    /**
     * Return users who have accepted connections with BOTH user1 AND user2.
     */
    public static function getMutualConnections(int $user1, int $user2): array {
        global $pdo;

        $sql = "SELECT DISTINCT u.user_id, u.name, u.username, u.profile_photo
                FROM ft_users u
                WHERE u.user_id != :u1a AND u.user_id != :u2a
                  AND EXISTS (
                      SELECT 1 FROM ft_connections
                      WHERE ((user_id = u.user_id AND friend_id = :u1b)
                          OR (user_id = :u1c AND friend_id = u.user_id))
                        AND status = 'accepted'
                  )
                  AND EXISTS (
                      SELECT 1 FROM ft_connections
                      WHERE ((user_id = u.user_id AND friend_id = :u2b)
                          OR (user_id = :u2c AND friend_id = u.user_id))
                        AND status = 'accepted'
                  )
                ORDER BY u.name ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':u1a', $user1, PDO::PARAM_INT);
            $stmt->bindValue(':u2a', $user2, PDO::PARAM_INT);
            $stmt->bindValue(':u1b', $user1, PDO::PARAM_INT);
            $stmt->bindValue(':u1c', $user1, PDO::PARAM_INT);
            $stmt->bindValue(':u2b', $user2, PDO::PARAM_INT);
            $stmt->bindValue(':u2c', $user2, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Connection::getMutualConnections error: " . $e->getMessage());
            return [];
        }
    }
}
?>
