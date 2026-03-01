<?php
// models/Revenue.php
// Task T34 — Revenue tracking & financial management model

require_once __DIR__ . '/../config/database.php';

class Revenue
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // ── Summary stats ──────────────────────────────────────────────────────────
    public static function getSummary(): array
    {
        $db  = self::db();
        $sum = [];

        // Total revenue (completed)
        $sum['total_revenue'] = (float) $db->query(
            "SELECT COALESCE(SUM(amount),0) FROM ft_transactions WHERE status='completed'"
        )->fetchColumn();

        // This month
        $sum['month_revenue'] = (float) $db->query(
            "SELECT COALESCE(SUM(amount),0) FROM ft_transactions
              WHERE status='completed'
                AND MONTH(created_at)=MONTH(NOW())
                AND YEAR(created_at)=YEAR(NOW())"
        )->fetchColumn();

        // Last month
        $lastMonth = (float) $db->query(
            "SELECT COALESCE(SUM(amount),0) FROM ft_transactions
              WHERE status='completed'
                AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH))
                AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))"
        )->fetchColumn();

        $sum['month_growth'] = $lastMonth > 0
            ? round(($sum['month_revenue'] - $lastMonth) / $lastMonth * 100, 1)
            : 0;

        // Transaction count + average
        $sum['total_transactions'] = (int) $db->query(
            "SELECT COUNT(*) FROM ft_transactions WHERE status='completed'"
        )->fetchColumn();
        $sum['avg_transaction'] = $sum['total_transactions'] > 0
            ? $sum['total_revenue'] / $sum['total_transactions']
            : 0;

        // Pending payouts
        $row = $db->query(
            "SELECT COALESCE(SUM(amount),0), COUNT(DISTINCT user_id)
               FROM ft_transactions
              WHERE type='payout' AND status='pending'"
        )->fetch(PDO::FETCH_NUM);
        $sum['pending_payouts'] = (float) ($row[0] ?? 0);
        $sum['payout_count']    = (int)   ($row[1] ?? 0);

        return $sum;
    }

    // ── Recent transactions ────────────────────────────────────────────────────
    public static function getRecentTransactions(int $limit = 10): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT t.*, u.name AS user_name
               FROM ft_transactions t
               JOIN ft_users u ON u.user_id = t.user_id
              ORDER BY t.created_at DESC
              LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── All transactions with filters + pagination ─────────────────────────────
    public static function getTransactions(
        string $type   = 'all',
        string $status = 'all',
        string $from   = '',
        string $to     = '',
        int    $page   = 1,
        int    $per    = 20
    ): array {
        $db     = self::db();
        $offset = ($page - 1) * $per;
        $where  = '1=1';
        $params = [];

        if ($type !== 'all') { $where .= ' AND t.type=?';               $params[] = $type; }
        if ($status !== 'all') { $where .= ' AND t.status=?';           $params[] = $status; }
        if ($from !== '')     { $where .= ' AND DATE(t.created_at)>=?'; $params[] = $from; }
        if ($to   !== '')     { $where .= ' AND DATE(t.created_at)<=?'; $params[] = $to; }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ft_transactions t WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT t.*, u.name AS user_name
               FROM ft_transactions t
               JOIN ft_users u ON u.user_id = t.user_id
              WHERE {$where}
              ORDER BY t.created_at DESC
              LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$per, $offset]));
        return ['transactions' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    // ── Monthly revenue (last N months) ───────────────────────────────────────
    public static function getMonthlyRevenue(int $months = 12): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
                    SUM(amount) AS total
               FROM ft_transactions
              WHERE status='completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              GROUP BY DATE_FORMAT(created_at,'%Y-%m')
              ORDER BY month ASC"
        );
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Create transaction ─────────────────────────────────────────────────────
    public static function createTransaction(array $data): bool
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "INSERT INTO ft_transactions
                (user_id, trip_id, type, amount, status, payment_method, transaction_ref, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            (int)   $data['user_id'],
                    $data['trip_id']         ?? null,
                    $data['type'],
            (float) $data['amount'],
                    $data['status']          ?? 'pending',
                    $data['payment_method']  ?? null,
                    $data['transaction_ref'] ?? null,
                    $data['description']     ?? null,
        ]);
    }

    // ── Update transaction status ──────────────────────────────────────────────
    public static function updateStatus(int $txnId, string $status): bool
    {
        $db   = self::db();
        $stmt = $db->prepare("UPDATE ft_transactions SET status=? WHERE transaction_id=?");
        return $stmt->execute([$status, $txnId]);
    }

    // ── Commission calc ────────────────────────────────────────────────────────
    public static function calculateCommission(float $amount): float
    {
        $rate = (float)(self::getSetting('commission_rate') ?? 5);
        return $amount * $rate / 100;
    }

    public static function getPlatformFee(): float
    {
        return (float)(self::getSetting('platform_fee') ?? 100);
    }

    // ── Settings ───────────────────────────────────────────────────────────────
    public static function getSetting(string $key): ?string
    {
        $db   = self::db();
        $stmt = $db->prepare("SELECT setting_value FROM ft_revenue_settings WHERE setting_key=?");
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    public static function updateSetting(string $key, string $value): bool
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "INSERT INTO ft_revenue_settings (setting_key, setting_value)
             VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
        );
        return $stmt->execute([$key, $value]);
    }

    public static function getAllSettings(): array
    {
        $db   = self::db();
        $stmt = $db->query("SELECT setting_key, setting_value FROM ft_revenue_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
        return $out;
    }
}
