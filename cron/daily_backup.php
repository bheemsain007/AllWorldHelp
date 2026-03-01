<?php
// cron/daily_backup.php
// Task T37 — Automated daily and weekly database backup
//
// Cron setup:
//   Daily backup at 2 AM:
//     0 2 * * * /usr/bin/php /var/www/html/cron/daily_backup.php
//   Weekly backup on Sunday at 3 AM:
//     0 3 * * 0 /usr/bin/php /var/www/html/cron/daily_backup.php --type=weekly

define('CLI_ONLY', true);
if (PHP_SAPI !== 'cli' && CLI_ONLY) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Backup.php';

// Determine backup type from CLI argument
$type = 'auto';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--type=')) {
        $raw = substr($arg, 7);
        if (in_array($raw, ['auto', 'weekly'], true)) {
            $type = $raw;
        }
    }
}

$ts = date('Y-m-d H:i:s');
echo "{$ts} | Starting {$type} backup...\n";

// Create backup (null = system/cron, no user_id)
$backupId = Backup::create($type, null);

if ($backupId) {
    echo date('Y-m-d H:i:s') . " | Backup created successfully. ID: {$backupId}\n";

    // Purge backups older than 30 days (auto/daily only; keep weeklies longer)
    $deleted = Backup::deleteOldBackups(30);
    if ($deleted > 0) {
        echo date('Y-m-d H:i:s') . " | Deleted {$deleted} old backup(s) (>30 days).\n";
    }

    // Optional email notification — uncomment if Email class is available
    // require_once __DIR__ . '/../includes/Email.php';
    // Email::send(
    //     defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com',
    //     'Backup Complete — Fellow Traveler',
    //     Email::buildSimpleEmail(
    //         "✅ Backup Successful",
    //         "<p>Your {$type} backup was created successfully (Backup ID #{$backupId}).</p>",
    //         'View Backups',
    //         '/admin/backup.php'
    //     )
    // );

    echo date('Y-m-d H:i:s') . " | Done.\n";
    exit(0);
} else {
    echo date('Y-m-d H:i:s') . " | ERROR: Backup failed! Check mysqldump availability and disk space.\n";

    // Optional failure alert email — uncomment when Email class available
    // require_once __DIR__ . '/../includes/Email.php';
    // Email::send(
    //     defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com',
    //     '🚨 Backup FAILED — Fellow Traveler',
    //     Email::buildSimpleEmail(
    //         "❌ Backup Failed",
    //         "<p>The automated {$type} backup failed. Please check your server logs immediately.</p>",
    //         'Check Logs',
    //         '/admin/backup.php'
    //     )
    // );

    exit(1);
}
