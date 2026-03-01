<?php
// messages/inbox.php
// Task T19 — Inbox: list of conversations with last message + unread badge

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Message.php";
require_once "../models/Connection.php";

$userId        = (int) SessionManager::get('user_id');
$conversations = Message::getConversations($userId);
$unreadCount   = Message::getUnreadCount($userId);
$pendingConns  = Connection::countPending($userId);

// ── Flash Messages ────────────────────────────────────────────────────────────
$flashMap = [
    'not_connected' => ['❌ You can only message your connections.', 'error'],
    'failed'        => ['❌ Something went wrong. Please try again.', 'error'],
];
$flash     = null;
$flashType = null;
$eKey      = $_GET['error'] ?? '';
if ($eKey && isset($flashMap[$eKey])) {
    [$flash, $flashType] = $flashMap[$eKey];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Fellow Traveler</title>
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
        .badge-count {
            background: #ef4444; color: white; font-size: 11px;
            border-radius: 10px; padding: 1px 6px; margin-left: 4px;
            font-weight: bold; vertical-align: middle;
        }

        /* ── Layout ── */
        .container { max-width: 860px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 24px 28px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.1); margin-bottom: 20px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 24px; }
        .header-card p  { color: #64748b; margin-top: 5px; font-size: 15px; }

        /* ── Conversation Row ── */
        .conversation {
            background: white; padding: 16px 20px; margin-bottom: 8px;
            border-radius: 10px; display: flex; gap: 16px; align-items: center;
            text-decoration: none; color: inherit;
            box-shadow: 0 2px 4px rgba(0,0,0,.06);
            transition: background 0.15s, box-shadow 0.15s;
            border-left: 4px solid transparent;
        }
        .conversation:hover { background: #f8fafc; box-shadow: 0 4px 10px rgba(0,0,0,.1); }
        .conversation.unread { border-left-color: #1d4ed8; }
        .conversation img {
            width: 58px; height: 58px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb; flex-shrink: 0;
        }
        .conv-info { flex: 1; min-width: 0; }
        .conv-info h3 {
            color: #1d4ed8; font-size: 16px; margin-bottom: 4px;
        }
        .conv-info .last-msg {
            color: #64748b; font-size: 14px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .conversation.unread .last-msg { font-weight: 700; color: #1e293b; }
        .conv-meta {
            text-align: right; font-size: 12px; color: #94a3b8; flex-shrink: 0;
        }
        .unread-badge {
            display: inline-block; background: #1d4ed8; color: white;
            padding: 3px 8px; border-radius: 12px;
            font-size: 12px; font-weight: 700; margin-top: 6px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 70px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 52px; margin-bottom: 16px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 10px; }
        .empty-state p  { color: #64748b; margin-bottom: 20px; }
        .empty-state a  {
            display: inline-block; padding: 12px 26px;
            background: #1d4ed8; color: white;
            border-radius: 8px; text-decoration: none; font-weight: 600;
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="inbox.php">
            Messages
            <?php if ($unreadCount > 0): ?>
                <span class="badge-count"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="../connections/connections.php">Connections</a>
        <a href="../connections/requests.php">
            Requests
            <?php if ($pendingConns > 0): ?>
                <span class="badge-count"><?php echo $pendingConns; ?></span>
            <?php endif; ?>
        </a>
        <a href="../profile/my_profile.php">My Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <h1>💬 Messages</h1>
        <?php if ($unreadCount > 0): ?>
            <p>You have <strong><?php echo $unreadCount; ?></strong> unread message<?php echo $unreadCount !== 1 ? 's' : ''; ?></p>
        <?php else: ?>
            <p>Your conversations</p>
        <?php endif; ?>
    </div>

    <!-- Conversation List -->
    <?php if (empty($conversations)): ?>
        <div class="empty-state">
            <div class="emoji">💬</div>
            <h2>No messages yet</h2>
            <p>Start a conversation with one of your connections!</p>
            <a href="../connections/connections.php">Go to My Connections</a>
        </div>

    <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
            <?php
                $isUnread = (int) $conv['unread_count'] > 0;
                // Timestamp formatting: today → time, older → date
                $msgTime  = strtotime($conv['last_message_time']);
                $isToday  = date('Y-m-d', $msgTime) === date('Y-m-d');
                $timeStr  = $isToday ? date('g:i A', $msgTime) : date('M j', $msgTime);
            ?>
            <a href="chat.php?user_id=<?php echo (int) $conv['other_user_id']; ?>"
               class="conversation <?php echo $isUnread ? 'unread' : ''; ?>">

                <img
                    src="<?php echo !empty($conv['profile_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($conv['profile_photo'])
                        : '../assets/default-avatar.png'; ?>"
                    alt="<?php echo htmlspecialchars($conv['name']); ?>"
                >

                <div class="conv-info">
                    <h3><?php echo htmlspecialchars($conv['name']); ?></h3>
                    <div class="last-msg">
                        <?php
                            $preview = $conv['last_message'];
                            if (mb_strlen($preview) > 55) {
                                $preview = mb_substr($preview, 0, 55) . '…';
                            }
                            echo htmlspecialchars($preview);
                        ?>
                    </div>
                </div>

                <div class="conv-meta">
                    <div><?php echo $timeStr; ?></div>
                    <?php if ($isUnread): ?>
                        <span class="unread-badge"><?php echo (int) $conv['unread_count']; ?></span>
                    <?php endif; ?>
                </div>

            </a>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
