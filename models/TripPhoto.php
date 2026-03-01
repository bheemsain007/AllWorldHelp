<?php
// models/TripPhoto.php
// Task T23 — TripPhoto model: upload photos, view gallery, delete photos

class TripPhoto {

    private const TABLE = 'ft_trip_photos';

    // ── Create Photo ──────────────────────────────────────────────────────────
    /**
     * Insert a new photo record.
     * $data: trip_id, user_id, photo_filename, caption (optional)
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_trip_photos (trip_id, user_id, photo_filename, caption, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['trip_id'],
                (int) $data['user_id'],
                $data['photo_filename'],
                $data['caption'] ?: null,
            ]);
        } catch (PDOException $e) {
            error_log("TripPhoto::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Photos For Trip ───────────────────────────────────────────────────
    /**
     * Return all photos for a trip, newest first, with uploader name.
     */
    public static function getPhotosForTrip(int $tripId, int $limit = 100): array {
        global $pdo;

        $sql = "SELECT p.photo_id, p.trip_id, p.user_id, p.photo_filename,
                       p.caption, p.created_at,
                       u.name AS uploader_name, u.profile_photo AS uploader_photo
                FROM ft_trip_photos p
                JOIN ft_users u ON p.user_id = u.user_id
                WHERE p.trip_id = :tid
                ORDER BY p.created_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':tid', $tripId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripPhoto::getPhotosForTrip error: " . $e->getMessage());
            return [];
        }
    }

    // ── Find By ID ────────────────────────────────────────────────────────────
    /**
     * Return a single photo row, or null if not found.
     */
    public static function findById(int $photoId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_trip_photos WHERE photo_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$photoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("TripPhoto::findById error: " . $e->getMessage());
            return null;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    /**
     * Hard-delete a photo record by ID.
     * Caller is responsible for deleting the file from disk.
     */
    public static function delete(int $photoId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_trip_photos WHERE photo_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$photoId]);
        } catch (PDOException $e) {
            error_log("TripPhoto::delete error: " . $e->getMessage());
            return false;
        }
    }

    // ── Count ─────────────────────────────────────────────────────────────────
    /**
     * Return total photo count for a trip.
     */
    public static function getPhotoCount(int $tripId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_trip_photos WHERE trip_id = ?";

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
