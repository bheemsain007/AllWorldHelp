<?php
// connections/connections.php
// Task T18 — View & search all accepted connections; remove (unfriend) option

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";

$userId = (int) SessionManager::get('user_id');

// ── Search / Filter ───────────────────────────────────────────────────────────
$searchQuery = trim($_GET['q'] ?? '');

// Fetch all accepted connections, then optionally filter in PHP for simplicity
$allConnections = Connection::getConnections($userId);

if ($searchQuery !== '') {
    $allConnections = array_filter($allConnections, function (array $c) use ($searchQuery): bool {
        return stripos($c['name'],     $searchQuery) !== false
            || stripos($c['username'], $searchQuery) !== false;
    });
}

$totalConnections = count($allConnections);
$pendingCount     = Connection::countPending($userId);

// ── Flash Messages ────────────────────────────────────────────────────────────
$flashMap = [
    'removed'        => ['✅ Connection removed.', 'success'],
    'failed'         => ['❌ Something went wrong. Please try again.', 'error'],
    'not_connected'  => ['❌ You are not connected to that user.', 'error'],
    'csrf'           => ['❌ Security token invalid.', 'error'],
];
$flash     = null;
$flashType = null;
$fKey      = $_GET['success'] ?? $_GET['error'] ?? '';
if ($fKey && isset($flashMap[$fKey])) {
    [$flash, $flashType] = $flashMap[$fKey];
}

$csrfField = CSRF::getTokenField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Connections — Fellow Traveler</title>
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
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 24px 28px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.1); margin-bottom: 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 20px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 24px; }
        .header-card .sub { color: #64748b; margin-top: 4px; font-size: 15px; }

        /* ── Search Bar ── */
        .search-form { display: flex; gap: 10px; margin-bottom: 24px; }
        .search-form input {
            flex: 1; padding: 12px 16px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px;
        }
        .search-form input:focus { outline: none; border-color: #1d4ed8; }
        .search-form button {
            padding: 12px 28px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 15px;
            cursor: pointer; font-weight: 600;
        }
        .search-form button:hover { background: #1e40af; }

        /* ── Grid ── */
        .connections-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }
        @media (max-width: 900px) { .connections-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 600px) { .connections-grid { grid-template-columns: repeat(2, 1fr); } }

        /* ── Connection Card ── */
        .connection-card {
            background: white; border-radius: 10px; padding: 22px 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08); text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .connection-card:hover { transform: translateY(-4px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
        .connection-card img {
            width: 90px; height: 90px; border-radius: 50%;
            object-fit: cover; border: 3px solid #e5e7eb; margin-bottom: 12px;
        }
        .connection-card h3 { font-size: 16px; margin-bottom: 3px; }
        .connection-card h3 a { color: #1d4ed8; text-decoration: none; }
        .connection-card h3 a:hover { text-decoration: underline; }
        .connection-card .uname { color: #64748b; font-size: 13px; margin-bottom: 14px; }

        /* ── Card Actions ── */
        .card-actions { display: flex; gap: 6px; justify-content: center; }
        .btn {
            flex: 1; padding: 8px 6px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 13px; font-weight: 600;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-msg    { background: #1d4ed8; color: white; }
        .btn-msg:hover { background: #1e40af; }
        .btn-remove { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .btn-remove:hover { background: #dc2626; color: white; }

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

    <!-- Flash -->
    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>👥 My Connections</h1>
            <div class="sub">
                <?php if ($searchQuery): ?>
                    <?php echo $totalConnections; ?> result<?php echo $totalConnections !== 1 ? 's' : ''; ?>
                    for "<?php echo htmlspecialchars($searchQuery); ?>"
                <?php else: ?>
                    <?php echo $totalConnections; ?> connection<?php echo $totalConnections !== 1 ? 's' : ''; ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="requests.php" style="background:#1d4ed8;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">
            📥 View Requests
        </a>
    </div>

    <!-- Search -->
    <form method="GET" class="search-form">
        <input type="text" name="q"
               placeholder="Search by name or username…"
               value="<?php echo htmlspecialchars($searchQuery); ?>">
        <button type="submit">🔍 Search</button>
    </form>

    <!-- Connections -->
    <?php if (empty($allConnections)): ?>
        <div class="empty-state">
            <div class="emoji"><?php echo $searchQuery ? '🔍' : '👥'; ?></div>
            <h2>
                <?php echo $searchQuery
                    ? 'No connections match "' . htmlspecialchars($searchQuery) . '"'
                    : 'No connections yet'; ?>
            </h2>
            <p>
                <?php if ($searchQuery): ?>
                    Try a different name or username.
                <?php else: ?>
                    Start connecting with fellow travelers to build your network!
                <?php endif; ?>
            </p>
            <?php if (!$searchQuery): ?>
                <a href="../trips/search.php">Browse Trips & Meet Travelers</a>
            <?php else: ?>
                <a href="connections.php">View All Connections</a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="connections-grid">
            <?php foreach ($allConnections as $conn): ?>
                <div class="connection-card">

                    <a href="../profile/public_profile.php?user_id=<?php echo (int) $conn['user_id']; ?>">
                        <img
                            src="<?php echo !empty($conn['profile_photo'])
                                ? '../uploads/profiles/' . htmlspecialchars($conn['profile_photo'])
                                : '../assets/default-avatar.png'; ?>"
                            alt="<?php echo htmlspecialchars($conn['name']); ?>"
                        >
                    </a>

                    <h3>
                        <a href="../profile/public_profile.php?user_id=<?php echo (int) $conn['user_id']; ?>">
                            <?php echo htmlspecialchars($conn['name']); ?>
                        </a>
                    </h3>
                    <div class="uname">@<?php echo htmlspecialchars($conn['username']); ?></div>

                    <div class="card-actions">
                        <!-- Message (T19 placeholder) -->
                        <a href="../messages/new.php?to=<?php echo (int) $conn['user_id']; ?>"
                           class="btn btn-msg">✉️ Msg</a>

                        <!-- Remove / Unfriend — POST form -->
                        <form method="POST" action="remove.php" style="flex:1"
                              onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($conn['name'])); ?> from your connections?')">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="friend_id" value="<?php echo (int) $conn['user_id']; ?>">
                            <button type="submit" class="btn btn-remove" style="width:100%">✖ Remove</button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
