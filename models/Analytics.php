<?php
// models/Analytics.php
// Task T33 — Analytics data model

require_once __DIR__ . '/../config/database.php';

class Analytics
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    private static function rangeDays(string $range): int
    {
        return match ($range) {
            '7'   => 7,
            '30'  => 30,
            '90'  => 90,
            '365' => 365,
            'all' => 3650,
            default => 30,
        };
    }

    // ── User registrations by day ──────────────────────────────────────────────
    public static function getUserGrowth(string $range = '30'): array
    {
        $db   = self::db();
        $days = self::rangeDays($range);
        $stmt = $db->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
               FROM ft_users
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Trips created by day ───────────────────────────────────────────────────
    public static function getTripStats(string $range = '30'): array
    {
        $db   = self::db();
        $days = self::rangeDays($range);
        $stmt = $db->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
               FROM ft_trips
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Expense totals by day (revenue proxy) ─────────────────────────────────
    public static function getRevenueData(string $range = '30'): array
    {
        $db   = self::db();
        $days = self::rangeDays($range);
        $stmt = $db->prepare(
            "SELECT DATE(created_at) AS date, SUM(amount) AS amount
               FROM ft_trip_expenses
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Top destinations ───────────────────────────────────────────────────────
    public static function getTopDestinations(int $limit = 10): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT destination, COUNT(*) AS count
               FROM ft_trips
              WHERE destination IS NOT NULL AND destination <> ''
              GROUP BY destination
              ORDER BY count DESC
              LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Trip type distribution ─────────────────────────────────────────────────
    public static function getTripTypeDistribution(): array
    {
        $db   = self::db();
        $stmt = $db->query(
            "SELECT trip_type, COUNT(*) AS count
               FROM ft_trips
              GROUP BY trip_type
              ORDER BY count DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Key insights (current period vs previous period) ──────────────────────
    public static function getKeyInsights(string $range = '30'): array
    {
        $db      = self::db();
        $days    = self::rangeDays($range);
        $ins     = [];

        $pct = function (float $curr, float $prev): float {
            if ($prev <= 0) return 0.0;
            return round(($curr - $prev) / $prev * 100, 1);
        };

        // ── Users ──────────────────────────────────────────────────────────────
        $s = $db->prepare("SELECT COUNT(*) FROM ft_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $s->execute([$days]);
        $ins['new_users'] = (int) $s->fetchColumn();

        $s = $db->prepare(
            "SELECT COUNT(*) FROM ft_users
              WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY)
                                   AND DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $s->execute([$days * 2, $days]);
        $prevUsers = (int) $s->fetchColumn();
        $ins['user_growth_percent'] = $pct($ins['new_users'], $prevUsers);

        // ── Trips ──────────────────────────────────────────────────────────────
        $s = $db->prepare("SELECT COUNT(*) FROM ft_trips WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $s->execute([$days]);
        $ins['trips_created'] = (int) $s->fetchColumn();

        $s = $db->prepare(
            "SELECT COUNT(*) FROM ft_trips
              WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY)
                                   AND DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $s->execute([$days * 2, $days]);
        $prevTrips = (int) $s->fetchColumn();
        $ins['trip_growth_percent'] = $pct($ins['trips_created'], $prevTrips);

        // ── Revenue ────────────────────────────────────────────────────────────
        $s = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM ft_trip_expenses WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $s->execute([$days]);
        $ins['total_revenue'] = (float) $s->fetchColumn();

        $s = $db->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM ft_trip_expenses
              WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY)
                                   AND DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $s->execute([$days * 2, $days]);
        $prevRevenue = (float) $s->fetchColumn();
        $ins['revenue_growth_percent'] = $pct($ins['total_revenue'], $prevRevenue);

        // ── Ratings ────────────────────────────────────────────────────────────
        $row = $db->query("SELECT AVG(overall_rating), COUNT(*) FROM ft_trip_ratings")->fetch(PDO::FETCH_NUM);
        $ins['avg_rating']    = round((float)($row[0] ?? 0), 1);
        $ins['total_ratings'] = (int)($row[1] ?? 0);

        return $ins;
    }

    // ── Messages per day ───────────────────────────────────────────────────────
    public static function getMessageActivity(string $range = '30'): array
    {
        $db   = self::db();
        $days = self::rangeDays($range);
        $stmt = $db->prepare(
            "SELECT DATE(sent_at) AS date, COUNT(*) AS count
               FROM ft_messages
              WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY DATE(sent_at)
              ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
