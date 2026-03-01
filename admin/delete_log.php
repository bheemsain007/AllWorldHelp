<?php
// admin/delete_log.php
// Task T38 — Delete a single log entry

require_once '../includes/SessionManager.php';
SessionManager::start();
require_once '../auth/auth_check.php';
require_once '../config/database.php';
require_once '../includes/Logger.php';
require_once '../includes/CSRF.php';
require_once '../includes/helpers.php';

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare('SELECT role FROM ft_users WHERE user_id = ?');
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect('../dashboard.php?error=unauthorized');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('logs.php');
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) redirect('logs.php?error=csrf');

$logId = (int) ($_POST['log_id'] ?? 0);
if ($logId) Logger::deleteById($logId);

redirect('logs.php?success=cleared');
