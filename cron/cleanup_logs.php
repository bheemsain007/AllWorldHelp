<?php
// cron/cleanup_logs.php
// Task T38 — Auto-delete old log entries
//
// Cron setup:
//   0 3 * * * /usr/bin/php /var/www/html/cron/cleanup_logs.php
//
// Optional override: php cleanup_logs.php --days=60

define('CLI_ONLY', true);
if (PHP_SAPI !== 'cli' && CLI_ONLY) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

// Default retention: 90 days; override via --days=N
$days = 90;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $parsed = (int) substr($arg, 7);
        if ($parsed > 0) $days = $parsed;
    }
}

$ts = date('Y-m-d H:i:s');
echo "{$ts} | Starting log cleanup (retention: {$days} days)…\n";

try {
    $deleted = Logger::deleteOldLogs($days);
    echo date('Y-m-d H:i:s') . " | Deleted {$deleted} log entries older than {$days} days.\n";

    // Also clean up old log files in /logs/ directory
    $logDir = __DIR__ . '/../logs/';
    if (is_dir($logDir)) {
        $cutoff = strtotime("-{$days} days");
        $filesDeleted = 0;
        foreach (glob($logDir . 'app_*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $filesDeleted++;
            }
        }
        if ($filesDeleted > 0) {
            echo date('Y-m-d H:i:s') . " | Deleted {$filesDeleted} old log file(s) from /logs/.\n";
        }
    }

    // Log this cleanup event itself
    Logger::system('Log cleanup completed', [
        'db_entries_deleted' => $deleted,
        'retention_days'     => $days,
    ]);

    echo date('Y-m-d H:i:s') . " | Done.\n";
    exit(0);
} catch (Throwable $e) {
    echo date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
