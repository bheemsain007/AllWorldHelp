<?php
// admin/download_backup.php
// Task T37 — Stream backup file to browser for download

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

// CSRF token passed as GET param (read-only action, token still required for CSRF)
if (!CSRF::validateToken($_GET['csrf'] ?? '')) {
    redirect('backup.php?error=csrf');
}

$backupId = (int) ($_GET['id'] ?? 0);
if (!$backupId) redirect('backup.php');

$ok = Backup::download($backupId);

if (!$ok) {
    redirect('backup.php?error=not_found');
}
// download() sends the file and headers; execution ends here naturally
