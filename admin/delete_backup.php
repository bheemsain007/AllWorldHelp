<?php
// admin/delete_backup.php
// Task T37 — Delete a backup file and its DB record

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup.php');
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect('backup.php?error=csrf');
}

$backupId = (int) ($_POST['backup_id'] ?? 0);
if (!$backupId) redirect('backup.php');

$ok = Backup::delete($backupId);
redirect('backup.php?' . ($ok ? 'success=deleted' : 'error=delete_failed'));
