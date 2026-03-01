<?php
// models/TripComment.php
// Task T22 — TripComment model: create, fetch, find, delete, count

class TripComment {

    private const TABLE = 'ft_trip_comments';

    // ── Create Comment ────────────────────────────────────────────────────────
    /**
     * Insert a new comment.
     * $data: trip_id, user_id, comment_text
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_trip_comments (trip_id, user_id, comment_text, created_at)
                VALUES (?, ?, ?, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['trip_id'],
                (int) $data['user_id'],
                $data['comment_text'],
            ]);
        } catch (PDOException $e) {
            error_log("TripComment::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Comments For Trip ─────────────────────────────────────────────────
    /**
     * Return all comments for a trip in chronological order, with commenter info.
     */
    public static function getCommentsForTrip(int $tripId, int $limit = 200): array {
        global $pdo;

        $sql = "SELECT c.comment_id, c.trip_id, c.comment_text, c.created_at,
                       u.user_id, u.name, u.username, u.profile_photo
                FROM ft_trip_comments c
                JOIN ft_users u ON c.user_id = u.user_id
                WHERE c.trip_id = :tid
                ORDER BY c.created_at ASC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':tid', $tripId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripComment::getCommentsForTrip error: " . $e->getMessage());
            return [];
        }
    }

    // ── Find By ID ────────────────────────────────────────────────────────────
    /**
     * Fetch a single comment row by primary key.
     */
    public static function findById(int $commentId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_trip_comments WHERE comment_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$commentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    /**
     * Hard-delete a comment by its ID.
     */
    public static function delete(int $commentId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_trip_comments WHERE comment_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$commentId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("TripComment::delete error: " . $e->getMessage());
            return false;
        }
    }

    // ── Count ─────────────────────────────────────────────────────────────────
    /**
     * Return total number of comments for a trip.
     */
    public static function getCommentCount(int $tripId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_trip_comments WHERE trip_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
?>
