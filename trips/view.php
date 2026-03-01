<?php
// trips/view.php
// Task T14 — Trip detail page: full info, join button, participants, organizer card

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/User.php";
require_once "../includes/helpers.php";

// ── Resolve trip ──────────────────────────────────────────────────────────────
if (!isset($_GET['id'])) {
    redirect("search.php");
}

$tripId = (int) $_GET['id'];
$trip   = Trip::findById($tripId);

if (!$trip || $trip['status'] === 'deleted') {
    http_response_code(404);
    die("Trip not found.");
}

$loggedIn      = SessionManager::isActive();
$currentUserId = $loggedIn ? (int) SessionManager::get('user_id') : null;
$isOwner       = $currentUserId && (int) $trip['user_id'] === $currentUserId;

// ── Load participants ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.user_id, u.name, u.profile_photo
    FROM ft_trip_participants tp
    JOIN ft_users u ON tp.user_id = u.user_id
    WHERE tp.trip_id = ? AND tp.status = 'accepted'
    ORDER BY tp.joined_at ASC
");
$stmt->execute([$tripId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Check if current user already joined ─────────────────────────────────────
$hasJoined = false;
if ($currentUserId) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM ft_trip_participants WHERE trip_id = ? AND user_id = ?"
    );
    $stmt->execute([$tripId, $currentUserId]);
    $hasJoined = (bool) $stmt->fetchColumn();
}

$seatsAvailable = (int) $trip['max_travelers'] - (int) $trip['joined_travelers'];
$isFull         = $seatsAvailable <= 0;

// Duration in days
$durationDays = max(1, (int) ceil(
    (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / 86400
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($trip['title']); ?> — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* ── Nav ── */
        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
            position: relative; z-index: 20;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 14px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        /* ── Hero ── */
        .hero {
            height: 420px; background-size: cover; background-position: center;
            position: relative; display: flex; align-items: flex-end;
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.72));
        }
        .hero-content {
            position: relative; color: white; padding: 30px 30px 30px;
            max-width: 1200px; margin: 0 auto; width: 100%;
        }
        .trip-status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 12px;
            font-size: 13px; font-weight: 600; margin-bottom: 10px;
        }
        .status-open   { background: #22c55e; color: white; }
        .status-closed { background: #f59e0b; color: white; }
        .status-full   { background: #ef4444; color: white; }
        .hero h1 { font-size: 38px; font-weight: 800; margin-bottom: 8px; line-height: 1.2; }
        .hero-meta { font-size: 17px; opacity: 0.9; }

        /* ── Quick info bar ── */
        .quick-info {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
            max-width: 1200px; margin: -40px auto 0; padding: 0 20px;
            position: relative; z-index: 10;
        }
        .info-box {
            background: white; padding: 18px; border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.15); text-align: center;
        }
        .info-box .label { color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-box .value { font-size: 22px; font-weight: 800; color: #1d4ed8; margin-top: 6px; }

        /* ── Layout ── */
        .container {
            max-width: 1200px; margin: 30px auto; padding: 0 20px;
            display: grid; grid-template-columns: 2fr 1fr; gap: 26px;
        }

        /* ── Cards ── */
        .card {
            background: white; padding: 26px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09); margin-bottom: 22px;
        }
        .card h2 { color: #1d4ed8; margin-bottom: 16px; font-size: 20px; }

        /* Description */
        .description { line-height: 1.85; color: #374151; font-size: 15px; }

        /* Itinerary */
        .itinerary-day {
            padding: 14px 18px; border-left: 4px solid #1d4ed8;
            margin-bottom: 12px; background: #f8fafc; border-radius: 0 6px 6px 0;
        }
        .itinerary-day h3 { color: #1d4ed8; margin-bottom: 6px; font-size: 15px; }
        .itinerary-day p  { color: #374151; font-size: 14px; line-height: 1.6; }

        /* Included list */
        .included-list { list-style: none; line-height: 2; }
        .included-list li { font-size: 14px; color: #374151; }

        /* Participants */
        .participants-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 14px;
        }
        .participant { text-align: center; }
        .participant a { text-decoration: none; color: inherit; }
        .participant img {
            width: 60px; height: 60px; border-radius: 50%;
            object-fit: cover; margin-bottom: 6px;
            border: 2px solid #e5e7eb;
        }
        .participant:hover img { border-color: #1d4ed8; }
        .participant-name { font-size: 12px; color: #374151; }

        /* Flash messages */
        .alert {
            padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Action buttons */
        .btn {
            display: block; width: 100%; padding: 14px 16px;
            text-align: center; border-radius: 8px; border: none;
            font-size: 16px; font-weight: 700; cursor: pointer;
            text-decoration: none; margin-bottom: 10px;
            transition: opacity 0.2s;
        }
        .btn:last-child { margin-bottom: 0; }
        .btn:hover:not(:disabled) { opacity: 0.88; }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-primary   { background: #1d4ed8; color: white; }
        .btn-success   { background: #16a34a; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-danger    { background: #dc2626; color: white; }
        .btn-outline   { background: white; color: #1d4ed8; border: 2px solid #1d4ed8; }

        /* Organizer card */
        .organizer-row { display: flex; gap: 18px; align-items: flex-start; }
        .organizer-row img {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
            border: 3px solid #1d4ed8; flex-shrink: 0;
        }
        .organizer-row h3 { margin-bottom: 4px; }
        .organizer-row h3 a { color: #1d4ed8; text-decoration: none; }
        .organizer-row h3 a:hover { text-decoration: underline; }
        .organizer-since { color: #64748b; font-size: 13px; }

        /* Responsive */
        @media (max-width: 900px) {
            .container     { grid-template-columns: 1fr; }
            .quick-info    { grid-template-columns: repeat(2, 1fr); }
            .hero h1       { font-size: 26px; }
        }
        @media (max-width: 500px) {
            .quick-info { grid-template-columns: 1fr 1fr; margin: -30px auto 0; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="search.php">Browse Trips</a>
        <?php if ($loggedIn): ?>
            <a href="../profile/my_profile.php">Profile</a>
            <a href="../auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="../auth/login.php">Login</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ── Hero ── -->
<div class="hero" style="background-image: url('<?php echo !empty($trip['image'])
    ? '../uploads/trips/' . htmlspecialchars($trip['image'])
    : '../assets/default-trip.jpg'; ?>')">
    <div class="hero-content">
        <?php
        if ($isFull) {
            echo '<span class="trip-status-badge status-full">🔴 Trip Full</span>';
        } elseif ($trip['status'] === 'open') {
            echo '<span class="trip-status-badge status-open">🟢 Open for Joining</span>';
        } else {
            echo '<span class="trip-status-badge status-closed">🟡 ' . ucfirst($trip['status']) . '</span>';
        }
        ?>
        <h1><?php echo htmlspecialchars($trip['title']); ?></h1>
        <div class="hero-meta">
            📍 <?php echo htmlspecialchars($trip['destination']); ?>
            &nbsp;|&nbsp;
            📅 <?php echo date('M j', strtotime($trip['start_date'])); ?> –
               <?php echo date('M j, Y', strtotime($trip['end_date'])); ?>
        </div>
    </div>
</div>

<!-- ── Quick Info Bar ── -->
<div class="quick-info">
    <div class="info-box">
        <div class="label">Budget</div>
        <div class="value">₹<?php echo number_format((float) $trip['budget_per_person']); ?></div>
    </div>
    <div class="info-box">
        <div class="label">Duration</div>
        <div class="value"><?php echo $durationDays; ?> Day<?php echo $durationDays > 1 ? 's' : ''; ?></div>
    </div>
    <div class="info-box">
        <div class="label">Seats Left</div>
        <div class="value"><?php echo $seatsAvailable; ?> / <?php echo (int) $trip['max_travelers']; ?></div>
    </div>
    <div class="info-box">
        <div class="label">Trip Type</div>
        <div class="value" style="font-size:18px;"><?php echo htmlspecialchars(ucfirst($trip['trip_type'])); ?></div>
    </div>
</div>

<!-- ── Body ── -->
<div class="container">

    <!-- ── Left: Main content ── -->
    <div class="main-content">

        <?php if (isset($_GET['success']) && $_GET['success'] === 'joined'): ?>
            <div class="alert alert-success">🎉 You've successfully joined this trip!</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php
                $e = $_GET['error'];
                if ($e === 'full')    echo "❌ This trip is full.";
                elseif ($e === 'owner')   echo "❌ You can't join your own trip.";
                elseif ($e === 'already') echo "❌ You've already joined this trip.";
                else                      echo "❌ Something went wrong. Please try again.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <div class="card">
            <h2>📝 About This Trip</h2>
            <div class="description">
                <?php echo nl2br(htmlspecialchars($trip['description'] ?? 'No description provided.')); ?>
            </div>
        </div>

        <!-- Itinerary -->
        <div class="card">
            <h2>🗓️ Itinerary</h2>
            <?php if (!empty($trip['itinerary'])): ?>
                <?php
                $itinerary = json_decode($trip['itinerary'], true);
                if (is_array($itinerary)) {
                    foreach ($itinerary as $day => $plan):
                ?>
                    <div class="itinerary-day">
                        <h3>Day <?php echo htmlspecialchars((string) $day); ?></h3>
                        <p><?php echo htmlspecialchars((string) $plan); ?></p>
                    </div>
                <?php endforeach; } else { ?>
                    <p style="color:#64748b;"><?php echo nl2br(htmlspecialchars($trip['itinerary'])); ?></p>
                <?php } ?>
            <?php else: ?>
                <p style="color:#64748b;">Itinerary details will be shared after joining.</p>
            <?php endif; ?>
        </div>

        <!-- What's included -->
        <div class="card">
            <h2>✅ What's Included</h2>
            <ul class="included-list">
                <?php if (!empty($trip['accommodation_type'])): ?>
                    <li>🏠 Accommodation: <?php echo htmlspecialchars($trip['accommodation_type']); ?></li>
                <?php else: ?>
                    <li>🏠 Shared accommodation</li>
                <?php endif; ?>
                <li>🚗 Local transportation</li>
                <?php if (!empty($trip['meals_included'])): ?>
                    <li>🍽️ <?php echo htmlspecialchars($trip['meals_included']); ?></li>
                <?php else: ?>
                    <li>🍽️ Breakfast included</li>
                <?php endif; ?>
                <li>🎯 All activities in the itinerary</li>
            </ul>
        </div>

        <!-- Participants -->
        <div class="card">
            <h2>👥 Who's Going (<?php echo count($participants); ?>)</h2>
            <?php if (empty($participants)): ?>
                <p style="color:#64748b;">Be the first to join this trip!</p>
            <?php else: ?>
                <div class="participants-grid">
                    <?php foreach ($participants as $p): ?>
                        <div class="participant">
                            <a href="../profile/public_profile.php?user_id=<?php echo (int) $p['user_id']; ?>">
                                <img
                                    src="<?php echo !empty($p['profile_photo'])
                                        ? '../uploads/profiles/' . htmlspecialchars($p['profile_photo'])
                                        : '../assets/default-avatar.png'; ?>"
                                    alt="<?php echo htmlspecialchars($p['name']); ?>"
                                >
                                <div class="participant-name"><?php echo htmlspecialchars($p['name']); ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.main-content -->

    <!-- ── Right: Sidebar ── -->
    <aside class="sidebar">

        <!-- Action Buttons -->
        <div class="card">
            <?php if (!$loggedIn): ?>
                <a href="../auth/login.php?redirect=trips/view.php?id=<?php echo $tripId; ?>"
                   class="btn btn-primary">🔑 Login to Join</a>
            <?php elseif ($isOwner): ?>
                <a href="edit.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">✏️ Edit Trip</a>
                <a href="delete.php?id=<?php echo $tripId; ?>" class="btn btn-danger"
                   onclick="return confirm('Delete this trip?');">🗑️ Delete Trip</a>
                <p style="color:#16a34a; font-size:14px; text-align:center; margin-top:8px;">
                    ✅ You are the organizer
                </p>
            <?php elseif ($hasJoined): ?>
                <button class="btn btn-success" disabled>✅ Already Joined</button>
            <?php elseif ($isFull): ?>
                <button class="btn btn-secondary" disabled>🔴 Trip Full</button>
            <?php else: ?>
                <form action="join.php" method="POST">
                    <?php // CSRF would be added here once CSRF is loaded in scope ?>
                    <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                    <button type="submit" class="btn btn-primary">🤝 Join This Trip</button>
                </form>
            <?php endif; ?>

            <?php if ($loggedIn && !$isOwner): ?>
                <a href="../messages/new.php?to=<?php echo (int) $trip['user_id']; ?>"
                   class="btn btn-outline">✉️ Message Organizer</a>
            <?php endif; ?>
        </div>

        <!-- Organizer Card -->
        <div class="card">
            <h2>🧑 Organized By</h2>
            <div class="organizer-row">
                <img
                    src="<?php echo !empty($trip['organizer_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($trip['organizer_photo'])
                        : '../assets/default-avatar.png'; ?>"
                    alt="Organizer"
                >
                <div>
                    <h3>
                        <a href="../profile/public_profile.php?user_id=<?php echo (int) $trip['user_id']; ?>">
                            <?php echo htmlspecialchars($trip['organizer_name']); ?>
                        </a>
                    </h3>
                    <?php if (!empty($trip['organizer_username'])): ?>
                        <div style="color:#64748b; font-size:13px;">
                            @<?php echo htmlspecialchars($trip['organizer_username']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($trip['organizer_verified'])): ?>
                        <span style="color:#0ea5e9; font-size:13px;">✓ Verified</span>
                    <?php endif; ?>
                    <div class="organizer-since">
                        Member since <?php echo date('Y', strtotime($trip['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share & Report -->
        <div class="card">
            <h2>⋯ More Options</h2>
            <button class="btn btn-outline"
                    onclick="navigator.clipboard?.writeText(window.location.href).then(()=>alert('Link copied!'))">
                📤 Share Trip
            </button>
            <?php if ($loggedIn && !$isOwner): ?>
                <a href="../reports/report_trip.php?id=<?php echo $tripId; ?>"
                   class="btn btn-danger" style="font-size:14px;">
                    🚩 Report Trip
                </a>
            <?php endif; ?>
        </div>

    </aside><!-- /.sidebar -->

</div><!-- /.container -->
</body>
</html>
