<?php
// profile/my_profile.php
// Task T11 — User's own profile view (complete information + edit access)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/ProfileHelper.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');
$user   = User::findById($userId);

if (!$user) {
    redirect("../auth/logout.php");
}

// Fetch statistics
$stats = ProfileHelper::getProfileStats($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Fellow Traveler</title>
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
            object-fit: cover; border: 4px solid #1d4ed8;
            flex-shrink: 0;
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

        /* ── Edit Button ── */
        .edit-btn {
            background: #1d4ed8; color: white; padding: 10px 22px;
            border: none; border-radius: 5px; font-size: 15px;
            cursor: pointer; text-decoration: none; display: inline-block;
            margin-top: 12px;
        }
        .edit-btn:hover { background: #1e40af; }

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

        /* ── Info Grid ── */
        .info-grid {
            background: white; padding: 24px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        .info-grid h2 { margin-bottom: 16px; color: #1e293b; font-size: 18px; }
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
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="my_profile.php">My Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- ── Profile Header ── -->
    <div class="profile-header">
        <img
            src="<?php echo !empty($user['profile_photo'])
                ? '../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
                : '../assets/default-avatar.png'; ?>"
            alt="Profile Photo"
            class="profile-photo"
        >

        <div class="profile-info">
            <div class="profile-name">
                <?php echo htmlspecialchars($user['name']); ?>
                <?php echo ProfileHelper::getVerificationBadge($user); ?>
                <?php echo ProfileHelper::getPremiumBadge($user); ?>
                <?php echo ProfileHelper::getCoinLevelBadge($userId); ?>
            </div>

            <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>

            <div class="bio">
                <?php echo !empty($user['bio'])
                    ? htmlspecialchars($user['bio'])
                    : '<em>No bio added yet.</em>'; ?>
            </div>

            <a href="edit.php" class="edit-btn">✏️ Edit Profile</a>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['trips']; ?></div>
            <div class="stat-label">Trips Created</div>
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
            <div class="stat-number"><?php echo number_format($stats['coins']); ?></div>
            <div class="stat-label">Coins 🪙</div>
        </div>
    </div>

    <!-- ── Personal Information ── -->
    <div class="info-grid">
        <h2>👤 Personal Information</h2>

        <div class="info-row">
            <div class="info-label">Email</div>
            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Phone</div>
            <div class="info-value">
                <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<em>Not provided</em>'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">City</div>
            <div class="info-value">
                <?php echo !empty($user['city']) ? htmlspecialchars($user['city']) : '<em>Not provided</em>'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Country</div>
            <div class="info-value">
                <?php echo !empty($user['country']) ? htmlspecialchars($user['country']) : '<em>Not provided</em>'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Verification</div>
            <div class="info-value">
                <?php echo $user['is_verified'] ? '✅ Verified account' : '⏳ Not verified yet'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Account Type</div>
            <div class="info-value">
                <?php echo $user['is_premium'] ? '⭐ Premium member' : '🆓 Free account'; ?>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Member Since</div>
            <div class="info-value">
                <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
            </div>
        </div>

        <?php if (!empty($user['last_login'])): ?>
        <div class="info-row">
            <div class="info-label">Last Login</div>
            <div class="info-value">
                <?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.container -->
</body>
</html>
