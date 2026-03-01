<?php
// messages/chat.php
// Task T19 — WhatsApp-style 1-on-1 chat interface

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Message.php";
require_once "../models/User.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId      = (int) SessionManager::get('user_id');
$otherUserId = (int) ($_GET['user_id'] ?? 0);

if ($otherUserId <= 0) {
    redirect("inbox.php");
}

// Cannot chat with yourself
if ($otherUserId === $userId) {
    redirect("inbox.php");
}

// ── Fetch Other User ──────────────────────────────────────────────────────────
$otherUser = User::findById($otherUserId);
if (!$otherUser) {
    redirect("inbox.php?error=not_found");
}

// ── Permission: Only connected users can message ──────────────────────────────
if (!Connection::areConnected($userId, $otherUserId)) {
    redirect("inbox.php?error=not_connected");
}

// ── Fetch Message History & Mark As Read ─────────────────────────────────────
$messages = Message::getConversation($userId, $otherUserId);
Message::markAsRead($userId, $otherUserId);   // mark incoming messages as read

$unreadCount  = Message::getUnreadCount($userId);
$pendingConns = Connection::countPending($userId);

// ── Flash Messages ────────────────────────────────────────────────────────────
$flashMap = [
    'not_connected'   => '❌ You can only message your connections.',
    'invalid_message' => '❌ Message cannot be empty or exceed 1000 characters.',
    'failed'          => '❌ Failed to send message. Please try again.',
    'csrf'            => '❌ Security token invalid.',
];
$flash = null;
$eKey  = $_GET['error'] ?? '';
if ($eKey && isset($flashMap[$eKey])) {
    $flash = $flashMap[$eKey];
}

$csrfField = CSRF::getTokenField();
$otherName = htmlspecialchars($otherUser['name']);
$otherPhoto = !empty($otherUser['profile_photo'])
    ? '../uploads/profiles/' . htmlspecialchars($otherUser['profile_photo'])
    : '../assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo $otherName; ?> — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%; font-family: Arial, sans-serif;
            background: #f5f5f5; overflow: hidden;
        }

        /* ── Full-height chat layout ── */
        .chat-wrapper {
            display: flex; flex-direction: column;
            height: 100vh; max-width: 900px;
            margin: 0 auto;
        }

        /* ── Chat Header ── */
        .chat-header {
            background: #1d4ed8; color: white;
            padding: 14px 20px; display: flex; align-items: center; gap: 14px;
            flex-shrink: 0;
        }
        .chat-header .back {
            color: white; text-decoration: none; font-size: 20px; margin-right: 4px;
        }
        .chat-header img {
            width: 42px; height: 42px; border-radius: 50%;
            object-fit: cover; border: 2px solid rgba(255,255,255,.4);
        }
        .chat-header-info { flex: 1; }
        .chat-header-info h2 { font-size: 17px; }
        .chat-header-info a {
            color: rgba(255,255,255,.75); text-decoration: none; font-size: 13px;
        }
        .chat-header-info a:hover { text-decoration: underline; }

        /* ── Flash Bar ── */
        .flash-bar {
            background: #fee2e2; color: #b91c1c;
            padding: 10px 20px; font-size: 14px; flex-shrink: 0;
        }

        /* ── Messages Area ── */
        .messages-area {
            flex: 1; overflow-y: auto; padding: 20px 20px 10px;
            background: #e5ddd5;
            display: flex; flex-direction: column; gap: 10px;
        }

        /* ── Date Separator ── */
        .date-sep {
            text-align: center; font-size: 12px; color: #667781;
            background: rgba(255,255,255,.6); display: inline-block;
            padding: 4px 14px; border-radius: 12px;
            margin: 6px auto;
        }

        /* ── Individual Message ── */
        .msg-row { display: flex; align-items: flex-end; gap: 8px; }
        .msg-row.sent  { flex-direction: row-reverse; }

        .bubble {
            max-width: 65%; padding: 10px 14px; border-radius: 12px;
            word-break: break-word; font-size: 15px; line-height: 1.4;
        }
        .msg-row.received .bubble {
            background: white; border-bottom-left-radius: 2px;
        }
        .msg-row.sent .bubble {
            background: #dcf8c6; border-bottom-right-radius: 2px;
        }
        .bubble .msg-time {
            font-size: 11px; color: #667781;
            text-align: right; margin-top: 4px;
        }

        /* ── Empty Chat ── */
        .empty-chat {
            text-align: center; color: #667781;
            margin: auto; padding: 20px;
        }

        /* ── Send Form ── */
        .send-form {
            background: white; padding: 12px 16px;
            display: flex; gap: 10px; align-items: center; flex-shrink: 0;
            border-top: 1px solid #e5e7eb;
        }
        .send-form textarea {
            flex: 1; padding: 10px 14px; border: 1px solid #d1d5db;
            border-radius: 22px; font-size: 15px; font-family: inherit;
            resize: none; height: 42px; max-height: 120px; overflow-y: auto;
            line-height: 1.4;
        }
        .send-form textarea:focus { outline: none; border-color: #1d4ed8; }
        .send-btn {
            background: #1d4ed8; color: white; border: none;
            width: 44px; height: 44px; border-radius: 50%;
            font-size: 20px; cursor: pointer; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .send-btn:hover { background: #1e40af; }
        .char-count { font-size: 12px; color: #94a3b8; flex-shrink: 0; }
        .char-count.warn { color: #f59e0b; }
        .char-count.over { color: #dc2626; }
    </style>
</head>
<body>

<div class="chat-wrapper">

    <!-- Chat Header -->
    <div class="chat-header">
        <a href="inbox.php" class="back">←</a>
        <img src="<?php echo $otherPhoto; ?>" alt="<?php echo $otherName; ?>">
        <div class="chat-header-info">
            <h2><?php echo $otherName; ?></h2>
            <a href="../profile/public_profile.php?user_id=<?php echo $otherUserId; ?>">
                View profile
            </a>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div class="flash-bar"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Messages Area -->
    <div class="messages-area" id="messagesArea">

        <?php if (empty($messages)): ?>
            <div class="empty-chat">
                No messages yet. Say hello! 👋
            </div>

        <?php else: ?>
            <?php
            $prevDate = null;
            foreach ($messages as $msg):
                $msgDate  = date('Y-m-d', strtotime($msg['created_at']));
                $isSent   = (int) $msg['sender_id'] === $userId;
                $rowClass = $isSent ? 'sent' : 'received';
                $timeStr  = date('g:i A', strtotime($msg['created_at']));

                // Date separator
                if ($msgDate !== $prevDate):
                    $label = match(true) {
                        $msgDate === date('Y-m-d')                        => 'Today',
                        $msgDate === date('Y-m-d', strtotime('-1 day'))   => 'Yesterday',
                        default                                            => date('M j, Y', strtotime($msgDate)),
                    };
            ?>
                    <div style="display:flex;justify-content:center;">
                        <span class="date-sep"><?php echo $label; ?></span>
                    </div>
            <?php
                    $prevDate = $msgDate;
                endif;
            ?>

            <div class="msg-row <?php echo $rowClass; ?>">
                <div class="bubble">
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    <div class="msg-time"><?php echo $timeStr; ?></div>
                </div>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /.messages-area -->

    <!-- Send Form -->
    <form class="send-form" action="send_message.php" method="POST" id="sendForm">
        <?php echo $csrfField; ?>
        <input type="hidden" name="receiver_id" value="<?php echo $otherUserId; ?>">
        <textarea
            id="msgInput"
            name="message"
            placeholder="Type a message…"
            maxlength="1000"
            required
            autocomplete="off"
            rows="1"
        ></textarea>
        <span class="char-count" id="charCount">1000</span>
        <button type="submit" class="send-btn" title="Send">➤</button>
    </form>

</div><!-- /.chat-wrapper -->

<script>
    // ── Auto-scroll to bottom ──────────────────────────────────────────────────
    const area = document.getElementById('messagesArea');
    area.scrollTop = area.scrollHeight;

    // ── Character counter ──────────────────────────────────────────────────────
    const input     = document.getElementById('msgInput');
    const charCount = document.getElementById('charCount');
    const MAX       = 1000;

    function updateCount() {
        const remaining = MAX - input.value.length;
        charCount.textContent = remaining;
        charCount.className = 'char-count';
        if (remaining < 100) charCount.classList.add('warn');
        if (remaining < 0)   charCount.classList.add('over');
    }
    input.addEventListener('input', updateCount);

    // ── Auto-resize textarea ───────────────────────────────────────────────────
    input.addEventListener('input', function() {
        this.style.height = '42px';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // ── Submit on Enter (Shift+Enter = newline) ────────────────────────────────
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim().length > 0) {
                document.getElementById('sendForm').submit();
            }
        }
    });
</script>
</body>
</html>
