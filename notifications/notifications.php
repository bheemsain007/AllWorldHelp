<?php
// notifications/notifications.php
// Task T26 — Notification center: grouped unread/read list, mark all read, delete

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Notification.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// ── Handle POST: mark-all-read ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        Notification::markAllAsRead($userId);
    }
    redirect("notifications.php");
}

// ── Fetch Notifications ───────────────────────────────────────────────────────
$notifications = Notification::getNotifications($userId, 60);

// Separate unread / read
$unread = array_values(array_filter($notifications, fn($n) => !(int)$n['is_read']));
$read   = array_values(array_filter($notifications, fn($n) =>  (int)$n['is_read']));

$icons = Notification::getIcons();
$csrfField = CSRF::getTokenField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* ── Nav ── */
        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        /* ── Layout ── */
        .container { max-width: 740px; margin: 30px auto; padding: 0 20px; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 20px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 21px; }
        .btn-mark-all {
            font-size: 13px; color: #1d4ed8; background: #eff6ff;
            border: none; padding: 8px 16px; border-radius: 6px;
            cursor: pointer; font-weight: 600; transition: background 0.15s;
        }
        .btn-mark-all:hover { background: #dbeafe; }

        /* ── Section Title ── */
        .section-title {
            color: #64748b; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.8px;
            margin: 20px 0 10px;
        }

        /* ── Notification Item ── */
        .notif-item {
            background: white; border-radius: 10px;
            margin-bottom: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07);
            display: flex; gap: 14px; align-items: flex-start;
            padding: 16px 18px; text-decoration: none; color: inherit;
            transition: box-shadow 0.15s, background 0.15s;
        }
        .notif-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); background: #fafbfc; }
        .notif-item.unread {
            border-left: 4px solid #1d4ed8; background: #f0f6ff;
        }
        .notif-item.unread:hover { background: #e8f1ff; }

        /* ── Icon ── */
        .notif-icon {
            width: 44px; height: 44px; border-radius: 50%;
            background: #eff6ff; display: flex; align-items: center;
            justify-content: center; font-size: 20px; flex-shrink: 0;
        }

        /* ── Content ── */
        .notif-body { flex: 1; min-width: 0; }
        .notif-title {
            font-weight: 700; color: #1e293b; font-size: 14px; margin-bottom: 3px;
        }
        .notif-message { color: #64748b; font-size: 13px; margin-bottom: 5px; }
        .notif-time    { color: #94a3b8; font-size: 12px; }
        .unread-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #1d4ed8; flex-shrink: 0; margin-top: 6px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 64px 20px;
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
        .empty-state .emoji { font-size: 52px; margin-bottom: 16px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../messages/inbox.php">Messages</a>
        <a href="../connections/requests.php">Connections</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>🔔 Notifications</h1>
            <?php if (!empty($unread)): ?>
                <p style="color:#64748b;font-size:14px;margin-top:4px;">
                    <?php echo count($unread); ?> unread notification<?php echo count($unread) !== 1 ? 's' : ''; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if (!empty($unread)): ?>
            <form method="POST">
                <?php echo $csrfField; ?>
                <button type="submit" name="mark_all_read" value="1" class="btn-mark-all">
                    ✅ Mark all read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="emoji">🔕</div>
            <h2>All caught up!</h2>
            <p>You have no notifications at the moment.</p>
        </div>

    <?php else: ?>

        <!-- Unread notifications -->
        <?php if (!empty($unread)): ?>
            <div class="section-title">Unread (<?php echo count($unread); ?>)</div>
            <?php foreach ($unread as $n): ?>
                <?php $icon = $icons[$n['type']] ?? '🔔'; ?>
                <a href="mark_read.php?id=<?php echo (int) $n['notification_id']; ?>&redirect=<?php echo urlencode($n['link']); ?>"
                   class="notif-item unread">
                    <div class="notif-icon"><?php echo $icon; ?></div>
                    <div class="notif-body">
                        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="notif-time"><?php echo timeAgo($n['created_at']); ?></div>
                    </div>
                    <div class="unread-dot"></div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Read notifications -->
        <?php if (!empty($read)): ?>
            <div class="section-title">Earlier</div>
            <?php foreach ($read as $n): ?>
                <?php $icon = $icons[$n['type']] ?? '🔔'; ?>
                <a href="<?php echo htmlspecialchars($n['link']); ?>"
                   class="notif-item">
                    <div class="notif-icon" style="opacity:0.6;"><?php echo $icon; ?></div>
                    <div class="notif-body">
                        <div class="notif-title" style="font-weight:600;color:#475569;">
                            <?php echo htmlspecialchars($n['title']); ?>
                        </div>
                        <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="notif-time"><?php echo timeAgo($n['created_at']); ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
