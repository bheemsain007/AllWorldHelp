<?php
// admin/delete_user.php
// Task T32 — Permanently delete a user account (GET shows confirm, POST deletes)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/UserManagement.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');

$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$adminId]);
if ($stmt->fetchColumn() !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

$targetId = (int) ($_GET['id'] ?? 0);
if ($targetId <= 0) {
    redirect("users.php");
}

// Cannot delete self
if ($targetId === $adminId) {
    redirect("users.php?error=Cannot+delete+your+own+account");
}

$targetUser = UserManagement::getUserDetails($targetId);
if (!$targetUser) {
    redirect("users.php?error=User+not+found");
}
if (($targetUser['role'] ?? '') === 'admin') {
    redirect("users.php?error=Cannot+delete+admin+accounts");
}

// ── POST: execute deletion ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        redirect("delete_user.php?id={$targetId}");
    }
    $confirm = trim($_POST['confirm_text'] ?? '');
    if ($confirm !== 'DELETE') {
        redirect("delete_user.php?id={$targetId}&error=confirm");
    }
    $ok = UserManagement::deleteUser($targetId);
    redirect("users.php?success=" . ($ok ? urlencode('User deleted permanently') : urlencode('Failed to delete user')));
}

$csrfField = CSRF::getTokenField();
$error     = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User — Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 60px 20px; }
        .box { background: white; border-radius: 12px; padding: 36px; max-width: 480px; width: 100%; box-shadow: 0 4px 16px rgba(0,0,0,.1); }
        .danger-zone { background: #fef2f2; border: 2px solid #dc2626; border-radius: 10px; padding: 20px; margin-bottom: 22px; }
        .danger-zone h1 { color: #991b1b; font-size: 20px; margin-bottom: 8px; }
        .danger-zone p  { color: #7f1d1d; font-size: 14px; line-height: 1.6; }
        .user-name { font-weight: 700; font-size: 17px; color: #0f172a; margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .hint { font-size: 13px; color: #64748b; margin-bottom: 8px; }
        input[type="text"] { width: 100%; padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; letter-spacing: 2px; }
        input[type="text"]:focus { outline: none; border-color: #dc2626; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 11px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
        .btns { display: flex; gap: 10px; margin-top: 20px; }
        .btn-delete  { flex: 1; padding: 13px; background: #7f1d1d; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn-delete:hover { background: #991b1b; }
        .btn-cancel { padding: 13px 20px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-cancel:hover { background: #d1d5db; }
    </style>
</head>
<body>
<div class="box">
    <div class="danger-zone">
        <h1>⚠️ Permanent Deletion</h1>
        <p>This action will <strong>permanently delete</strong> the user account and all associated data. This <strong>cannot be undone</strong>.</p>
    </div>

    <div class="user-name">
        🧑 <?php echo htmlspecialchars($targetUser['name']); ?>
        <div style="font-size:13px;color:#64748b;font-weight:400;"><?php echo htmlspecialchars($targetUser['email']); ?></div>
    </div>

    <?php if ($error === 'confirm'): ?>
        <div class="alert-error">❌ You must type "DELETE" to confirm.</div>
    <?php endif; ?>

    <form method="POST" action="delete_user.php?id=<?php echo $targetId; ?>">
        <?php echo $csrfField; ?>
        <div style="margin-bottom: 16px;">
            <label for="confirm_text">Type <strong>DELETE</strong> to confirm</label>
            <p class="hint">This step cannot be skipped.</p>
            <input type="text" id="confirm_text" name="confirm_text" autocomplete="off" placeholder="DELETE">
        </div>
        <div class="btns">
            <a href="users.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-delete">🗑️ Permanently Delete</button>
        </div>
    </form>
</div>
</body>
</html>
