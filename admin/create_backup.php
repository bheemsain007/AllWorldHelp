<?php
// admin/create_backup.php
// Task T37 — Manual backup creation handler

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

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup.php');
}

// CSRF check
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect('backup.php?error=csrf');
}

// Create manual backup
$backupId = Backup::create('manual', $adminId);

if ($backupId) {
    redirect('backup.php?success=backup_created');
} else {
    redirect('backup.php?error=backup_failed');
}
