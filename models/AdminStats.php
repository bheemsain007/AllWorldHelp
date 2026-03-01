<?php
// models/AdminStats.php
// Task T31 — Admin statistics model

require_once __DIR__ . '/../config/database.php';

class AdminStats
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // ── Overview stats ─────────────────────────────────────────────────────────
    public static function getOverviewStats(): array
    {
        $db    = self::db();
        $stats = [];

        // Total users
        $stats['total_users'] = (int) $db->query("SELECT COUNT(*) FROM ft_users")->fetchColumn();

        // New users in last 24 h
        $stats['new_users_24h'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_users WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetchColumn();

        // Active trips
        $stats['active_trips'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_trips WHERE status IN ('open', 'ongoing')"
        )->fetchColumn();

        // Total trips
        $stats['total_trips'] = (int) $db->query("SELECT COUNT(*) FROM ft_trips")->fetchColumn();

        // Trips this month
        $stats['trips_this_month'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_trips WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
        )->fetchColumn();

        // Pending reports
        $stats['pending_reports'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_reports WHERE status = 'pending'"
        )->fetchColumn();

        // Average trip rating + count
        $row = $db->query("SELECT AVG(overall_rating), COUNT(*) FROM ft_trip_ratings")->fetch(PDO::FETCH_NUM);
        $stats['avg_rating']    = round((float) ($row[0] ?? 0), 1);
        $stats['total_ratings'] = (int) ($row[1] ?? 0);

        // Total accepted connections
        $stats['total_connections'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_connections WHERE status = 'accepted'"
        )->fetchColumn();

        // Total messages sent
        $stats['total_messages'] = (int) $db->query("SELECT COUNT(*) FROM ft_messages")->fetchColumn();

        return $stats;
    }

    // ── Recent activity feed ───────────────────────────────────────────────────
    public static function getRecentActivity(int $limit = 10): array
    {
        $db         = self::db();
        $activities = [];

        // Recent registrations
        $rows = $db->query("SELECT name, created_at FROM ft_users ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $activities[] = [
                'icon'       => '👤',
                'text'       => htmlspecialchars($r['name']) . ' joined Fellow Traveler',
                'created_at' => $r['created_at'],
            ];
        }

        // Recent trips
        $rows = $db->query("SELECT title, created_at FROM ft_trips ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $activities[] = [
                'icon'       => '🗺️',
                'text'       => 'New trip: ' . htmlspecialchars($r['title']),
                'created_at' => $r['created_at'],
            ];
        }

        // Recent reports
        $rows = $db->query("SELECT category, created_at FROM ft_reports ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $activities[] = [
                'icon'       => '🚨',
                'text'       => 'New report: ' . htmlspecialchars(ucfirst($r['category'])),
                'created_at' => $r['created_at'],
            ];
        }

        // Recent connections
        $rows = $db->query(
            "SELECT c.created_at, u1.name AS n1, u2.name AS n2
               FROM ft_connections c
               JOIN ft_users u1 ON u1.user_id = c.user_id
               JOIN ft_users u2 ON u2.user_id = c.connected_user_id
              WHERE c.status = 'accepted'
              ORDER BY c.created_at DESC LIMIT 2"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $activities[] = [
                'icon'       => '🤝',
                'text'       => htmlspecialchars($r['n1']) . ' connected with ' . htmlspecialchars($r['n2']),
                'created_at' => $r['created_at'],
            ];
        }

        // Sort newest-first and slice
        usort($activities, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        return array_slice($activities, 0, $limit);
    }

    // ── Growth data (daily new-user counts for the last N days) ───────────────
    public static function getGrowthData(int $days = 30): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
               FROM ft_users
              WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Popular trips ──────────────────────────────────────────────────────────
    public static function getPopularTrips(int $limit = 5): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT t.trip_id, t.title, t.destination, t.status,
                    COUNT(tp.user_id) AS participant_count
               FROM ft_trips t
               LEFT JOIN ft_trip_participants tp
                      ON tp.trip_id = t.trip_id AND tp.status = 'accepted'
              WHERE t.status IN ('open', 'ongoing')
              GROUP BY t.trip_id
              ORDER BY participant_count DESC
              LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Most active users (by trips organised) ─────────────────────────────────
    public static function getActiveUsers(int $limit = 5): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT u.user_id, u.name, u.email, u.profile_photo,
                    COUNT(t.trip_id) AS trips_organized
               FROM ft_users u
               LEFT JOIN ft_trips t ON t.user_id = u.user_id
              GROUP BY u.user_id
              ORDER BY trips_organized DESC
              LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Alerts ─────────────────────────────────────────────────────────────────
    public static function getAlerts(): array
    {
        $db     = self::db();
        $alerts = [];

        $pending = (int) $db->query("SELECT COUNT(*) FROM ft_reports WHERE status = 'pending'")->fetchColumn();
        if ($pending > 0) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => '🚨',
                'message' => "{$pending} pending report(s) need moderation.",
                'link'    => 'reports_dashboard.php',
                'cta'     => 'Review Now',
            ];
        }

        $cancelledToday = (int) $db->query(
            "SELECT COUNT(*) FROM ft_trips WHERE status = 'cancelled' AND DATE(updated_at) = CURDATE()"
        )->fetchColumn();
        if ($cancelledToday > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => '⚠️',
                'message' => "{$cancelledToday} trip(s) cancelled today.",
                'link'    => 'trips.php',
                'cta'     => 'View Trips',
            ];
        }

        return $alerts;
    }
}
