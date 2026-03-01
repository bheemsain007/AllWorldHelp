<?php
// models/Review.php
// Task T20 — Review model: create, update, find, list, average rating, count

class Review {

    private const TABLE = 'ft_reviews';

    // ── Create ────────────────────────────────────────────────────────────────
    /**
     * Insert a new review. Returns true on success.
     * $data: reviewer_id, reviewee_id, rating (1-5), review_text
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_reviews (reviewer_id, reviewee_id, rating, review_text, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['reviewer_id'],
                (int) $data['reviewee_id'],
                (int) $data['rating'],
                $data['review_text'],
            ]);
        } catch (PDOException $e) {
            error_log("Review::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Update ────────────────────────────────────────────────────────────────
    /**
     * Update rating + text of an existing review row.
     */
    public static function update(int $reviewId, array $data): bool {
        global $pdo;

        $sql = "UPDATE ft_reviews
                SET rating = ?, review_text = ?, updated_at = NOW()
                WHERE review_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['rating'],
                $data['review_text'],
                $reviewId,
            ]);
        } catch (PDOException $e) {
            error_log("Review::update error: " . $e->getMessage());
            return false;
        }
    }

    // ── Find by Users ─────────────────────────────────────────────────────────
    /**
     * Return the review written by $reviewerId for $revieweeId, or null.
     */
    public static function findByUsers(int $reviewerId, int $revieweeId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_reviews
                WHERE reviewer_id = ? AND reviewee_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reviewerId, $revieweeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Get All Reviews For a User ────────────────────────────────────────────
    /**
     * Return all reviews received by $userId, newest first, with reviewer info.
     */
    public static function getReviewsForUser(int $userId, int $limit = 50): array {
        global $pdo;

        $sql = "SELECT r.review_id, r.rating, r.review_text, r.created_at,
                       u.user_id AS reviewer_id,
                       u.name    AS reviewer_name,
                       u.username AS reviewer_username,
                       u.profile_photo
                FROM ft_reviews r
                JOIN ft_users u ON r.reviewer_id = u.user_id
                WHERE r.reviewee_id = :uid
                ORDER BY r.created_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Review::getReviewsForUser error: " . $e->getMessage());
            return [];
        }
    }

    // ── Average Rating ────────────────────────────────────────────────────────
    /**
     * Return the average rating for $userId rounded to 1 decimal, or 0 if none.
     */
    public static function getAverageRating(int $userId): float {
        global $pdo;

        $sql = "SELECT AVG(rating) FROM ft_reviews WHERE reviewee_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $avg = $stmt->fetchColumn();
            return $avg ? (float) number_format((float) $avg, 1, '.', '') : 0.0;
        } catch (PDOException $e) {
            return 0.0;
        }
    }

    // ── Review Count ──────────────────────────────────────────────────────────
    /**
     * Return the total number of reviews received by $userId.
     */
    public static function getReviewCount(int $userId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_reviews WHERE reviewee_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
?>
