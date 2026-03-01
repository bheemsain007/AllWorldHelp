<?php
// profile/public_profile.php
// Task T11 — View another user's profile (limited info, privacy respected)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/ProfileHelper.php";
require_once "../includes/helpers.php";

// ── Resolve which user to display ────────────────────────────────────────────
if (isset($_GET['user_id'])) {
    $profileUser = User::findById((int) $_GET['user_id']);
} elseif (isset($_GET['username'])) {
    $profileUser = User::findByUsername($_GET['username']);
} else {
    redirect("../index.php");
}

if (!$profileUser) {
    http_response_code(404);
    die("User not found.");
}

// ── Privacy check ─────────────────────────────────────────────────────────────
$viewerId = SessionManager::isActive() ? (int) SessionManager::get('user_id') : null;

if (!ProfileHelper::canViewProfile($viewerId, (int) $profileUser['user_id'])) {
    http_response_code(403);
    die("This profile is private.");
}

// ── Redirect owner to their own profile ──────────────────────────────────────
if ($viewerId && $viewerId === (int) $profileUser['user_id']) {
    redirect("my_profile.php");
}

// ── Statistics (public-safe: no coin balance) ─────────────────────────────────
$stats       = ProfileHelper::getProfileStats((int) $profileUser['user_id']);
$profileName = htmlspecialchars($profileUser['name']);
$profileId   = (int) $profileUser['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profileName; ?> — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* ── Navigation ── */
        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        /* ── Layout ── */
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }

        /* ── Profile Header ── */
        .profile-header {
            background: white; padding: 30px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;
            display: flex; gap: 30px; align-items: flex-start;
        }
        .profile-photo {
            width: 150px; height: 150px; border-radius: 50%;
            object-fit: cover; border: 4px solid #1d4ed8; flex-shrink: 0;
        }
        .profile-info { flex: 1; }
        .profile-name {
            font-size: 28px; font-weight: bold; color: #1d4ed8;
            margin-bottom: 6px; display: flex; align-items: center;
            gap: 8px; flex-wrap: wrap;
        }
        .username { color: #64748b; font-size: 17px; margin-bottom: 10px; }
        .bio { color: #475569; line-height: 1.6; margin: 12px 0; }

        /* ── Badges ── */
        .badge {
            padding: 3px 10px; border-radius: 12px; font-size: 12px;
            font-weight: bold; color: white; white-space: nowrap;
        }
        .badge-verified  { background: #0ea5e9; }
        .badge-premium   { background: #f59e0b; }
        .badge-bronze    { background: #92400e; }
        .badge-silver    { background: #6b7280; }
        .badge-gold      { background: #d97706; }
        .badge-platinum  { background: #6366f1; }

        /* ── Action Buttons ── */
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn {
            padding: 10px 20px; border-radius: 5px; border: none;
            cursor: pointer; font-size: 14px; text-decoration: none;
            display: inline-block; text-align: center; font-weight: 600;
        }
        .btn-primary   { background: #1d4ed8; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-danger    { background: #dc2626; color: white; }
        .btn:hover     { opacity: 0.88; }

        /* ── Stats Grid ── */
        .stats {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 16px; margin-bottom: 20px;
        }
        .stat-box {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center;
        }
        .stat-number { font-size: 30px; font-weight: bold; color: #1d4ed8; }
        .stat-label  { color: #64748b; margin-top: 4px; font-size: 14px; }

        /* ── Info Card ── */
        .info-card {
            background: white; padding: 24px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        .info-card h2 { margin-bottom: 16px; color: #1e293b; font-size: 18px; }
        .info-row {
            display: flex; padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; width: 160px; color: #475569; flex-shrink: 0; }
        .info-value { color: #1e293b; }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .profile-header { flex-direction: column; align-items: center; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .profile-name { justify-content: center; }
            .action-buttons { justify-content: center; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <?php if ($viewerId): ?>
            <a href="../dashboard.php">Dashboard</a>
            <a href="my_profile.php">My Profile</a>
            <a href="../auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="../auth/login.php">Login</a>
            <a href="../auth/register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

    <!-- ── Profile Header ── -->
    <div class="profile-header">
        <img
            src="<?php echo !empty($profileUser['profile_photo'])
                ? '../uploads/profiles/' . htmlspecialchars($profileUser['profile_photo'])
                : '../assets/default-avatar.png'; ?>"
            alt="<?php echo $profileName; ?>'s Photo"
            class="profile-photo"
        >

        <div class="profile-info">
            <div class="profile-name">
                <?php echo $profileName; ?>
                <?php echo ProfileHelper::getVerificationBadge($profileUser); ?>
                <?php echo ProfileHelper::getPremiumBadge($profileUser); ?>
                <?php echo ProfileHelper::getCoinLevelBadge($profileId); ?>
            </div>

            <div class="username">@<?php echo htmlspecialchars($profileUser['username']); ?></div>

            <div class="bio">
                <?php echo !empty($profileUser['bio'])
                    ? htmlspecialchars($profileUser['bio'])
                    : '<em>No bio.</em>'; ?>
            </div>

            <!-- Action buttons — only visible to logged-in users -->
            <?php if ($viewerId): ?>
            <div class="action-buttons">
                <a href="../connections/send_request.php?user_id=<?php echo $profileId; ?>"
                   class="btn btn-primary">🤝 Connect</a>
                <a href="../messages/new.php?to=<?php echo $profileId; ?>"
                   class="btn btn-secondary">✉️ Send Message</a>
                <a href="../reports/report.php?user_id=<?php echo $profileId; ?>"
                   class="btn btn-danger">🚩 Report</a>
            </div>
            <?php else: ?>
            <p style="margin-top:12px; color:#64748b;">
                <a href="../auth/login.php" style="color:#1d4ed8;">Login</a> to connect with <?php echo $profileName; ?>.
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stats (no coin balance shown to public) ── -->
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['trips']; ?></div>
            <div class="stat-label">Trips</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['connections']; ?></div>
            <div class="stat-label">Connections</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['reviews']; ?></div>
            <div class="stat-label">Reviews</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo date('Y', strtotime($profileUser['created_at'])); ?></div>
            <div class="stat-label">Joined</div>
        </div>
    </div>

    <!-- ── Public Info (no email / phone / DOB / coin balance) ── -->
    <div class="info-card">
        <h2>ℹ️ About <?php echo $profileName; ?></h2>

        <?php if (!empty($profileUser['city']) || !empty($profileUser['country'])): ?>
        <div class="info-row">
            <div class="info-label">Location</div>
            <div class="info-value">
                <?php
                $location = array_filter([
                    $profileUser['city']    ?? '',
                    $profileUser['country'] ?? '',
                ]);
                echo htmlspecialchars(implode(', ', $location));
                ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="info-row">
            <div class="info-label">Account Type</div>
            <div class="info-value">
                <?php echo $profileUser['is_premium'] ? '⭐ Premium member' : '🆓 Free account'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Member Since</div>
            <div class="info-value">
                <?php echo date('F Y', strtotime($profileUser['created_at'])); ?>
            </div>
        </div>
    </div>

</div><!-- /.container -->
</body>
</html>
