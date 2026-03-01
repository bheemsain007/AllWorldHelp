<?php
// admin/block_user.php
// Task T32 — Block / unblock a user (GET for unblock, GET + POST for block with reason)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/UserManagement.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');

$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$adminId]);
if ($stmt->fetchColumn() !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

$targetId = (int) ($_GET['id'] ?? 0);
$action   = $_GET['action'] ?? '';

if ($targetId <= 0 || !in_array($action, ['block', 'unblock'], true)) {
    redirect("users.php?error=Invalid+action");
}

// Prevent blocking self or another admin
$targetUser = UserManagement::getUserDetails($targetId);
if (!$targetUser) {
    redirect("users.php?error=User+not+found");
}
if (($targetUser['role'] ?? '') === 'admin') {
    redirect("users.php?error=Cannot+modify+admin+accounts");
}

// ── UNBLOCK (simple GET action) ────────────────────────────────────────────────
if ($action === 'unblock') {
    $ok = UserManagement::unblockUser($targetId);
    redirect("users.php?success=" . ($ok ? 'User+unblocked+successfully' : 'Failed+to+unblock+user'));
}

// ── BLOCK (POST with reason) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        redirect("block_user.php?id={$targetId}&action=block&error=csrf");
    }
    $reason = InputValidator::sanitizeString($_POST['reason'] ?? '');
    if (mb_strlen($reason) < 3) {
        redirect("block_user.php?id={$targetId}&action=block&error=reason");
    }
    $ok = UserManagement::blockUser($targetId, $reason);
    redirect("users.php?success=" . ($ok ? 'User+blocked+successfully' : 'Failed+to+block+user'));
}

// ── GET: show block reason form ────────────────────────────────────────────────
$csrfField = CSRF::getTokenField();
$error     = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block User — Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 60px 20px; }
        .box { background: white; border-radius: 12px; padding: 36px; max-width: 480px; width: 100%; box-shadow: 0 4px 16px rgba(0,0,0,.1); }
        h1 { color: #dc2626; font-size: 22px; margin-bottom: 8px; }
        .sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; min-height: 90px; }
        textarea:focus { outline: none; border-color: #dc2626; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 11px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .btns { display: flex; gap: 10px; margin-top: 20px; }
        .btn-confirm { flex: 1; padding: 13px; background: #dc2626; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn-confirm:hover { background: #b91c1c; }
        .btn-cancel { padding: 13px 20px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-cancel:hover { background: #d1d5db; }
    </style>
</head>
<body>
<div class="box">
    <h1>🚫 Block User</h1>
    <p class="sub">Blocking <strong><?php echo htmlspecialchars($targetUser['name']); ?></strong> will prevent them from logging in until unblocked.</p>

    <?php if ($error === 'reason'): ?>
        <div class="alert-error">❌ Please enter a reason (at least 3 characters).</div>
    <?php elseif ($error === 'csrf'): ?>
        <div class="alert-error">❌ Security token invalid. Please try again.</div>
    <?php endif; ?>

    <form method="POST" action="block_user.php?id=<?php echo $targetId; ?>&action=block">
        <?php echo $csrfField; ?>
        <div style="margin-bottom:16px;">
            <label for="reason">Reason for Blocking <span style="color:#dc2626;">*</span></label>
            <textarea id="reason" name="reason" maxlength="500" placeholder="e.g. Multiple harassment reports, spam behaviour…" required></textarea>
        </div>
        <div class="btns">
            <a href="users.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-confirm">🚫 Block User</button>
        </div>
    </form>
</div>
</body>
</html>
