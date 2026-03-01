<?php
// trips/trip_discussion.php
// Task T22 — Trip discussion board: chronological comments + post form

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripComment.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$tripId = (int) ($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Viewer Context ────────────────────────────────────────────────────────────
$viewerId      = (int) SessionManager::get('user_id');
$isOrganizer   = $viewerId && ((int) $trip['user_id'] === $viewerId);
$isParticipant = $viewerId && Trip::isParticipant($tripId, $viewerId);  // includes organizer
$canComment    = $isParticipant;

// ── Fetch Comments ────────────────────────────────────────────────────────────
$comments     = TripComment::getCommentsForTrip($tripId);
$commentCount = count($comments);

// ── Flash Messages ────────────────────────────────────────────────────────────
$flashMap = [
    'commented'       => ['✅ Comment posted!',                                  'success'],
    'deleted'         => ['✅ Comment deleted.',                                 'success'],
    'not_participant' => ['❌ Only trip participants can comment.',               'error'],
    'invalid_comment' => ['❌ Comment must be between 10 and 500 characters.',   'error'],
    'permission'      => ['❌ You do not have permission to delete that comment.','error'],
    'failed'          => ['❌ Something went wrong. Please try again.',           'error'],
    'csrf'            => ['❌ Security token invalid.',                           'error'],
];
$flash     = null;
$flashType = null;
$sKey = $_GET['success'] ?? '';
$eKey = $_GET['error']   ?? '';
if ($sKey && isset($flashMap[$sKey])) { [$flash, $flashType] = $flashMap[$sKey]; }
elseif ($eKey && isset($flashMap[$eKey])) { [$flash, $flashType] = $flashMap[$eKey]; }

$csrfField = CSRF::getTokenField();
$tripTitle  = htmlspecialchars($trip['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 780px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 22px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 21px; margin-bottom: 4px; }
        .header-card p  { color: #64748b; font-size: 14px; }

        /* ── Comment Card ── */
        .comment-card {
            background: white; padding: 18px 20px; border-radius: 10px;
            margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,.06);
            display: flex; gap: 14px;
        }
        .comment-card.own { border-left: 4px solid #1d4ed8; }
        .comment-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb; flex-shrink: 0;
        }
        .comment-body { flex: 1; min-width: 0; }
        .comment-header {
            display: flex; align-items: baseline; gap: 10px; margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .commenter-name { font-weight: 700; color: #1d4ed8; font-size: 14px; }
        .commenter-name a { text-decoration: none; color: inherit; }
        .commenter-name a:hover { text-decoration: underline; }
        .commenter-badge {
            font-size: 11px; background: #eff6ff; color: #1d4ed8;
            padding: 2px 8px; border-radius: 10px; font-weight: 600;
        }
        .comment-time { color: #94a3b8; font-size: 12px; margin-left: auto; }
        .comment-text { color: #374151; font-size: 14px; line-height: 1.6; }
        .comment-actions { margin-top: 8px; }
        .btn-delete {
            font-size: 12px; color: #94a3b8; background: none; border: none;
            cursor: pointer; padding: 0; text-decoration: underline;
        }
        .btn-delete:hover { color: #dc2626; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 56px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.07); margin-bottom: 24px;
        }
        .empty-state .emoji { font-size: 46px; margin-bottom: 14px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; }

        /* ── Post Comment Form ── */
        .post-form-card {
            background: white; padding: 24px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-top: 24px;
        }
        .post-form-card h3 { color: #1d4ed8; font-size: 17px; margin-bottom: 16px; }
        .post-row { display: flex; gap: 12px; align-items: flex-start; }
        .post-row .my-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb; flex-shrink: 0; margin-top: 2px;
        }
        .post-row .input-wrap { flex: 1; }
        textarea.comment-input {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; font-family: inherit;
            resize: vertical; min-height: 90px; line-height: 1.5;
        }
        textarea.comment-input:focus { outline: none; border-color: #1d4ed8; }
        .char-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 8px;
        }
        .char-row .cnt { font-size: 13px; color: #94a3b8; }
        .char-row .cnt.warn { color: #f59e0b; }
        .char-row .cnt.min  { color: #dc2626; }
        .btn-post {
            background: #1d4ed8; color: white; border: none;
            padding: 10px 22px; border-radius: 8px; font-size: 14px;
            font-weight: 700; cursor: pointer; transition: background 0.2s;
        }
        .btn-post:hover { background: #1e40af; }

        /* ── Not-participant notice ── */
        .join-notice {
            background: #fefce8; border: 1px solid #fef08a;
            color: #713f12; padding: 14px 18px; border-radius: 8px;
            margin-top: 20px; font-size: 14px;
        }
        .join-notice a { color: #1d4ed8; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="trip_feed.php?trip_id=<?php echo $tripId; ?>">Updates</a>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>💬 Trip Discussion</h1>
            <p>
                <strong><?php echo $tripTitle; ?></strong> ·
                <?php echo $commentCount; ?> comment<?php echo $commentCount !== 1 ? 's' : ''; ?>
            </p>
        </div>
        <a href="view.php?id=<?php echo $tripId; ?>"
           style="color:#1d4ed8;text-decoration:none;font-size:14px;">← Back to Trip</a>
    </div>

    <!-- Comments -->
    <?php if (empty($comments)): ?>
        <div class="empty-state">
            <div class="emoji">💬</div>
            <h2>No comments yet</h2>
            <p>
                <?php if ($canComment): ?>
                    Be the first to start the discussion!
                <?php else: ?>
                    Join the trip to participate in the discussion.
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <?php
                $isOwn       = $viewerId && ((int) $c['user_id'] === $viewerId);
                $canDelete   = $isOwn || $isOrganizer;
                $ts          = strtotime($c['created_at']);
                $timeStr     = date('Y-m-d', $ts) === date('Y-m-d')
                    ? 'Today ' . date('g:i A', $ts)
                    : date('M j, Y g:i A', $ts);
            ?>
            <div class="comment-card <?php echo $isOwn ? 'own' : ''; ?>">
                <img
                    class="comment-avatar"
                    src="<?php echo !empty($c['profile_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($c['profile_photo'])
                        : '../assets/default-avatar.png'; ?>"
                    alt="<?php echo htmlspecialchars($c['name']); ?>"
                >
                <div class="comment-body">
                    <div class="comment-header">
                        <span class="commenter-name">
                            <a href="../profile/public_profile.php?user_id=<?php echo (int) $c['user_id']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </a>
                        </span>
                        <?php if ((int) $c['user_id'] === (int) $trip['user_id']): ?>
                            <span class="commenter-badge">Organizer</span>
                        <?php endif; ?>
                        <span class="comment-time"><?php echo $timeStr; ?></span>
                    </div>

                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($c['comment_text'])); ?>
                    </div>

                    <?php if ($canDelete): ?>
                        <div class="comment-actions">
                            <form method="POST" action="delete_comment.php" style="display:inline"
                                  onsubmit="return confirm('Delete this comment?')">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="comment_id" value="<?php echo (int) $c['comment_id']; ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Post Form -->
    <?php if ($canComment): ?>
        <div class="post-form-card">
            <h3>💬 Add a Comment</h3>
            <form method="POST" action="post_comment.php">
                <?php echo $csrfField; ?>
                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                <div class="post-row">
                    <img
                        class="my-avatar"
                        src="<?php
                            // Quick fetch of logged-in user photo
                            global $pdo;
                            try {
                                $s = $pdo->prepare("SELECT profile_photo FROM ft_users WHERE user_id = ?");
                                $s->execute([$viewerId]);
                                $myPhoto = $s->fetchColumn();
                                echo $myPhoto
                                    ? '../uploads/profiles/' . htmlspecialchars($myPhoto)
                                    : '../assets/default-avatar.png';
                            } catch (Exception $e) { echo '../assets/default-avatar.png'; }
                        ?>"
                        alt="You"
                    >
                    <div class="input-wrap">
                        <textarea
                            class="comment-input"
                            name="comment_text"
                            id="commentText"
                            placeholder="Write your comment… (10–500 characters)"
                            maxlength="500"
                            required
                        ></textarea>
                        <div class="char-row">
                            <span class="cnt" id="charCount">0/500</span>
                            <button type="submit" class="btn-post">Post Comment</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

    <?php elseif ($viewerId && !$canComment): ?>
        <div class="join-notice">
            ℹ️ <a href="view.php?id=<?php echo $tripId; ?>">Join this trip</a>
            to participate in the discussion.
        </div>

    <?php elseif (!$viewerId): ?>
        <div class="join-notice">
            ℹ️ <a href="../auth/login.php">Log in</a> and join the trip to comment.
        </div>
    <?php endif; ?>

</div><!-- /.container -->

<script>
    const ta  = document.getElementById('commentText');
    const cnt = document.getElementById('charCount');
    if (ta) {
        ta.addEventListener('input', function() {
            const len = this.value.length;
            cnt.textContent = len + '/500';
            cnt.className = 'cnt';
            if (len < 10)  cnt.classList.add('min');
            if (len > 450) cnt.classList.add('warn');
        });
    }
    // Auto-scroll to bottom of comments if just posted
    <?php if ($sKey === 'commented'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.post-form-card');
            if (form) form.scrollIntoView({ behavior: 'smooth' });
        });
    <?php endif; ?>
</script>
</body>
</html>
