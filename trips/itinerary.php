<?php
// trips/itinerary.php
// Task T25 — Day-by-day itinerary timeline; organizer can add/edit/delete activities

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripItinerary.php";
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
$viewerId    = (int) SessionManager::get('user_id');
$isOrganizer = $viewerId && ((int) $trip['user_id'] === $viewerId);

// ── Fetch Itinerary ───────────────────────────────────────────────────────────
$itinerary    = TripItinerary::getItineraryByDay($tripId);
$totalActivities = TripItinerary::countForTrip($tripId);

// ── Trip dates for display ────────────────────────────────────────────────────
$startTs = $trip['start_date'] ? strtotime($trip['start_date']) : null;

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash     = null;
$flashType = null;
$flashMap  = [
    'added'   => ['✅ Activity added!',              'success'],
    'updated' => ['✅ Activity updated!',            'success'],
    'deleted' => ['✅ Activity deleted.',            'success'],
    'failed'  => ['❌ Something went wrong.',        'error'],
    'permission' => ['❌ Only the organizer can manage the itinerary.', 'error'],
];
$sKey = $_GET['success'] ?? '';
$eKey = $_GET['error']   ?? '';
if ($sKey && isset($flashMap[$sKey])) { [$flash, $flashType] = $flashMap[$sKey]; }
elseif ($eKey && isset($flashMap[$eKey])) { [$flash, $flashType] = $flashMap[$eKey]; }

$csrfField   = CSRF::getTokenField();
$tripTitle   = htmlspecialchars($trip['title']);
$categoryMeta = TripItinerary::getCategoryMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itinerary — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 820px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 22px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 28px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .header-card h1 { color: #1d4ed8; font-size: 21px; margin-bottom: 4px; }
        .header-card p  { color: #64748b; font-size: 14px; }
        .btn-add {
            background: #1d4ed8; color: white; text-decoration: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px;
            white-space: nowrap; transition: background 0.2s;
        }
        .btn-add:hover { background: #1e40af; }

        /* ── Day Section ── */
        .day-section {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 28px; overflow: hidden;
        }
        .day-header {
            background: #eff6ff; padding: 16px 24px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 2px solid #dbeafe;
        }
        .day-header h2 { color: #1d4ed8; font-size: 17px; }
        .day-date      { color: #64748b; font-size: 13px; }
        .day-count     { color: #64748b; font-size: 13px; }

        /* ── Activity Timeline ── */
        .activity-timeline { padding: 20px 24px 8px 70px; position: relative; }
        .activity-timeline::before {
            content: ''; position: absolute;
            left: 36px; top: 0; bottom: 0;
            width: 2px; background: #e5e7eb;
        }

        /* ── Activity Item ── */
        .activity-item {
            position: relative; margin-bottom: 28px;
        }
        .activity-dot {
            position: absolute; left: -42px; top: 4px;
            width: 16px; height: 16px; border-radius: 50%;
            background: #1d4ed8; border: 3px solid white;
            box-shadow: 0 0 0 2px #1d4ed8;
        }
        .activity-time {
            color: #1d4ed8; font-weight: 700; font-size: 13px; margin-bottom: 4px;
        }
        .activity-title {
            font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 4px;
        }
        .activity-location {
            color: #64748b; font-size: 13px; margin-bottom: 6px;
        }
        .activity-description {
            color: #374151; font-size: 14px; line-height: 1.6; margin-bottom: 8px;
        }
        .cat-badge {
            display: inline-block; padding: 3px 10px; border-radius: 10px;
            font-size: 11px; font-weight: 700;
        }
        .cat-sightseeing   { background: #dbeafe; color: #1e40af; }
        .cat-food          { background: #fef3c7; color: #92400e; }
        .cat-transport     { background: #e5e7eb; color: #374151; }
        .cat-activity      { background: #dcfce7; color: #166534; }
        .cat-accommodation { background: #f3e8ff; color: #6b21a8; }

        .activity-actions { margin-top: 10px; display: flex; gap: 10px; }
        .btn-edit-sm {
            font-size: 12px; color: #1d4ed8; background: #eff6ff;
            border: none; padding: 4px 12px; border-radius: 5px; cursor: pointer;
            text-decoration: none; transition: background 0.15s;
        }
        .btn-edit-sm:hover { background: #dbeafe; }
        .btn-del-sm {
            font-size: 12px; color: #dc2626; background: none;
            border: none; padding: 0; cursor: pointer; text-decoration: underline;
        }
        .btn-del-sm:hover { color: #b91c1c; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 64px 20px;
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
        .empty-state .emoji { font-size: 52px; margin-bottom: 16px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="trip_feed.php?trip_id=<?php echo $tripId; ?>">Updates</a>
        <a href="expenses.php?trip_id=<?php echo $tripId; ?>">Expenses</a>
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
            <h1>📅 Trip Itinerary</h1>
            <p>
                <strong><?php echo $tripTitle; ?></strong> ·
                <?php echo $totalActivities; ?> activit<?php echo $totalActivities !== 1 ? 'ies' : 'y'; ?>
            </p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="view.php?id=<?php echo $tripId; ?>"
               style="color:#1d4ed8;text-decoration:none;font-size:14px;">← Back to Trip</a>
            <?php if ($isOrganizer): ?>
                <a href="add_activity.php?trip_id=<?php echo $tripId; ?>" class="btn-add">
                    ➕ Add Activity
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Itinerary Timeline -->
    <?php if (empty($itinerary)): ?>
        <div class="empty-state">
            <div class="emoji">📆</div>
            <h2>No itinerary yet</h2>
            <p>
                <?php if ($isOrganizer): ?>
                    Add activities to plan your trip day-by-day.
                <?php else: ?>
                    The trip organizer will add the itinerary here.
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>
        <?php foreach ($itinerary as $dayNum => $activities): ?>
            <?php
                // Compute real date for this day if trip has a start_date
                $dayLabel = "Day {$dayNum}";
                $dayDate  = '';
                if ($startTs) {
                    $dayTs   = strtotime("+{$dayOffset} days", $startTs);
                    $dayOffset = $dayNum - 1;
                    $dayTs   = strtotime("+{$dayOffset} days", $startTs);
                    $dayDate = date('M j, Y', $dayTs);
                }
            ?>
            <div class="day-section">
                <div class="day-header">
                    <div>
                        <h2><?php echo $dayLabel; ?></h2>
                        <?php if ($dayDate): ?>
                            <span class="day-date"><?php echo $dayDate; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="day-count">
                        <?php echo count($activities); ?> activit<?php echo count($activities) !== 1 ? 'ies' : 'y'; ?>
                    </span>
                </div>

                <div class="activity-timeline">
                    <?php foreach ($activities as $act): ?>
                        <?php
                            $cat     = $act['category'];
                            $catInfo = $categoryMeta[$cat] ?? $categoryMeta['activity'];
                            $timeStr = date('g:i A', strtotime($act['activity_time']));
                        ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div class="activity-time">⏰ <?php echo $timeStr; ?></div>
                            <div class="activity-title"><?php echo htmlspecialchars($act['activity_title']); ?></div>
                            <?php if (!empty($act['location'])): ?>
                                <div class="activity-location">
                                    📍 <?php echo htmlspecialchars($act['location']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($act['description'])): ?>
                                <div class="activity-description">
                                    <?php echo nl2br(htmlspecialchars($act['description'])); ?>
                                </div>
                            <?php endif; ?>
                            <span class="cat-badge <?php echo $catInfo['class']; ?>">
                                <?php echo $catInfo['label']; ?>
                            </span>
                            <?php if ($isOrganizer): ?>
                                <div class="activity-actions">
                                    <a href="edit_activity.php?id=<?php echo (int) $act['itinerary_id']; ?>"
                                       class="btn-edit-sm">✏️ Edit</a>
                                    <form method="POST" action="delete_activity.php" style="display:inline"
                                          onsubmit="return confirm('Delete this activity?')">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="itinerary_id" value="<?php echo (int) $act['itinerary_id']; ?>">
                                        <input type="hidden" name="trip_id"      value="<?php echo $tripId; ?>">
                                        <button type="submit" class="btn-del-sm">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
