<?php
// models/TripUpdate.php
// Task T21 — TripUpdate model: create update, fetch for trip, notify participants (stub)

class TripUpdate {

    private const TABLE       = 'ft_trip_updates';
    private const VALID_TYPES = ['info', 'warning', 'reminder', 'completion'];

    // ── Create Update ─────────────────────────────────────────────────────────
    /**
     * Insert a new trip update row.
     * $data: trip_id, user_id, update_text, update_type
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_trip_updates (trip_id, user_id, update_text, update_type, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        try {
            $stmt    = $pdo->prepare($sql);
            $success = $stmt->execute([
                (int) $data['trip_id'],
                (int) $data['user_id'],
                $data['update_text'],
                $data['update_type'],
            ]);

            if ($success) {
                // Stub: Future T26 Notification::create() for each participant
                self::notifyParticipants((int) $data['trip_id']);
            }

            return $success;
        } catch (PDOException $e) {
            error_log("TripUpdate::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Updates For Trip ──────────────────────────────────────────────────
    /**
     * Return all updates for a trip, newest first.
     */
    public static function getUpdatesForTrip(int $tripId, int $limit = 50): array {
        global $pdo;

        $sql = "SELECT u.update_id, u.trip_id, u.update_text, u.update_type, u.created_at,
                       usr.name AS poster_name, usr.profile_photo AS poster_photo
                FROM ft_trip_updates u
                JOIN ft_users usr ON u.user_id = usr.user_id
                WHERE u.trip_id = :tid
                ORDER BY u.created_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':tid', $tripId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripUpdate::getUpdatesForTrip error: " . $e->getMessage());
            return [];
        }
    }

    // ── Count Updates ─────────────────────────────────────────────────────────
    /**
     * Return the number of updates for a trip (used for badge on view.php).
     */
    public static function countForTrip(int $tripId): int {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM ft_trip_updates WHERE trip_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Valid Types ───────────────────────────────────────────────────────────
    public static function isValidType(string $type): bool {
        return in_array($type, self::VALID_TYPES, true);
    }

    // ── Notify Participants (Stub) ────────────────────────────────────────────
    /**
     * Placeholder: Future T26 will create Notification rows for each participant.
     */
    private static function notifyParticipants(int $tripId): void {
        global $pdo;

        $sql = "SELECT user_id FROM ft_trip_participants
                WHERE trip_id = ? AND status = 'accepted'";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            // $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            // foreach ($participants as $uid) {
            //     Notification::create($uid, 'trip_update', $tripId);
            // }
        } catch (PDOException $e) {
            error_log("TripUpdate::notifyParticipants error: " . $e->getMessage());
        }
    }
}
?>
