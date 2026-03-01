<?php
// admin/update_settings.php
// Task T36 — Settings POST handler

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Settings.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect("../dashboard.php?error=unauthorized");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("settings.php");
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("settings.php?error=csrf");
}

$category = trim($_POST['category'] ?? '');
if (empty($category)) {
    redirect("settings.php?error=no_category");
}

// Get known setting keys for this category to avoid arbitrary key injection
$settings = Settings::getByCategory($category);
$keys     = array_column($settings, 'setting_key');

// Boolean fields default to 0 if not present in POST (unchecked checkboxes)
$updates = [];
foreach ($keys as $key) {
    $type = '';
    foreach ($settings as $s2) {
        if ($s2['setting_key'] === $key) { $type = $s2['setting_type']; break; }
    }

    if ($type === 'boolean') {
        $updates[$key] = isset($_POST[$key]) ? '1' : '0';
    } else {
        $updates[$key] = trim((string) ($_POST[$key] ?? ''));
    }
}

$ok = Settings::bulkUpdate($updates, $adminId);
redirect("settings.php?category=" . urlencode($category) . ($ok ? '&success=1' : '&error=save_failed'));
