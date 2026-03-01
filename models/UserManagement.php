<?php
// models/UserManagement.php
// Task T32 — Admin user management queries

require_once __DIR__ . '/../config/database.php';

class UserManagement
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // ── List users (search + filter + paginate) ────────────────────────────────
    public static function getUsers(
        string $search      = '',
        string $status      = 'all',
        int    $page        = 1,
        int    $perPage     = 20
    ): array {
        $db     = self::db();
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where  = '1=1';

        if (!empty($search)) {
            $where   .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($status === 'active') {
            $where .= " AND u.is_blocked = 0";
        } elseif ($status === 'blocked') {
            $where .= " AND u.is_blocked = 1";
        }

        // Total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM ft_users u WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Main query
        $sql = "SELECT
                    u.*,
                    (SELECT COUNT(*) FROM ft_trips WHERE user_id = u.user_id)                                   AS trips_organized,
                    (SELECT COUNT(*) FROM ft_trip_participants WHERE user_id = u.user_id AND status='accepted') AS trips_joined
                FROM ft_users u
                WHERE {$where}
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['users' => $users, 'total' => $total];
    }

    // ── Single user ─────────────────────────────────────────────────────────────
    public static function getUserDetails(int $userId): ?array
    {
        $db   = self::db();
        $stmt = $db->prepare("SELECT * FROM ft_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── User stats ──────────────────────────────────────────────────────────────
    public static function getUserStats(int $userId): array
    {
        $db    = self::db();
        $stats = [];

        $q = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) {
            $s = $db->prepare($sql);
            $s->execute($p);
            return (int) $s->fetchColumn();
        })();

        $stats['trips_organized'] = $q("SELECT COUNT(*) FROM ft_trips WHERE user_id = ?", [$userId]);
        $stats['trips_joined']    = $q("SELECT COUNT(*) FROM ft_trip_participants WHERE user_id = ? AND status='accepted'", [$userId]);
        $stats['connections']     = $q(
            "SELECT COUNT(*) FROM ft_connections
              WHERE (user_id = ? OR connected_user_id = ?) AND status = 'accepted'",
            [$userId, $userId]
        );
        $stats['reviews']         = $q("SELECT COUNT(*) FROM ft_trip_ratings WHERE user_id = ?", [$userId]);
        $stats['reports_filed']   = $q("SELECT COUNT(*) FROM ft_reports WHERE reporter_id = ?", [$userId]);
        $stats['times_reported']  = $q("SELECT COUNT(*) FROM ft_reports WHERE reported_type='user' AND reported_id = ?", [$userId]);
        $stats['messages_sent']   = $q("SELECT COUNT(*) FROM ft_messages WHERE sender_id = ?", [$userId]);

        return $stats;
    }

    // ── Recent activity ─────────────────────────────────────────────────────────
    public static function getUserActivity(int $userId, int $limit = 15): array
    {
        $db  = self::db();
        $act = [];

        // Trips created
        $rows = $db->prepare("SELECT title, created_at FROM ft_trips WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $rows->execute([$userId]);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $act[] = ['icon' => '🗺️', 'text' => 'Created trip: ' . htmlspecialchars($r['title']), 'at' => $r['created_at']];
        }

        // Trip joins
        $rows = $db->prepare(
            "SELECT t.title, tp.created_at
               FROM ft_trip_participants tp
               JOIN ft_trips t ON t.trip_id = tp.trip_id
              WHERE tp.user_id = ? AND tp.status = 'accepted'
              ORDER BY tp.created_at DESC LIMIT 5"
        );
        $rows->execute([$userId]);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $act[] = ['icon' => '✈️', 'text' => 'Joined trip: ' . htmlspecialchars($r['title']), 'at' => $r['created_at']];
        }

        // Reviews written
        $rows = $db->prepare(
            "SELECT t.title, r.created_at
               FROM ft_trip_ratings r
               JOIN ft_trips t ON t.trip_id = r.trip_id
              WHERE r.user_id = ?
              ORDER BY r.created_at DESC LIMIT 3"
        );
        $rows->execute([$userId]);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $act[] = ['icon' => '⭐', 'text' => 'Reviewed trip: ' . htmlspecialchars($r['title']), 'at' => $r['created_at']];
        }

        usort($act, fn($a, $b) => strtotime($b['at']) - strtotime($a['at']));
        return array_slice($act, 0, $limit);
    }

    // ── Block ───────────────────────────────────────────────────────────────────
    public static function blockUser(int $userId, string $reason = 'Admin action'): bool
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "UPDATE ft_users
                SET is_blocked = 1, blocked_at = NOW(), blocked_reason = ?
              WHERE user_id = ?"
        );
        return $stmt->execute([$reason, $userId]);
    }

    // ── Unblock ─────────────────────────────────────────────────────────────────
    public static function unblockUser(int $userId): bool
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "UPDATE ft_users
                SET is_blocked = 0, blocked_at = NULL, blocked_reason = NULL
              WHERE user_id = ?"
        );
        return $stmt->execute([$userId]);
    }

    // ── Delete ──────────────────────────────────────────────────────────────────
    public static function deleteUser(int $userId): bool
    {
        $db   = self::db();
        // Relies on ON DELETE CASCADE or SET NULL FK constraints in the schema.
        $stmt = $db->prepare("DELETE FROM ft_users WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
}
