<?php
// trips/trip_feed.php
// Task T21 — Timeline of all updates for a trip; organizer can post new updates

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripUpdate.php";
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
$viewerId    = (int) SessionManager::get('user_id');
$isOrganizer = $viewerId && ((int) $trip['user_id'] === $viewerId);

// ── Fetch Updates ─────────────────────────────────────────────────────────────
$updates     = TripUpdate::getUpdatesForTrip($tripId);
$updateCount = count($updates);

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash     = null;
$flashType = null;
if (($_GET['success'] ?? '') === 'posted') {
    $flash     = '✅ Update posted successfully!';
    $flashType = 'success';
}

// ── Type meta ─────────────────────────────────────────────────────────────────
$typeMeta = [
    'info'       => ['label' => 'ℹ️ Info',      'class' => 'type-info',       'dot' => '#1d4ed8'],
    'warning'    => ['label' => '⚠️ Warning',   'class' => 'type-warning',    'dot' => '#ea580c'],
    'reminder'   => ['label' => '⏰ Reminder',  'class' => 'type-reminder',   'dot' => '#ca8a04'],
    'completion' => ['label' => '✅ Completed', 'class' => 'type-completion', 'dot' => '#16a34a'],
];

$tripTitle = htmlspecialchars($trip['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updates — <?php echo $tripTitle; ?></title>
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

        /* ── Header card ── */
        .header-card {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.1); margin-bottom: 28px;
        }
        .header-img {
            height: 160px; background: #1d4ed8;
            background-image: url('<?php echo !empty($trip['image'])
                ? "../uploads/trips/" . htmlspecialchars($trip['image'])
                : ""; ?>');
            background-size: cover; background-position: center;
            position: relative;
        }
        .header-img::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,.55));
        }
        .header-body {
            padding: 20px 24px;
            display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
        }
        .header-body h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 4px; }
        .header-body p  { color: #64748b; font-size: 14px; }
        .btn-post {
            display: inline-block; padding: 10px 20px; background: #1d4ed8; color: white;
            border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-post:hover { background: #1e40af; }

        /* ── Timeline ── */
        .timeline { position: relative; padding-left: 52px; }
        .timeline::before {
            content: ''; position: absolute; left: 18px; top: 0; bottom: 0;
            width: 2px; background: #e5e7eb; border-radius: 1px;
        }

        /* ── Update Item ── */
        .update-item {
            position: relative; background: white; padding: 20px 22px;
            border-radius: 10px; margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,.07);
        }
        .update-dot {
            position: absolute; left: -42px; top: 22px;
            width: 18px; height: 18px; border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e5e7eb;
        }

        /* ── Type Badge ── */
        .type-badge {
            display: inline-block; padding: 4px 12px; border-radius: 14px;
            font-size: 12px; font-weight: 700; margin-bottom: 12px;
        }
        .type-info       { background: #dbeafe; color: #1d4ed8; }
        .type-warning    { background: #fed7aa; color: #ea580c; }
        .type-reminder   { background: #fef3c7; color: #ca8a04; }
        .type-completion { background: #d1fae5; color: #16a34a; }

        /* ── Update Content ── */
        .update-text {
            color: #374151; font-size: 15px; line-height: 1.65; margin-bottom: 12px;
        }
        .update-meta {
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: #94a3b8;
        }
        .update-meta img {
            width: 26px; height: 26px; border-radius: 50%; object-fit: cover;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 48px; margin-bottom: 14px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <?php if ($isOrganizer): ?>
            <a href="post_update.php?trip_id=<?php echo $tripId; ?>">+ Post Update</a>
        <?php endif; ?>
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
        <div class="header-img"></div>
        <div class="header-body">
            <div>
                <h1>📢 Trip Updates</h1>
                <p>
                    <strong><?php echo $tripTitle; ?></strong> ·
                    <?php echo htmlspecialchars($trip['destination']); ?> ·
                    <?php echo $updateCount; ?> update<?php echo $updateCount !== 1 ? 's' : ''; ?>
                </p>
            </div>
            <?php if ($isOrganizer): ?>
                <a href="post_update.php?trip_id=<?php echo $tripId; ?>" class="btn-post">
                    + Post Update
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline -->
    <?php if (empty($updates)): ?>
        <div class="empty-state">
            <div class="emoji">📭</div>
            <h2>No updates yet</h2>
            <p>
                <?php if ($isOrganizer): ?>
                    Post your first update to keep participants informed!
                <?php else: ?>
                    The trip organizer will post important updates here.
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>
        <div class="timeline">
            <?php foreach ($updates as $upd): ?>
                <?php
                    $type = $upd['update_type'];
                    $meta = $typeMeta[$type] ?? $typeMeta['info'];
                    $dot  = $meta['dot'];
                ?>
                <div class="update-item">
                    <!-- Timeline dot -->
                    <div class="update-dot" style="background:<?php echo $dot; ?>;"></div>

                    <!-- Type badge -->
                    <span class="type-badge <?php echo $meta['class']; ?>">
                        <?php echo $meta['label']; ?>
                    </span>

                    <!-- Update text -->
                    <div class="update-text">
                        <?php echo nl2br(htmlspecialchars($upd['update_text'])); ?>
                    </div>

                    <!-- Meta: poster + time -->
                    <div class="update-meta">
                        <img
                            src="<?php echo !empty($upd['poster_photo'])
                                ? '../uploads/profiles/' . htmlspecialchars($upd['poster_photo'])
                                : '../assets/default-avatar.png'; ?>"
                            alt="<?php echo htmlspecialchars($upd['poster_name']); ?>"
                        >
                        <span><?php echo htmlspecialchars($upd['poster_name']); ?></span>
                        <span>·</span>
                        <span>
                            <?php
                                $ts = strtotime($upd['created_at']);
                                echo date('M j, Y', $ts) === date('M j, Y')
                                    ? 'Today ' . date('g:i A', $ts)
                                    : date('M j, Y g:i A', $ts);
                            ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
