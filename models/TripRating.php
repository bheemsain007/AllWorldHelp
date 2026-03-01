<?php
/**
 * TripRating Model
 * Fellow Traveler — Task T28
 *
 * Static CRUD class for ft_trip_ratings table.
 *
 * Public methods:
 *   TripRating::create($data)                  → bool
 *   TripRating::update($ratingId, $data)       → bool
 *   TripRating::findById($ratingId)            → array|null
 *   TripRating::findByUserAndTrip($userId, $tripId) → array|null
 *   TripRating::getRatingsForTrip($tripId)     → array
 *   TripRating::getAverageRatings($tripId)     → array|null
 *   TripRating::getRatingCount($tripId)        → int
 *   TripRating::delete($ratingId)              → bool
 */
class TripRating
{
    private const TABLE = 'ft_trip_ratings';

    // ── 1. create() ───────────────────────────────────────────────────────────
    public static function create(array $data): bool
    {
        global $pdo;

        $sql = "
            INSERT INTO ft_trip_ratings
                (trip_id, user_id,
                 organization_rating, communication_rating,
                 value_rating, experience_rating, recommend_rating,
                 overall_rating, review_text)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['trip_id'],
                (int) $data['user_id'],
                (int) $data['organization_rating'],
                (int) $data['communication_rating'],
                (int) $data['value_rating'],
                (int) $data['experience_rating'],
                (int) $data['recommend_rating'],
                round((float) $data['overall_rating'], 2),
                trim($data['review_text'] ?? ''),
            ]);
        } catch (PDOException $e) {
            error_log("TripRating::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── 2. update() ───────────────────────────────────────────────────────────
    public static function update(int $ratingId, array $data): bool
    {
        global $pdo;

        $sql = "
            UPDATE ft_trip_ratings
            SET organization_rating  = ?,
                communication_rating = ?,
                value_rating         = ?,
                experience_rating    = ?,
                recommend_rating     = ?,
                overall_rating       = ?,
                review_text          = ?
            WHERE rating_id = ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int)   $data['organization_rating'],
                (int)   $data['communication_rating'],
                (int)   $data['value_rating'],
                (int)   $data['experience_rating'],
                (int)   $data['recommend_rating'],
                round((float) $data['overall_rating'], 2),
                trim($data['review_text'] ?? ''),
                $ratingId,
            ]);
        } catch (PDOException $e) {
            error_log("TripRating::update error: " . $e->getMessage());
            return false;
        }
    }

    // ── 3. findById() ─────────────────────────────────────────────────────────
    public static function findById(int $ratingId): ?array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM ft_trip_ratings WHERE rating_id = ?");
            $stmt->execute([$ratingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("TripRating::findById error: " . $e->getMessage());
            return null;
        }
    }

    // ── 4. findByUserAndTrip() ────────────────────────────────────────────────
    public static function findByUserAndTrip(int $userId, int $tripId): ?array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM ft_trip_ratings WHERE user_id = ? AND trip_id = ?"
            );
            $stmt->execute([$userId, $tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("TripRating::findByUserAndTrip error: " . $e->getMessage());
            return null;
        }
    }

    // ── 5. getRatingsForTrip() ────────────────────────────────────────────────
    public static function getRatingsForTrip(int $tripId): array
    {
        global $pdo;

        $sql = "
            SELECT r.*, u.name AS reviewer_name, u.profile_photo AS reviewer_photo,
                   u.username AS reviewer_username
            FROM ft_trip_ratings r
            JOIN ft_users u ON r.user_id = u.user_id
            WHERE r.trip_id = ?
            ORDER BY r.created_at DESC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripRating::getRatingsForTrip error: " . $e->getMessage());
            return [];
        }
    }

    // ── 6. getAverageRatings() ────────────────────────────────────────────────
    public static function getAverageRatings(int $tripId): ?array
    {
        global $pdo;

        $sql = "
            SELECT
                AVG(overall_rating)       AS avg_overall,
                AVG(organization_rating)  AS avg_organization,
                AVG(communication_rating) AS avg_communication,
                AVG(value_rating)         AS avg_value,
                AVG(experience_rating)    AS avg_experience,
                AVG(recommend_rating)     AS avg_recommend,
                COUNT(*)                  AS total_ratings
            FROM ft_trip_ratings
            WHERE trip_id = ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("TripRating::getAverageRatings error: " . $e->getMessage());
            return null;
        }
    }

    // ── 7. getRatingCount() ───────────────────────────────────────────────────
    public static function getRatingCount(int $tripId): int
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM ft_trip_ratings WHERE trip_id = ?"
            );
            $stmt->execute([$tripId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("TripRating::getRatingCount error: " . $e->getMessage());
            return 0;
        }
    }

    // ── 8. delete() ───────────────────────────────────────────────────────────
    public static function delete(int $ratingId): bool
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                "DELETE FROM ft_trip_ratings WHERE rating_id = ?"
            );
            return $stmt->execute([$ratingId]);
        } catch (PDOException $e) {
            error_log("TripRating::delete error: " . $e->getMessage());
            return false;
        }
    }
}
