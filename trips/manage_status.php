<?php
// trips/manage_status.php
// Task T29 — Status management dashboard (organizer only); shows current status + available actions

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_GET['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// Organizer only
if ((int) $trip['user_id'] !== $userId) {
    redirect("view.php?id={$tripId}&error=permission");
}

$currentStatus = $trip['status'];

// Flash messages
$flash     = null;
$flashType = 'success';
$flashMap  = [
    'update_failed'  => ['❌ Failed to update status. Please try again.', 'error'],
    'invalid_status' => ['❌ That status transition is not allowed.',     'error'],
];
if (!empty($_GET['error']) && isset($flashMap[$_GET['error']])) {
    [$flash, $flashType] = $flashMap[$_GET['error']];
}

$csrfField = CSRF::getTokenField();
$tripTitle = htmlspecialchars($trip['title']);

// Status meta: label + badge class
$statusMeta = [
    'open'      => ['🟢 Open',      'open'],
    'full'      => ['🟡 Full',      'full'],
    'closed'    => ['🔴 Closed',    'closed'],
    'ongoing'   => ['🔵 Ongoing',   'ongoing'],
    'completed' => ['⚫ Completed',  'completed'],
    'cancelled' => ['❌ Cancelled', 'cancelled'],
];
[$statusLabel, $statusClass] = $statusMeta[$currentStatus] ?? [$currentStatus, 'open'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Status — <?php echo $tripTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        .container { max-width: 760px; margin: 36px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash {
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
            font-size: 15px; border: 1px solid;
        }
        .flash.success { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

        /* ── Current status card ── */
        .status-card {
            background: white; padding: 28px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 28px;
            text-align: center;
        }
        .status-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 6px; }
        .status-card .trip-name { color: #64748b; font-size: 14px; margin-bottom: 20px; }

        .status-badge {
            display: inline-block; padding: 8px 24px; border-radius: 20px;
            font-size: 18px; font-weight: 700; margin-bottom: 12px;
        }
        .status-badge.open      { background: #dcfce7; color: #166534; }
        .status-badge.full      { background: #fef3c7; color: #92400e; }
        .status-badge.closed    { background: #fee2e2; color: #991b1b; }
        .status-badge.ongoing   { background: #dbeafe; color: #1e40af; }
        .status-badge.completed { background: #e5e7eb; color: #374151; }
        .status-badge.cancelled { background: #fce7f3; color: #9d174d; }

        /* ── Action cards ── */
        .section-title { font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 14px; }
        .action-card {
            background: white; padding: 22px 24px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 14px;
            border-left: 4px solid #1d4ed8;
        }
        .action-card.danger { border-color: #dc2626; }
        .action-card.success-border { border-color: #16a34a; }

        .action-title { font-weight: 700; font-size: 16px; color: #1e293b; margin-bottom: 6px; }
        .action-desc  { color: #64748b; font-size: 13px; margin-bottom: 14px; line-height: 1.5; }

        .btn-action {
            padding: 10px 22px; border: none; border-radius: 7px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: opacity 0.2s; color: white;
        }
        .btn-action:hover { opacity: 0.85; }
        .btn-blue  { background: #1d4ed8; }
        .btn-green { background: #16a34a; }
        .btn-red   { background: #dc2626; }
        .btn-yellow { background: #d97706; }

        .btn-link {
            display: inline-block; padding: 10px 22px; border-radius: 7px;
            font-size: 14px; font-weight: 700; text-decoration: none; color: white;
            transition: opacity 0.2s;
        }
        .btn-link:hover { opacity: 0.85; }

        /* ── No actions ── */
        .no-actions {
            background: white; padding: 28px; border-radius: 12px;
            text-align: center; color: #64748b; font-size: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="edit.php?id=<?php echo $tripId; ?>">Edit Trip</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Current status -->
    <div class="status-card">
        <h1>🔄 Trip Status Management</h1>
        <div class="trip-name"><?php echo $tripTitle; ?></div>
        <div class="status-badge <?php echo htmlspecialchars($statusClass); ?>">
            <?php echo htmlspecialchars($statusLabel); ?>
        </div>
        <div style="color:#64748b;font-size:13px;">
            📅 <?php echo date('M j, Y', strtotime($trip['start_date'])); ?>
            – <?php echo date('M j, Y', strtotime($trip['end_date'])); ?>
        </div>
    </div>

    <div class="section-title">Available Actions</div>

    <?php $hasActions = false; ?>

    <!-- Mark as Ongoing (open → ongoing) -->
    <?php if ($currentStatus === 'open' || $currentStatus === 'closed' || $currentStatus === 'full'): ?>
        <?php $hasActions = true; ?>
        <div class="action-card success-border">
            <div class="action-title">🔵 Mark as Ongoing</div>
            <div class="action-desc">Trip has started. Participants cannot join anymore.</div>
            <form method="POST" action="change_status.php">
                <?php echo $csrfField; ?>
                <input type="hidden" name="trip_id"    value="<?php echo $tripId; ?>">
                <input type="hidden" name="new_status" value="ongoing">
                <button type="submit" class="btn-action btn-blue">Mark as Ongoing</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Mark as Completed (ongoing → completed) -->
    <?php if ($currentStatus === 'ongoing'): ?>
        <?php $hasActions = true; ?>
        <div class="action-card success-border">
            <div class="action-title">✅ Mark as Completed</div>
            <div class="action-desc">Trip has finished successfully. Participants will be notified and can rate the trip.</div>
            <form method="POST" action="mark_completed.php">
                <?php echo $csrfField; ?>
                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                <button type="submit" class="btn-action btn-green">Mark as Completed</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Close Registration (open → closed) -->
    <?php if ($currentStatus === 'open'): ?>
        <?php $hasActions = true; ?>
        <div class="action-card">
            <div class="action-title">🔒 Close Registration</div>
            <div class="action-desc">Stop accepting new participants. Current participants remain.</div>
            <form method="POST" action="change_status.php">
                <?php echo $csrfField; ?>
                <input type="hidden" name="trip_id"    value="<?php echo $tripId; ?>">
                <input type="hidden" name="new_status" value="closed">
                <button type="submit" class="btn-action btn-yellow">Close Registration</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Reopen Registration (closed/full → open) -->
    <?php if ($currentStatus === 'closed' || $currentStatus === 'full'): ?>
        <?php $hasActions = true; ?>
        <div class="action-card success-border">
            <div class="action-title">🔓 Reopen Registration</div>
            <div class="action-desc">Allow new participants to join again.</div>
            <form method="POST" action="change_status.php">
                <?php echo $csrfField; ?>
                <input type="hidden" name="trip_id"    value="<?php echo $tripId; ?>">
                <input type="hidden" name="new_status" value="open">
                <button type="submit" class="btn-action btn-green">Reopen Registration</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Cancel Trip (any non-terminal status) -->
    <?php if (!in_array($currentStatus, ['completed', 'cancelled'])): ?>
        <?php $hasActions = true; ?>
        <div class="action-card danger">
            <div class="action-title">❌ Cancel Trip</div>
            <div class="action-desc">Permanently cancel this trip. All participants will be notified. This cannot be undone.</div>
            <a href="cancel_trip.php?trip_id=<?php echo $tripId; ?>"
               class="btn-link btn-red">Cancel This Trip</a>
        </div>
    <?php endif; ?>

    <?php if (!$hasActions): ?>
        <div class="no-actions">
            No actions available for a <strong><?php echo htmlspecialchars($statusLabel); ?></strong> trip.
        </div>
    <?php endif; ?>

    <div style="margin-top:18px;">
        <a href="view.php?id=<?php echo $tripId; ?>"
           style="color:#64748b;text-decoration:none;font-size:14px;">← Back to Trip</a>
    </div>

</div><!-- /.container -->
</body>
</html>
