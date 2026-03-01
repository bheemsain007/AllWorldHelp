<?php
// admin/user_details.php
// Task T32 — Detailed admin view of a single user

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/UserManagement.php";
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

$user     = UserManagement::getUserDetails($targetId);
if (!$user) {
    redirect("users.php?error=User+not+found");
}
$stats    = UserManagement::getUserStats($targetId);
$activity = UserManagement::getUserActivity($targetId, 15);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> — Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; }
        nav { background: #0f172a; color: white; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        nav a:hover { color: white; }

        .page { max-width: 960px; margin: 28px auto; padding: 0 20px; }
        .back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 14px; margin-bottom: 20px; }
        .back:hover { color: #0f172a; }

        .profile-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #7c3aed 100%);
            color: white; padding: 28px 32px; border-radius: 14px; margin-bottom: 22px;
            display: flex; align-items: center; gap: 22px;
        }
        .profile-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,.4); }
        .profile-header h1 { font-size: 24px; margin-bottom: 4px; }
        .profile-header .meta { opacity: 0.85; font-size: 14px; }
        .badge-status {
            display: inline-block; margin-top: 8px; padding: 4px 12px;
            border-radius: 14px; font-size: 13px; font-weight: 700;
        }
        .blocked-badge { background: rgba(220,38,38,.3); }
        .active-badge  { background: rgba(22,163,74,.3); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .stat-card { background: white; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 4px rgba(0,0,0,.07); text-align: center; }
        .stat-num  { font-size: 28px; font-weight: 800; color: #1d4ed8; }
        .stat-lbl  { font-size: 12px; color: #64748b; margin-top: 4px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .card { background: white; border-radius: 10px; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,.07); margin-bottom: 18px; }
        .card h2 { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 16px; }

        .detail-row { display: flex; gap: 8px; margin-bottom: 10px; font-size: 14px; }
        .dl { color: #64748b; min-width: 140px; }
        .dv { color: #0f172a; font-weight: 600; }

        .act-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .act-item:last-child { border-bottom: none; }
        .act-icon { font-size: 18px; flex-shrink: 0; }
        .act-text { font-size: 14px; color: #1e293b; }
        .act-time { font-size: 12px; color: #94a3b8; }

        .admin-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 11px 22px; border-radius: 8px; font-size: 14px; font-weight: 700;
            text-decoration: none; border: none; cursor: pointer; transition: opacity .15s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-block   { background: #dc2626; color: white; }
        .btn-unblock { background: #16a34a; color: white; }
        .btn-delete  { background: #7f1d1d; color: white; }
        .btn-back-sm { background: #e5e7eb; color: #374151; }

        .blocked-info { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 14px 18px; margin-bottom: 18px; }
        .blocked-info strong { color: #991b1b; }

        @media (max-width: 700px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-2     { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <a href="users.php" class="back">← Back to Users</a>

    <!-- Profile header -->
    <div class="profile-header">
        <img src="<?php echo htmlspecialchars($user['profile_photo'] ?? '../images/default-avatar.png'); ?>"
             class="profile-photo" alt="">
        <div>
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <div class="meta"><?php echo htmlspecialchars($user['email']); ?></div>
            <div class="meta">Member since <?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
            <span class="badge-status <?php echo $user['is_blocked'] ? 'blocked-badge' : 'active-badge'; ?>">
                <?php echo $user['is_blocked'] ? '🚫 Blocked' : '✅ Active'; ?>
            </span>
        </div>
    </div>

    <!-- Blocked info -->
    <?php if ($user['is_blocked']): ?>
    <div class="blocked-info">
        <strong>⚠️ This user is blocked.</strong>
        Blocked on <?php echo date('d M Y, H:i', strtotime($user['blocked_at'] ?? 'now')); ?>.
        <?php if (!empty($user['blocked_reason'])): ?>
            Reason: <?php echo htmlspecialchars($user['blocked_reason']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-num"><?php echo $stats['trips_organized']; ?></div><div class="stat-lbl">Trips Organized</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $stats['trips_joined']; ?></div><div class="stat-lbl">Trips Joined</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $stats['connections']; ?></div><div class="stat-lbl">Connections</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $stats['reviews']; ?></div><div class="stat-lbl">Reviews Written</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $stats['messages_sent']; ?></div><div class="stat-lbl">Messages Sent</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $stats['reports_filed']; ?></div><div class="stat-lbl">Reports Filed</div></div>
        <div class="stat-card"><div class="stat-num" style="color:<?php echo $stats['times_reported'] > 2 ? '#dc2626' : '#1d4ed8'; ?>;"><?php echo $stats['times_reported']; ?></div><div class="stat-lbl">Times Reported</div></div>
        <div class="stat-card"><div class="stat-num" style="color:<?php echo ($user['role'] ?? '') === 'admin' ? '#7c3aed' : '#0f172a'; ?>;"><?php echo ucfirst($user['role'] ?? 'user'); ?></div><div class="stat-lbl">Role</div></div>
    </div>

    <div class="grid-2">
        <!-- Account info -->
        <div class="card">
            <h2>📋 Account Information</h2>
            <div class="detail-row"><span class="dl">User ID</span><span class="dv">#<?php echo (int)$user['user_id']; ?></span></div>
            <div class="detail-row"><span class="dl">Full Name</span><span class="dv"><?php echo htmlspecialchars($user['name']); ?></span></div>
            <div class="detail-row"><span class="dl">Email</span><span class="dv"><?php echo htmlspecialchars($user['email']); ?></span></div>
            <?php if (!empty($user['bio'])): ?>
            <div class="detail-row"><span class="dl">Bio</span><span class="dv"><?php echo htmlspecialchars(mb_strimwidth($user['bio'], 0, 120, '…')); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($user['location'])): ?>
            <div class="detail-row"><span class="dl">Location</span><span class="dv"><?php echo htmlspecialchars($user['location']); ?></span></div>
            <?php endif; ?>
            <div class="detail-row"><span class="dl">Joined</span><span class="dv"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span></div>
        </div>

        <!-- Admin actions -->
        <div class="card">
            <h2>⚙️ Admin Actions</h2>
            <div class="admin-actions">
                <?php if ($user['is_blocked']): ?>
                    <a href="block_user.php?id=<?php echo (int)$user['user_id']; ?>&action=unblock"
                       class="btn btn-unblock"
                       onclick="return confirm('Unblock this user?')">✅ Unblock User</a>
                <?php elseif (($user['role'] ?? '') !== 'admin'): ?>
                    <a href="block_user.php?id=<?php echo (int)$user['user_id']; ?>&action=block"
                       class="btn btn-block"
                       onclick="return confirm('Block this user? They will not be able to log in.')">🚫 Block User</a>
                <?php endif; ?>

                <?php if (($user['role'] ?? '') !== 'admin'): ?>
                    <a href="delete_user.php?id=<?php echo (int)$user['user_id']; ?>"
                       class="btn btn-delete"
                       onclick="return confirm('PERMANENTLY delete this user and all their data? This cannot be undone.')">🗑️ Delete Account</a>
                <?php endif; ?>

                <a href="../moderation/report_form.php?type=user&id=<?php echo (int)$user['user_id']; ?>"
                   class="btn" style="background:#f59e0b;color:white;">🚨 File Report</a>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="card">
        <h2>🕓 Recent Activity</h2>
        <?php if (empty($activity)): ?>
            <p style="color:#94a3b8;font-size:14px;">No activity recorded.</p>
        <?php else: ?>
            <?php foreach ($activity as $item): ?>
            <div class="act-item">
                <span class="act-icon"><?php echo $item['icon']; ?></span>
                <div>
                    <div class="act-text"><?php echo $item['text']; ?></div>
                    <div class="act-time"><?php echo timeAgo($item['at']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
