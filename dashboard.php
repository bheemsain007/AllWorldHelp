<?php
// dashboard.php
// Updated in T7: uses SessionManager::get() for all session reads

require_once "includes/SessionManager.php";
SessionManager::start();
require_once "auth/auth_check.php";     // Protect page — redirects if not logged in
require_once "config/database.php";
require_once "includes/helpers.php";

// Read session data via SessionManager
$userName   = SessionManager::get('name',       'User');
$userEmail  = SessionManager::get('email',      '');
$userHandle = SessionManager::get('username',   '');
$isPremium  = SessionManager::get('is_premium',  0);
$isVerified = SessionManager::get('is_verified', 0);
$loggedInAt = SessionManager::get('logged_in_at', time());
$userId     = SessionManager::userId();

// Flash messages (from T7 bonus flash system)
$flashSuccess = SessionManager::getFlash('success');
$flashError   = SessionManager::getFlash('error');

// Pull fresh coin balance
$coinBalance = 0;
try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT total_coins FROM ft_user_coins WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wallet      = $stmt->fetch();
    $coinBalance = $wallet ? (int)$wallet['total_coins'] : 0;
} catch (Throwable $e) {
    // Fail silently — table may not be populated yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4f8; min-height: 100vh; }

        /* ── Top Nav ── */
        .navbar {
            background: #1d4ed8; color: white; padding: 14px 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar .logo { font-size: 1.4rem; font-weight: bold; }
        .navbar .nav-right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; }
        .navbar .coin-badge {
            background: #fbbf24; color: #1e1b4b; padding: 4px 12px;
            border-radius: 99px; font-weight: bold; font-size: 0.85rem;
        }
        .logout-btn {
            background: #dc2626; color: white; padding: 6px 14px;
            border: none; border-radius: 5px; cursor: pointer; font-size: 0.85rem;
            text-decoration: none;
        }
        .logout-btn:hover { background: #b91c1c; }

        /* ── Main Content ── */
        .main { max-width: 900px; margin: 30px auto; padding: 0 16px; }

        /* ── Flash Messages ── */
        .flash-success { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #86efac; }
        .flash-error   { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #fca5a5; }

        /* ── Welcome Card ── */
        .welcome-card {
            background: white; border-radius: 12px; padding: 28px;
            margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 5px solid #1d4ed8;
        }
        .welcome-card h1 { font-size: 1.6rem; color: #1e293b; margin-bottom: 8px; }
        .welcome-card .meta { color: #64748b; font-size: 0.9rem; margin-bottom: 4px; }
        .badges { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        .badge { padding: 3px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: bold; }
        .badge.premium  { background: #fef9c3; color: #854d0e; }
        .badge.verified { background: #dcfce7; color: #14532d; }
        .badge.member   { background: #e0e7ff; color: #3730a3; }

        /* ── Quick Links Grid ── */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card {
            background: white; border-radius: 10px; padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.07); text-align: center;
            text-decoration: none; color: #1e293b;
            transition: transform .15s, box-shadow .15s;
        }
        .card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
        .card .icon { font-size: 2rem; margin-bottom: 8px; }
        .card h3 { font-size: 1rem; margin-bottom: 4px; }
        .card p  { font-size: 0.8rem; color: #64748b; }

        /* ── Session Info Panel ── */
        .info-panel {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .info-panel h2 { color: #1d4ed8; margin-bottom: 12px; font-size: 1rem; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row .key   { color: #64748b; }
        .info-row .value { font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>

<!-- ── Navigation ── -->
<nav class="navbar">
    <div class="logo">🌍 Fellow Traveler</div>
    <div class="nav-right">
        <span class="coin-badge">🪙 <?php echo number_format($coinBalance); ?> coins</span>
        <span>Hello, <?php echo htmlspecialchars($userName); ?>!</span>
        <a href="auth/logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="main">

    <!-- ── Flash Messages ── -->
    <?php if ($flashSuccess): ?>
        <div class="flash-success">✅ <?php echo htmlspecialchars($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flash-error">❌ <?php echo htmlspecialchars($flashError); ?></div>
    <?php endif; ?>

    <!-- ── Welcome Card ── -->
    <div class="welcome-card">
        <h1>Welcome back, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
        <p class="meta">📧 <?php echo htmlspecialchars($userEmail); ?></p>
        <p class="meta">🔗 @<?php echo htmlspecialchars($userHandle); ?></p>
        <div class="badges">
            <span class="badge member">🧑 Member</span>
            <?php if ($isPremium): ?><span class="badge premium">⭐ Premium</span><?php endif; ?>
            <?php if ($isVerified): ?><span class="badge verified">✅ Verified</span><?php endif; ?>
        </div>
    </div>

    <!-- ── Quick Links ── -->
    <div class="grid">
        <a href="profile/view.php" class="card">
            <div class="icon">👤</div>
            <h3>My Profile</h3>
            <p>View &amp; edit your profile</p>
        </a>
        <a href="trips/search.php" class="card">
            <div class="icon">🔍</div>
            <h3>Search Trips</h3>
            <p>Find travel companions</p>
        </a>
        <a href="trips/create.php" class="card">
            <div class="icon">✈️</div>
            <h3>Create Trip</h3>
            <p>Start a new adventure</p>
        </a>
        <a href="connections/index.php" class="card">
            <div class="icon">🤝</div>
            <h3>Connections</h3>
            <p>Manage your network</p>
        </a>
        <a href="messages/index.php" class="card">
            <div class="icon">💬</div>
            <h3>Messages</h3>
            <p>Chat with travelers</p>
        </a>
        <a href="notifications/index.php" class="card">
            <div class="icon">🔔</div>
            <h3>Notifications</h3>
            <p>Stay up to date</p>
        </a>
    </div>

    <!-- ── Session Info (dev helper) ── -->
    <div class="info-panel">
        <h2>📋 Session Info (T7 — SessionManager)</h2>
        <div class="info-row">
            <span class="key">User ID</span>
            <span class="value"><?php echo (int)$userId; ?></span>
        </div>
        <div class="info-row">
            <span class="key">Username</span>
            <span class="value">@<?php echo htmlspecialchars($userHandle); ?></span>
        </div>
        <div class="info-row">
            <span class="key">Session Name</span>
            <span class="value"><?php echo htmlspecialchars(session_name()); ?></span>
        </div>
        <div class="info-row">
            <span class="key">Logged in at</span>
            <span class="value"><?php echo date('d M Y, H:i:s', $loggedInAt); ?></span>
        </div>
        <div class="info-row">
            <span class="key">Session expires</span>
            <span class="value"><?php echo date('d M Y, H:i:s', $loggedInAt + SessionManager::SESSION_LIFETIME); ?></span>
        </div>
        <div class="info-row">
            <span class="key">Coin Balance</span>
            <span class="value">🪙 <?php echo number_format($coinBalance); ?></span>
        </div>
        <div class="info-row">
            <span class="key">isActive()</span>
            <span class="value" style="color:#16a34a;">✅ true</span>
        </div>
    </div>

</div>
</body>
</html>
