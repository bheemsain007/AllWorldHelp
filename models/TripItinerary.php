<?php
// models/TripItinerary.php
// Task T25 — TripItinerary model: day-by-day activities for a trip

class TripItinerary {

    private const TABLE      = 'ft_trip_itinerary';
    private const CATEGORIES = ['sightseeing', 'food', 'transport', 'activity', 'accommodation'];

    // ── Create Activity ───────────────────────────────────────────────────────
    /**
     * Insert a new itinerary activity.
     * $data: trip_id, day_number, activity_time, activity_title,
     *        location (optional), description (optional), category
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_trip_itinerary
                    (trip_id, day_number, activity_time, activity_title,
                     location, description, category, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int)    $data['trip_id'],
                (int)    $data['day_number'],
                         $data['activity_time'],
                         $data['activity_title'],
                         $data['location']     ?: null,
                         $data['description']  ?: null,
                         $data['category'],
            ]);
        } catch (PDOException $e) {
            error_log("TripItinerary::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Itinerary (grouped by day) ────────────────────────────────────────
    /**
     * Return all activities for a trip, ordered by day then time.
     * Returns: array keyed by day_number, each value an array of activity rows.
     */
    public static function getItineraryByDay(int $tripId): array {
        global $pdo;

        $sql = "SELECT itinerary_id, trip_id, day_number, activity_time,
                       activity_title, location, description, category, created_at
                FROM ft_trip_itinerary
                WHERE trip_id = ?
                ORDER BY day_number ASC, activity_time ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripItinerary::getItineraryByDay error: " . $e->getMessage());
            return [];
        }

        // Group by day_number
        $grouped = [];
        foreach ($rows as $row) {
            $day = (int) $row['day_number'];
            $grouped[$day][] = $row;
        }
        ksort($grouped);
        return $grouped;
    }

    // ── Find By ID ────────────────────────────────────────────────────────────
    public static function findById(int $itineraryId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_trip_itinerary WHERE itinerary_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itineraryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Update Activity ───────────────────────────────────────────────────────
    public static function update(int $itineraryId, array $data): bool {
        global $pdo;

        $sql = "UPDATE ft_trip_itinerary
                SET day_number      = ?,
                    activity_time   = ?,
                    activity_title  = ?,
                    location        = ?,
                    description     = ?,
                    category        = ?
                WHERE itinerary_id  = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int) $data['day_number'],
                      $data['activity_time'],
                      $data['activity_title'],
                      $data['location']    ?: null,
                      $data['description'] ?: null,
                      $data['category'],
                (int) $itineraryId,
            ]);
        } catch (PDOException $e) {
            error_log("TripItinerary::update error: " . $e->getMessage());
            return false;
        }
    }

    // ── Delete Activity ───────────────────────────────────────────────────────
    public static function delete(int $itineraryId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_trip_itinerary WHERE itinerary_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$itineraryId]);
        } catch (PDOException $e) {
            error_log("TripItinerary::delete error: " . $e->getMessage());
            return false;
        }
    }

    // ── Count ─────────────────────────────────────────────────────────────────
    public static function countForTrip(int $tripId): int {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ft_trip_itinerary WHERE trip_id = ?");
            $stmt->execute([$tripId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Valid Category ────────────────────────────────────────────────────────
    public static function isValidCategory(string $cat): bool {
        return in_array($cat, self::CATEGORIES, true);
    }

    // ── Category Meta ─────────────────────────────────────────────────────────
    public static function getCategoryMeta(): array {
        return [
            'sightseeing'   => ['label' => '👁️ Sightseeing',   'class' => 'cat-sightseeing'],
            'food'          => ['label' => '🍕 Food',           'class' => 'cat-food'],
            'transport'     => ['label' => '🚗 Transport',      'class' => 'cat-transport'],
            'activity'      => ['label' => '🎯 Activity',       'class' => 'cat-activity'],
            'accommodation' => ['label' => '🏨 Accommodation',  'class' => 'cat-accommodation'],
        ];
    }
}
?>
