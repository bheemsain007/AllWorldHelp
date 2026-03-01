<?php
// cron/process_email_queue.php
// Task T35 — Processes pending emails from ft_email_queue.
// Run via cron: */5 * * * * php /var/www/html/cron/process_email_queue.php

define('CLI_ONLY', true);
if (PHP_SAPI !== 'cli' && CLI_ONLY) {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Email.php';

const BATCH   = 20;   // emails per run
const MAX_TRY = 3;    // max attempts before marking failed

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare(
    "SELECT * FROM ft_email_queue
      WHERE status = 'pending' AND attempts < ?
      ORDER BY created_at ASC
      LIMIT ?"
);
$stmt->execute([MAX_TRY, BATCH]);
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent = $failed = 0;

foreach ($queue as $item) {
    // Increment attempt counter first
    $db->prepare("UPDATE ft_email_queue SET attempts=attempts+1 WHERE queue_id=?")->execute([$item['queue_id']]);

    $ok = Email::sendNow(
        $item['to_email'],
        $item['subject'],
        $item['body'],
        (bool) $item['is_html']
    );

    if ($ok) {
        $db->prepare(
            "UPDATE ft_email_queue SET status='sent', sent_at=NOW() WHERE queue_id=?"
        )->execute([$item['queue_id']]);
        $sent++;
    } else {
        if ((int)$item['attempts'] + 1 >= MAX_TRY) {
            $db->prepare(
                "UPDATE ft_email_queue SET status='failed' WHERE queue_id=?"
            )->execute([$item['queue_id']]);
        }
        $failed++;
    }
}

echo date('Y-m-d H:i:s') . " | Queue processed: {$sent} sent, {$failed} failed.\n";
