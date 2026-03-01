<?php
/**
 * Trip Model
 * Fellow Traveler — Task T13
 *
 * Static CRUD class for ft_trips table.
 *
 * Public methods:
 *   Trip::search($filters, $limit, $offset)   → array   (search with filters)
 *   Trip::countSearch($filters)               → int     (total results for pagination)
 *   Trip::findById($id)                       → array|null
 *   Trip::findByUser($userId, $limit, $offset)→ array
 *   Trip::create($data)                       → int|false (new trip_id)
 *   Trip::update($tripId, $data)              → bool
 *   Trip::delete($tripId)                     → bool     (soft delete: status='deleted')
 *   Trip::count($userId)                      → int
 *   Trip::getRecent($limit)                   → array
 */
class Trip
{
    private const TABLE = 'ft_trips';

    // Fields allowed in update()
    private const UPDATABLE_FIELDS = [
        'title', 'description', 'destination', 'start_date', 'end_date',
        'budget_per_person', 'max_travelers', 'joined_travelers',
        'trip_type', 'status', 'image', 'is_featured',
    ];

    // ── 1. search() ──────────────────────────────────────────────────────────
    /**
     * Search trips with optional filters, sorting, and pagination.
     *
     * @param  array  $filters   Supported keys:
     *                           keyword, start_date, end_date,
     *                           min_budget, max_budget, trip_type,
     *                           min_seats, status, sort
     * @param  int    $limit
     * @param  int    $offset
     * @return array
     */
    public static function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        global $pdo;

        [$where, $params] = self::buildWhere($filters, 't');

        // Sorting
        $orderBy = self::buildOrderBy($filters['sort'] ?? '');

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT t.*, u.name AS organizer_name, u.profile_photo AS organizer_photo
            FROM ft_trips t
            JOIN ft_users u ON t.user_id = u.user_id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Trip::search error: " . $e->getMessage());
            return [];
        }
    }

    // ── 2. countSearch() ─────────────────────────────────────────────────────
    /**
     * Count total trips matching the filters (for pagination).
     *
     * @param  array $filters  Same keys as search()
     * @return int
     */
    public static function countSearch(array $filters = []): int
    {
        global $pdo;

        [$where, $params] = self::buildWhere($filters);

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM ft_trips WHERE {$whereClause}";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Trip::countSearch error: " . $e->getMessage());
            return 0;
        }
    }

    // ── 3. findById() ────────────────────────────────────────────────────────
    /**
     * Fetch a single trip by ID, joined with organizer info.
     *
     * @param  int        $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        global $pdo;

        $sql = "
            SELECT t.*, u.name AS organizer_name, u.profile_photo AS organizer_photo,
                   u.username AS organizer_username, u.is_verified AS organizer_verified
            FROM ft_trips t
            JOIN ft_users u ON t.user_id = u.user_id
            WHERE t.trip_id = ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("Trip::findById error: " . $e->getMessage());
            return null;
        }
    }

    // ── 4. findByUser() ──────────────────────────────────────────────────────
    /**
     * Get all trips created by a given user.
     *
     * @param  int   $userId
     * @param  int   $limit
     * @param  int   $offset
     * @return array
     */
    public static function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        global $pdo;

        $sql = "
            SELECT t.*, u.name AS organizer_name, u.profile_photo AS organizer_photo
            FROM ft_trips t
            JOIN ft_users u ON t.user_id = u.user_id
            WHERE t.user_id = ? AND t.status != 'deleted'
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Trip::findByUser error: " . $e->getMessage());
            return [];
        }
    }

    // ── 5. create() ──────────────────────────────────────────────────────────
    /**
     * Insert a new trip.
     *
     * Required keys: user_id, title, destination, start_date, end_date,
     *                budget_per_person, max_travelers, trip_type
     * Optional keys: description, image, status
     *
     * @param  array     $data
     * @return int|false  New trip_id on success, false on failure
     */
    public static function create(array $data)
    {
        global $pdo;

        $allowed = [
            'user_id', 'title', 'description', 'destination',
            'start_date', 'end_date', 'budget_per_person',
            'max_travelers', 'trip_type', 'image', 'status',
        ];

        $fields = [];
        $values = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $values[] = $data[$field];
            }
        }

        // Defaults
        if (!in_array('status', $fields)) {
            $fields[] = 'status';
            $values[] = 'open';
        }
        if (!in_array('joined_travelers', $fields)) {
            $fields[] = 'joined_travelers';
            $values[] = 0;
        }

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $cols         = implode(', ', $fields);

        $sql = "INSERT INTO ft_trips ({$cols}) VALUES ({$placeholders})";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Trip::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── 6. update() ──────────────────────────────────────────────────────────
    /**
     * Update allowed fields for a trip.
     *
     * @param  int   $tripId
     * @param  array $data   Keys must be in UPDATABLE_FIELDS whitelist
     * @return bool
     */
    public static function update(int $tripId, array $data): bool
    {
        global $pdo;

        $sets   = [];
        $values = [];

        foreach ($data as $field => $value) {
            if (in_array($field, self::UPDATABLE_FIELDS)) {
                $sets[]   = "{$field} = ?";
                $values[] = $value;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $values[]  = $tripId;
        $setClause = implode(', ', $sets);
        $sql       = "UPDATE ft_trips SET {$setClause}, updated_at = NOW() WHERE trip_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Trip::update error: " . $e->getMessage());
            return false;
        }
    }

    // ── 7. delete() ──────────────────────────────────────────────────────────
    /**
     * Soft-delete a trip (sets status = 'deleted').
     *
     * @param  int  $tripId
     * @return bool
     */
    public static function delete(int $tripId): bool
    {
        return self::update($tripId, ['status' => 'deleted']);
    }

    // ── 8. count() ───────────────────────────────────────────────────────────
    /**
     * Count non-deleted trips for a user (used in profile stats).
     *
     * @param  int $userId
     * @return int
     */
    public static function count(int $userId): int
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM ft_trips WHERE user_id = ? AND status != 'deleted'"
            );
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Trip::count error: " . $e->getMessage());
            return 0;
        }
    }

    // ── 9. getRecent() ───────────────────────────────────────────────────────
    /**
     * Fetch recent open trips for the homepage/dashboard.
     *
     * @param  int   $limit
     * @return array
     */
    public static function getRecent(int $limit = 6): array
    {
        global $pdo;

        $sql = "
            SELECT t.*, u.name AS organizer_name, u.profile_photo AS organizer_photo
            FROM ft_trips t
            JOIN ft_users u ON t.user_id = u.user_id
            WHERE t.status = 'open'
            ORDER BY t.created_at DESC
            LIMIT ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Trip::getRecent error: " . $e->getMessage());
            return [];
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Build WHERE clauses + params array from filter map.
     * Pass $tableAlias = '' when querying ft_trips directly (countSearch).
     *
     * @param  array  $filters
     * @param  string $alias   Table alias prefix (e.g. 't' → 't.column')
     * @return array           [where_clauses[], params[]]
     */
    private static function buildWhere(array $filters, string $alias = ''): array
    {
        $col    = fn($c) => $alias ? "{$alias}.{$c}" : $c;
        $where  = [];
        $params = [];

        // Exclude deleted trips
        $where[] = $col('status') . " != 'deleted'";

        // Keyword: search in destination, title, and description
        if (!empty($filters['keyword'])) {
            $kw      = '%' . $filters['keyword'] . '%';
            $where[] = '(' . $col('destination') . ' LIKE ? OR ' . $col('title') . ' LIKE ? OR ' . $col('description') . ' LIKE ?)';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        // Date range
        if (!empty($filters['start_date'])) {
            $where[]  = $col('start_date') . ' >= ?';
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]  = $col('end_date') . ' <= ?';
            $params[] = $filters['end_date'];
        }

        // Budget range
        if (!empty($filters['min_budget'])) {
            $where[]  = $col('budget_per_person') . ' >= ?';
            $params[] = (int) $filters['min_budget'];
        }
        if (!empty($filters['max_budget'])) {
            $where[]  = $col('budget_per_person') . ' <= ?';
            $params[] = (int) $filters['max_budget'];
        }

        // Trip type — single string or array (multi-select checkboxes)
        if (!empty($filters['trip_type'])) {
            $types = is_array($filters['trip_type'])
                ? array_filter(array_map('trim', $filters['trip_type']))
                : [trim($filters['trip_type'])];
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '?'));
                $where[]      = $col('trip_type') . " IN ({$placeholders})";
                $params       = array_merge($params, array_values($types));
            }
        }

        // Duration filter (uses DATEDIFF on end_date - start_date)
        if (!empty($filters['duration'])) {
            $sd = $col('start_date');
            $ed = $col('end_date');
            switch ($filters['duration']) {
                case 'short':   // 1–3 days
                    $where[] = "DATEDIFF({$ed}, {$sd}) BETWEEN 1 AND 3";
                    break;
                case 'medium':  // 4–7 days
                    $where[] = "DATEDIFF({$ed}, {$sd}) BETWEEN 4 AND 7";
                    break;
                case 'long':    // 8+ days
                    $where[] = "DATEDIFF({$ed}, {$sd}) >= 8";
                    break;
            }
        }

        // Max travelers range
        if (!empty($filters['max_travelers_range'])) {
            switch ($filters['max_travelers_range']) {
                case 'small':   // 2–5
                    $where[] = $col('max_travelers') . ' BETWEEN 2 AND 5';
                    break;
                case 'medium':  // 6–10
                    $where[] = $col('max_travelers') . ' BETWEEN 6 AND 10';
                    break;
                case 'large':   // 11–20
                    $where[] = $col('max_travelers') . ' BETWEEN 11 AND 20';
                    break;
                case 'xlarge':  // 21+
                    $where[] = $col('max_travelers') . ' >= 21';
                    break;
            }
        }

        // Minimum seats available
        if (!empty($filters['min_seats'])) {
            $where[]  = '(' . $col('max_travelers') . ' - ' . $col('joined_travelers') . ') >= ?';
            $params[] = (int) $filters['min_seats'];
        }

        // Status (default: open only)
        if (!empty($filters['status'])) {
            $where[]  = $col('status') . ' = ?';
            $params[] = $filters['status'];
        } else {
            $where[] = $col('status') . " = 'open'";
        }

        return [$where, $params];
    }

    /**
     * Build ORDER BY clause from sort key.
     *
     * @param  string $sort
     * @return string
     */
    private static function buildOrderBy(string $sort): string
    {
        $map = [
            'date_asc'    => 't.start_date ASC',
            'date_desc'   => 't.start_date DESC',
            'budget_asc'  => 't.budget_per_person ASC',
            'budget_desc' => 't.budget_per_person DESC',
            'newest'      => 't.created_at DESC',
            'most_joined' => 't.joined_travelers DESC',
        ];
        return $map[$sort] ?? 't.start_date ASC';
    }

    // ── getParticipants (T24) ─────────────────────────────────────────────────
    /**
     * Return all accepted participants (including organizer) for a trip.
     * Each row: user_id, name, username, profile_photo
     */
    public static function getParticipants(int $tripId): array {
        global $pdo;

        // Organizer + accepted participants via UNION
        $sql = "SELECT u.user_id, u.name, u.username, u.profile_photo
                FROM ft_trips t
                JOIN ft_users u ON u.user_id = t.user_id
                WHERE t.trip_id = :tid

                UNION

                SELECT u2.user_id, u2.name, u2.username, u2.profile_photo
                FROM ft_trip_participants p
                JOIN ft_users u2 ON u2.user_id = p.user_id
                WHERE p.trip_id = :tid2 AND p.status = 'accepted'

                ORDER BY name ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':tid',  $tripId, PDO::PARAM_INT);
            $stmt->bindValue(':tid2', $tripId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Trip::getParticipants error: " . $e->getMessage());
            return [];
        }
    }

    // ── updateStatus (T29) ───────────────────────────────────────────────────
    /**
     * Convenience wrapper: set trip status to a lifecycle value.
     * Valid values: open, full, closed, ongoing, completed, cancelled.
     */
    public static function updateStatus(int $tripId, string $status): bool
    {
        return self::update($tripId, ['status' => $status]);
    }

    // ── isParticipant (T22) ───────────────────────────────────────────────────
    /**
     * Return true if $userId is an accepted participant of $tripId,
     * OR if $userId is the trip organizer.
     */
    public static function isParticipant(int $tripId, int $userId): bool {
        global $pdo;

        // Check organizer first (fast path)
        $sql = "SELECT 1 FROM ft_trips WHERE trip_id = ? AND user_id = ? LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tripId, $userId]);
            if ($stmt->fetch()) return true;
        } catch (\PDOException $e) {}

        // Check accepted participant
        $sql2 = "SELECT 1 FROM ft_trip_participants
                 WHERE trip_id = ? AND user_id = ? AND status = 'accepted' LIMIT 1";
        try {
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$tripId, $userId]);
            return (bool) $stmt2->fetch();
        } catch (\PDOException $e) {
            return false;
        }
    }
}
?>
