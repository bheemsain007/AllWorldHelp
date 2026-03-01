<?php
// connections/requests.php
// Task T17 — View received / sent connection requests with accept/reject actions

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";

$userId = (int) SessionManager::get('user_id');

// ── Active Tab ────────────────────────────────────────────────────────────────
$tab = ($_GET['tab'] ?? 'received') === 'sent' ? 'sent' : 'received';

// ── Fetch Requests ────────────────────────────────────────────────────────────
$requests = ($tab === 'received')
    ? Connection::getPendingRequests($userId)   // requests TO me
    : Connection::getSentRequests($userId);     // requests FROM me

$pendingCount = Connection::countPending($userId);

// ── Flash Messages ────────────────────────────────────────────────────────────
$successMap = [
    'accepted'  => '✅ Connection accepted!',
    'rejected'  => '✅ Request rejected.',
    'cancelled' => '✅ Request cancelled.',
];
$errorMap = [
    'csrf'              => '❌ Security token invalid. Please try again.',
    'notfound'          => '❌ Request not found.',
    'permission'        => '❌ You do not have permission to do that.',
    'already_processed' => '❌ This request has already been processed.',
    'failed'            => '❌ Something went wrong. Please try again.',
    'invalid'           => '❌ Invalid request.',
];
$flash        = null;
$flashType    = null;
$successKey   = $_GET['success'] ?? '';
$errorKey     = $_GET['error']   ?? '';
$infoKey      = $_GET['info']    ?? '';

if ($successKey && isset($successMap[$successKey])) {
    $flash     = $successMap[$successKey];
    $flashType = 'success';
} elseif ($errorKey && isset($errorMap[$errorKey])) {
    $flash     = $errorMap[$errorKey];
    $flashType = 'error';
} elseif ($infoKey === 'they_sent_first') {
    $flash     = 'ℹ️ This person already sent you a request — accept it below!';
    $flashType = 'info';
}

$csrfField = CSRF::getTokenField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Requests — Fellow Traveler</title>
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
        .flash {
            padding: 14px 18px; border-radius: 6px; margin-bottom: 20px;
            font-weight: 500; font-size: 15px;
        }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .flash.info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

        /* ── Header card ── */
        .header-card {
            background: white; padding: 24px 28px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.1); margin-bottom: 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .header-card h1 { color: #1d4ed8; font-size: 24px; }
        .header-card p  { color: #64748b; margin-top: 4px; font-size: 14px; }

        /* ── Tabs ── */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab {
            flex: 1; padding: 13px 20px; text-align: center;
            background: white; border: 2px solid #e5e7eb;
            border-radius: 8px; cursor: pointer; text-decoration: none;
            color: #374151; font-weight: bold; font-size: 15px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .tab.active { background: #1d4ed8; color: white; border-color: #1d4ed8; }
        .tab:hover:not(.active) { border-color: #1d4ed8; color: #1d4ed8; }

        /* ── Request Card ── */
        .request-card {
            background: white; padding: 20px 24px; border-radius: 10px;
            margin-bottom: 14px; display: flex; gap: 20px; align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .request-card img {
            width: 80px; height: 80px; border-radius: 50%;
            object-fit: cover; border: 3px solid #e5e7eb; flex-shrink: 0;
        }
        .request-info { flex: 1; min-width: 0; }
        .request-info h3 { font-size: 17px; color: #1d4ed8; margin-bottom: 3px; }
        .request-info h3 a { text-decoration: none; color: inherit; }
        .request-info h3 a:hover { text-decoration: underline; }
        .request-info .uname { color: #64748b; font-size: 14px; margin-bottom: 4px; }
        .request-info .when  { color: #94a3b8; font-size: 13px; }

        /* ── Action Buttons ── */
        .request-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .btn {
            padding: 9px 20px; border: none; border-radius: 6px;
            cursor: pointer; font-weight: 600; font-size: 14px;
            text-decoration: none; display: inline-block; white-space: nowrap;
        }
        .btn-accept { background: #16a34a; color: white; }
        .btn-accept:hover { background: #15803d; }
        .btn-reject { background: #dc2626; color: white; }
        .btn-reject:hover { background: #b91c1c; }
        .btn-cancel { background: #6b7280; color: white; }
        .btn-cancel:hover { background: #4b5563; }

        /* Hidden POST form trick */
        .action-form { display: inline; }
        .action-form button.btn { margin: 0; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 { color: #1e293b; font-size: 20px; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; font-size: 15px; }
        .empty-state a  { color: #1d4ed8; }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .request-card { flex-direction: column; align-items: flex-start; gap: 14px; }
            .request-actions { width: 100%; }
            .btn { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="requests.php">
            Requests
            <?php if ($pendingCount > 0): ?>
                <span class="badge-count"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="../profile/my_profile.php">My Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- Flash -->
    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>🤝 Connection Requests</h1>
            <p>Manage your connection requests with fellow travelers</p>
        </div>
        <?php if ($pendingCount > 0): ?>
            <div style="background:#fef3c7;color:#92400e;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:14px;">
                <?php echo $pendingCount; ?> pending
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=received" class="tab <?php echo $tab === 'received' ? 'active' : ''; ?>">
            📥 Received
            <?php if ($pendingCount > 0): ?>
                <span class="badge-count" style="<?php echo $tab === 'received' ? 'background:#93c5fd;color:#1e40af' : ''; ?>">
                    <?php echo $pendingCount; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="?tab=sent" class="tab <?php echo $tab === 'sent' ? 'active' : ''; ?>">
            📤 Sent
        </a>
    </div>

    <!-- Request List -->
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="emoji"><?php echo $tab === 'received' ? '📭' : '📬'; ?></div>
            <h3>
                <?php echo $tab === 'received'
                    ? 'No pending requests'
                    : 'No sent requests'; ?>
            </h3>
            <p>
                <?php if ($tab === 'received'): ?>
                    You'll see connection requests here when someone wants to connect with you.
                <?php else: ?>
                    You haven't sent any connection requests yet.
                    <a href="../trips/search.php">Browse trips</a> to meet fellow travelers!
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>
        <?php foreach ($requests as $req): ?>
            <div class="request-card">

                <!-- Avatar -->
                <img
                    src="<?php echo !empty($req['profile_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($req['profile_photo'])
                        : '../assets/default-avatar.png'; ?>"
                    alt="<?php echo htmlspecialchars($req['name']); ?>"
                >

                <!-- Info -->
                <div class="request-info">
                    <h3>
                        <a href="../profile/public_profile.php?user_id=<?php echo (int) $req['user_id']; ?>">
                            <?php echo htmlspecialchars($req['name']); ?>
                        </a>
                    </h3>
                    <div class="uname">@<?php echo htmlspecialchars($req['username']); ?></div>
                    <div class="when"><?php echo date('M j, Y', strtotime($req['created_at'])); ?></div>
                </div>

                <!-- Actions -->
                <div class="request-actions">
                    <?php if ($tab === 'received'): ?>

                        <!-- Accept -->
                        <form method="POST" action="accept.php" class="action-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="connection_id" value="<?php echo (int) $req['connection_id']; ?>">
                            <button type="submit" class="btn btn-accept">✅ Accept</button>
                        </form>

                        <!-- Reject -->
                        <form method="POST" action="reject.php" class="action-form"
                              onsubmit="return confirm('Reject this request?')">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="connection_id" value="<?php echo (int) $req['connection_id']; ?>">
                            <button type="submit" class="btn btn-reject">✖ Reject</button>
                        </form>

                    <?php else: ?>

                        <!-- Cancel sent request -->
                        <form method="POST" action="reject.php" class="action-form"
                              onsubmit="return confirm('Cancel this connection request?')">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="connection_id" value="<?php echo (int) $req['connection_id']; ?>">
                            <button type="submit" class="btn btn-cancel">✖ Cancel</button>
                        </form>

                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
