<?php
// includes/Logger.php
// Task T38 — Centralized application logging class

require_once __DIR__ . '/../config/database.php';

class Logger
{
    // Log levels (ascending severity)
    const DEBUG    = 'debug';
    const INFO     = 'info';
    const WARNING  = 'warning';
    const ERROR    = 'error';
    const CRITICAL = 'critical';

    // Log categories
    const CAT_ERROR    = 'error';
    const CAT_ACTIVITY = 'activity';
    const CAT_SECURITY = 'security';
    const CAT_SYSTEM   = 'system';
    const CAT_EMAIL    = 'email';
    const CAT_PAYMENT  = 'payment';
    const CAT_EXCEPTION= 'exception';

    // File fallback directory
    private static string $logDir = '';

    // ── Core log writer ────────────────────────────────────────────────────────
    public static function log(
        string $level,
        string $category,
        string $message,
        array  $context = []
    ): bool {
        // Sanitise level
        $validLevels = [self::DEBUG, self::INFO, self::WARNING, self::ERROR, self::CRITICAL];
        if (!in_array($level, $validLevels, true)) {
            $level = self::INFO;
        }

        // Current user (if session active)
        $userId = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            require_once __DIR__ . '/SessionManager.php';
            $uid = SessionManager::get('user_id');
            if ($uid) $userId = (int) $uid;
        }

        $ip  = self::getIp();
        $url = ($_SERVER['REQUEST_URI'] ?? null);
        $ctxJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;

        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO ft_logs
                    (level, category, message, context, user_id, ip_address, url, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$level, $category, $message, $ctxJson, $userId, $ip, $url]);

            // Send alert on CRITICAL
            if ($level === self::CRITICAL) {
                self::sendCriticalAlert($message, $context);
            }

            return true;
        } catch (Throwable $e) {
            // Fallback to file so we never lose a log entry
            self::logToFile($level, $category, $message, $context, $e->getMessage());
            return false;
        }
    }

    // ── Convenience level methods ──────────────────────────────────────────────
    public static function debug(string $category, string $message, array $ctx = []): bool
    {
        return self::log(self::DEBUG, $category, $message, $ctx);
    }

    public static function info(string $category, string $message, array $ctx = []): bool
    {
        return self::log(self::INFO, $category, $message, $ctx);
    }

    public static function warning(string $category, string $message, array $ctx = []): bool
    {
        return self::log(self::WARNING, $category, $message, $ctx);
    }

    public static function error(string $category, string $message, array $ctx = []): bool
    {
        return self::log(self::ERROR, $category, $message, $ctx);
    }

    public static function critical(string $category, string $message, array $ctx = []): bool
    {
        return self::log(self::CRITICAL, $category, $message, $ctx);
    }

    // ── Semantic shorthand methods ─────────────────────────────────────────────
    public static function activity(string $message, array $ctx = []): bool
    {
        return self::log(self::INFO, self::CAT_ACTIVITY, $message, $ctx);
    }

    public static function security(string $message, array $ctx = []): bool
    {
        return self::log(self::WARNING, self::CAT_SECURITY, $message, $ctx);
    }

    public static function system(string $message, array $ctx = []): bool
    {
        return self::log(self::INFO, self::CAT_SYSTEM, $message, $ctx);
    }

    // ── Query helpers (used by admin/logs.php) ─────────────────────────────────
    public static function getLogs(
        string $level      = '',
        string $category   = '',
        string $dateFrom   = '',
        string $dateTo     = '',
        string $search     = '',
        int    $userId     = 0,
        int    $limit      = 50,
        int    $offset     = 0
    ): array {
        $db    = Database::getInstance()->getConnection();
        $where = ['1=1'];
        $params = [];

        if ($level)    { $where[] = 'l.level = ?';    $params[] = $level; }
        if ($category) { $where[] = 'l.category = ?'; $params[] = $category; }
        if ($dateFrom) { $where[] = 'DATE(l.created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(l.created_at) <= ?'; $params[] = $dateTo; }
        if ($search)   { $where[] = 'l.message LIKE ?'; $params[] = "%{$search}%"; }
        if ($userId)   { $where[] = 'l.user_id = ?';  $params[] = $userId; }

        $sql = "SELECT l.*, u.name AS user_name
                  FROM ft_logs l
                  LEFT JOIN ft_users u ON u.user_id = l.user_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY l.created_at DESC
                 LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countLogs(
        string $level    = '',
        string $category = '',
        string $dateFrom = '',
        string $dateTo   = '',
        string $search   = '',
        int    $userId   = 0
    ): int {
        $db    = Database::getInstance()->getConnection();
        $where = ['1=1'];
        $params = [];

        if ($level)    { $where[] = 'level = ?';    $params[] = $level; }
        if ($category) { $where[] = 'category = ?'; $params[] = $category; }
        if ($dateFrom) { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }
        if ($search)   { $where[] = 'message LIKE ?'; $params[] = "%{$search}%"; }
        if ($userId)   { $where[] = 'user_id = ?';  $params[] = $userId; }

        $stmt = $db->prepare("SELECT COUNT(*) FROM ft_logs WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $logId): ?array
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT l.*, u.name AS user_name, u.email AS user_email
               FROM ft_logs l
               LEFT JOIN ft_users u ON u.user_id = l.user_id
              WHERE l.log_id = ?"
        );
        $stmt->execute([$logId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getStats(): array
    {
        $db = Database::getInstance()->getConnection();

        // Counts by level
        $byCounts = $db->query(
            "SELECT level, COUNT(*) AS cnt FROM ft_logs GROUP BY level"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        // Today's total
        $today = (int) $db->query(
            "SELECT COUNT(*) FROM ft_logs WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        // Last 7 days
        $week = (int) $db->query(
            "SELECT COUNT(*) FROM ft_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        return [
            'total'    => array_sum($byCounts),
            'by_level' => $byCounts,
            'today'    => $today,
            'week'     => $week,
        ];
    }

    public static function getCategories(): array
    {
        $db   = Database::getInstance()->getConnection();
        $rows = $db->query(
            "SELECT category, COUNT(*) AS cnt FROM ft_logs GROUP BY category ORDER BY cnt DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    public static function deleteOldLogs(int $days = 90): int
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "DELETE FROM ft_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    public static function deleteById(int $logId): bool
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM ft_logs WHERE log_id = ?");
        return $stmt->execute([$logId]);
    }

    // ── Export to CSV ──────────────────────────────────────────────────────────
    public static function exportCsv(array $filters = []): void
    {
        $logs = self::getLogs(
            $filters['level']     ?? '',
            $filters['category']  ?? '',
            $filters['date_from'] ?? '',
            $filters['date_to']   ?? '',
            $filters['search']    ?? '',
            (int) ($filters['user_id'] ?? 0),
            5000,   // max export rows
            0
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="logs_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache');

        $fh = fopen('php://output', 'w');
        fputcsv($fh, ['ID', 'Level', 'Category', 'Message', 'User', 'IP', 'URL', 'Created At']);
        foreach ($logs as $log) {
            fputcsv($fh, [
                $log['log_id'],
                $log['level'],
                $log['category'],
                $log['message'],
                $log['user_name'] ?? 'System',
                $log['ip_address'],
                $log['url'],
                $log['created_at'],
            ]);
        }
        fclose($fh);
    }

    // ── UI helpers ─────────────────────────────────────────────────────────────
    public static function levelBadgeClass(string $level): string
    {
        return match ($level) {
            'debug'    => 'badge-debug',
            'info'     => 'badge-info',
            'warning'  => 'badge-warning',
            'error'    => 'badge-error',
            'critical' => 'badge-critical',
            default    => 'badge-info',
        };
    }

    public static function levelColor(string $level): string
    {
        return match ($level) {
            'debug'    => '#888',
            'info'     => '#0d6efd',
            'warning'  => '#f59e0b',
            'error'    => '#dc3545',
            'critical' => '#7b1c1c',
            default    => '#333',
        };
    }

    public static function timeAgo(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return $diff . 's ago';
        if ($diff < 3600)   return round($diff / 60) . 'm ago';
        if ($diff < 86400)  return round($diff / 3600) . 'h ago';
        return date('M j', strtotime($datetime));
    }

    // ── Internals ──────────────────────────────────────────────────────────────
    private static function getIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return null;
    }

    private static function logToFile(
        string $level,
        string $category,
        string $message,
        array  $context,
        string $dbError = ''
    ): void {
        if (empty(self::$logDir)) {
            self::$logDir = __DIR__ . '/../logs/';
        }
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }

        $file = self::$logDir . 'app_' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] [%s] [%s] %s %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $category,
            $message,
            $context ? json_encode($context) : '',
            $dbError ? " (DB err: {$dbError})" : ''
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function sendCriticalAlert(string $message, array $context): void
    {
        // Attempt email alert — requires Email class to be loaded
        if (!class_exists('Email')) {
            $emailFile = __DIR__ . '/Email.php';
            if (file_exists($emailFile)) require_once $emailFile;
        }
        if (!class_exists('Email')) return;

        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : null;
        if (!$adminEmail) return;

        $body = Email::buildSimpleEmail(
            '🚨 Critical Error — Fellow Traveler',
            '<p><strong>Message:</strong> ' . htmlspecialchars($message) . '</p>'
            . '<pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:12px">'
            . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT))
            . '</pre>',
            'View Logs',
            '/admin/logs.php?level=critical'
        );

        // Use queue to avoid blocking the request
        Email::send($adminEmail, '🚨 Critical Error on Fellow Traveler', $body);
    }
}
