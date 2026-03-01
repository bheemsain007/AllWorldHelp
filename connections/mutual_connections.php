<?php
// connections/mutual_connections.php
// Task T18 — Show mutual (common) connections between logged-in user and another user

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../models/User.php";
require_once "../includes/helpers.php";

$userId      = (int) SessionManager::get('user_id');
$otherUserId = (int) ($_GET['user_id'] ?? 0);

if ($otherUserId <= 0) {
    redirect("connections.php");
}

// Redirect to own connections page if viewing self
if ($otherUserId === $userId) {
    redirect("connections.php");
}

// ── Fetch Other User ──────────────────────────────────────────────────────────
$otherUser = User::findById($otherUserId);

if (!$otherUser) {
    redirect("connections.php?error=notfound");
}

// ── Get Mutual Connections ────────────────────────────────────────────────────
$mutuals      = Connection::getMutualConnections($userId, $otherUserId);
$mutualCount  = count($mutuals);
$pendingCount = Connection::countPending($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mutual Connections — Fellow Traveler</title>
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
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 24px 28px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.1); margin-bottom: 24px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 6px; }
        .header-card p  { color: #64748b; font-size: 15px; }
        .header-card .other-user {
            display: flex; align-items: center; gap: 14px; margin-top: 16px;
            padding-top: 16px; border-top: 1px solid #f1f5f9;
        }
        .header-card .other-user img {
            width: 50px; height: 50px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb;
        }
        .header-card .other-user a { color: #1d4ed8; text-decoration: none; font-weight: bold; }
        .header-card .other-user a:hover { text-decoration: underline; }

        /* ── Grid ── */
        .connections-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px;
        }
        @media (max-width: 600px) { .connections-grid { grid-template-columns: repeat(2, 1fr); } }

        /* ── Connection Card ── */
        .connection-card {
            background: white; border-radius: 10px; padding: 20px 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08); text-align: center;
            transition: transform 0.2s;
        }
        .connection-card:hover { transform: translateY(-4px); }
        .connection-card img {
            width: 80px; height: 80px; border-radius: 50%;
            object-fit: cover; border: 3px solid #e5e7eb; margin-bottom: 12px;
        }
        .connection-card h3 { font-size: 15px; margin-bottom: 3px; }
        .connection-card h3 a { color: #1d4ed8; text-decoration: none; }
        .connection-card h3 a:hover { text-decoration: underline; }
        .connection-card .uname { color: #64748b; font-size: 13px; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 70px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 52px; margin-bottom: 16px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 10px; }
        .empty-state p  { color: #64748b; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="connections.php">Connections</a>
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

    <!-- Header -->
    <div class="header-card">
        <h1>🤝 Mutual Connections</h1>
        <p>
            <?php if ($mutualCount > 0): ?>
                You and <strong><?php echo htmlspecialchars($otherUser['name']); ?></strong>
                have <strong><?php echo $mutualCount; ?></strong>
                mutual connection<?php echo $mutualCount !== 1 ? 's' : ''; ?> in common.
            <?php else: ?>
                No mutual connections yet between you and
                <strong><?php echo htmlspecialchars($otherUser['name']); ?></strong>.
            <?php endif; ?>
        </p>

        <!-- Other user mini-card -->
        <div class="other-user">
            <img
                src="<?php echo !empty($otherUser['profile_photo'])
                    ? '../uploads/profiles/' . htmlspecialchars($otherUser['profile_photo'])
                    : '../assets/default-avatar.png'; ?>"
                alt="<?php echo htmlspecialchars($otherUser['name']); ?>"
            >
            <div>
                <a href="../profile/public_profile.php?user_id=<?php echo $otherUserId; ?>">
                    <?php echo htmlspecialchars($otherUser['name']); ?>
                </a>
                <div style="color:#64748b;font-size:13px;">
                    @<?php echo htmlspecialchars($otherUser['username']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mutual List -->
    <?php if (empty($mutuals)): ?>
        <div class="empty-state">
            <div class="emoji">🌐</div>
            <h2>No mutual connections</h2>
            <p>You don't share any connections with <?php echo htmlspecialchars($otherUser['name']); ?> yet.</p>
        </div>

    <?php else: ?>
        <div class="connections-grid">
            <?php foreach ($mutuals as $m): ?>
                <div class="connection-card">
                    <a href="../profile/public_profile.php?user_id=<?php echo (int) $m['user_id']; ?>">
                        <img
                            src="<?php echo !empty($m['profile_photo'])
                                ? '../uploads/profiles/' . htmlspecialchars($m['profile_photo'])
                                : '../assets/default-avatar.png'; ?>"
                            alt="<?php echo htmlspecialchars($m['name']); ?>"
                        >
                    </a>
                    <h3>
                        <a href="../profile/public_profile.php?user_id=<?php echo (int) $m['user_id']; ?>">
                            <?php echo htmlspecialchars($m['name']); ?>
                        </a>
                    </h3>
                    <div class="uname">@<?php echo htmlspecialchars($m['username']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
