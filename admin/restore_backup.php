<?php
// admin/restore_backup.php
// Task T37 — Database restore from a backup file

require_once '../includes/SessionManager.php';
SessionManager::start();
require_once '../auth/auth_check.php';
require_once '../config/database.php';
require_once '../models/Backup.php';
require_once '../includes/CSRF.php';
require_once '../includes/helpers.php';

// Admin guard
$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare('SELECT role FROM ft_users WHERE user_id = ?');
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect('../dashboard.php?error=unauthorized');

$backupId = (int) ($_GET['id'] ?? 0);
if (!$backupId) redirect('backup.php');

$backup = Backup::findById($backupId);
if (!$backup) redirect('backup.php?error=not_found');

// ── POST: execute restore ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        redirect('backup.php?error=csrf');
    }

    // Safety snapshot before restoring
    Backup::create('pre_restore', $adminId);

    // Restore
    $ok = Backup::restore($backupId);

    if ($ok) {
        redirect('backup.php?success=restore_complete');
    } else {
        redirect('backup.php?error=restore_failed');
    }
}

// ── GET: confirmation page ─────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restore Database — Fellow Traveler Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; color: #333;
       display: flex; align-items: center; justify-content: center; min-height: 100vh; }

.confirm-card {
    background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.12);
    padding: 40px 48px; max-width: 520px; width: 95%; text-align: center;
}

.icon     { font-size: 4rem; margin-bottom: 16px; }
.title    { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; margin-bottom: 10px; }
.subtitle { color: #666; font-size: 0.95rem; margin-bottom: 28px; line-height: 1.5; }

.detail-box {
    background: #f8f9fa; border-radius: 10px; padding: 18px 22px;
    text-align: left; margin-bottom: 28px;
}
.detail-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.88rem; }
.detail-row:not(:last-child) { border-bottom: 1px solid #eee; }
.detail-label { color: #888; font-weight: 600; }
.detail-value { color: #333; font-family: monospace; word-break: break-all; max-width: 60%; text-align: right; }

.warning-box {
    background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px;
    padding: 14px 18px; margin-bottom: 28px; font-size: 0.88rem; color: #856404;
    text-align: left; line-height: 1.5;
}
.warning-box strong { display: block; margin-bottom: 4px; font-size: 0.95rem; }

.actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn { padding: 11px 24px; border-radius: 9px; font-size: 0.9rem; font-weight: 600;
       cursor: pointer; text-decoration: none; border: none; display: inline-flex;
       align-items: center; gap: 7px; transition: opacity .2s; }
.btn:hover { opacity: .85; }
.btn-danger     { background: #dc3545; color: #fff; }
.btn-secondary  { background: #6c757d; color: #fff; }
</style>
</head>
<body>
<div class="confirm-card">
    <div class="icon">⚠️</div>
    <div class="title">Restore Database?</div>
    <div class="subtitle">
        You are about to restore the database from the backup below.
        <strong>All current data will be permanently overwritten.</strong>
        A safety backup will be created automatically before proceeding.
    </div>

    <div class="detail-box">
        <div class="detail-row">
            <span class="detail-label">Filename</span>
            <span class="detail-value"><?= htmlspecialchars($backup['filename']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Type</span>
            <span class="detail-value"><?= Backup::typeLabel($backup['type']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Size</span>
            <span class="detail-value"><?= Backup::formatSize((int) $backup['size']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Created</span>
            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($backup['created_at'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Created By</span>
            <span class="detail-value">
                <?= $backup['created_by_name']
                    ? htmlspecialchars($backup['created_by_name'])
                    : 'System (cron)' ?>
            </span>
        </div>
    </div>

    <div class="warning-box">
        <strong>🚨 This action is irreversible!</strong>
        All current users, trips, messages, and other data will be replaced
        with the data from this backup file. Ensure you understand the consequences
        before proceeding. A <em>pre-restore</em> snapshot will be saved first.
    </div>

    <div class="actions">
        <form method="POST" action="restore_backup.php?id=<?= $backupId ?>">
            <?= CSRF::getTokenField() ?>
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('FINAL CONFIRMATION: Restore database from this backup? All current data will be overwritten.')">
                ♻ Yes, Restore Now
            </button>
        </form>
        <a href="backup.php" class="btn btn-secondary">✕ Cancel</a>
    </div>
</div>
</body>
</html>
