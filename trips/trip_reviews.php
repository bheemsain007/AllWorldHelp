<?php
// trips/trip_reviews.php
// Task T28 — View all ratings and reviews for a trip; summary card + individual reviews

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripRating.php";
require_once "../includes/helpers.php";

$tripId = (int) ($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    redirect("search.php");
}

$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

$userId      = (int) SessionManager::get('user_id');
$ratings     = TripRating::getRatingsForTrip($tripId);
$averages    = TripRating::getAverageRatings($tripId);
$totalRatings = (int) ($averages['total_ratings'] ?? 0);

// Can the current user rate?
$tripCompleted = strtotime($trip['end_date']) <= time();
$isParticipant = $userId && Trip::isParticipant($tripId, $userId);
$canRate       = $tripCompleted && $isParticipant;
$userRating    = $userId ? TripRating::findByUserAndTrip($userId, $tripId) : null;

// Flash
$flash     = null;
$flashType = 'success';
if (!empty($_GET['success']) && $_GET['success'] === 'rated') {
    $flash = '✅ Your rating has been saved!';
}

$tripTitle = htmlspecialchars($trip['title']);

// Helper: render star bar
function starBar(float $val, int $max = 5): string {
    $out = '';
    for ($i = 1; $i <= $max; $i++) {
        $out .= '<span style="color:' . ($i <= round($val) ? '#f59e0b' : '#d1d5db') . ';font-size:18px;">★</span>';
    }
    return $out;
}

$categoryLabels = [
    'organization'  => 'Organization',
    'communication' => 'Communication',
    'value'         => 'Value for Money',
    'experience'    => 'Experience',
    'recommend'     => 'Would Recommend',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — <?php echo $tripTitle; ?></title>
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

        .container { max-width: 820px; margin: 36px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash {
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
            font-size: 15px; border: 1px solid;
        }
        .flash.success { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

        /* ── Header card ── */
        .header-card {
            background: white; padding: 24px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;
        }
        .header-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 4px; }
        .header-card .sub { color: #64748b; font-size: 14px; }
        .btn-rate {
            background: #1d4ed8; color: white; text-decoration: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px;
            white-space: nowrap;
        }
        .btn-rate:hover { background: #1e40af; }
        .btn-rate.edit  { background: #0891b2; }
        .btn-rate.edit:hover { background: #0e7490; }

        /* ── Summary card ── */
        .summary-card {
            background: white; padding: 24px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
        }
        .summary-card h2 { color: #1e293b; font-size: 18px; margin-bottom: 20px; }

        .overall-score {
            display: flex; align-items: center; gap: 16px; margin-bottom: 24px;
        }
        .score-big { font-size: 52px; font-weight: 900; color: #f59e0b; line-height: 1; }
        .score-label { color: #64748b; font-size: 14px; margin-top: 4px; }

        .cat-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .cat-item { }
        .cat-name { font-size: 13px; color: #374151; font-weight: 600; margin-bottom: 4px; }
        .cat-bar-wrap {
            display: flex; align-items: center; gap: 8px;
        }
        .cat-bar-bg {
            flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;
        }
        .cat-bar-fill {
            height: 100%; background: #f59e0b; border-radius: 4px;
        }
        .cat-val { font-size: 13px; color: #64748b; white-space: nowrap; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
        .empty-state .emoji { font-size: 48px; margin-bottom: 14px; }
        .empty-state h3 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; font-size: 14px; }

        /* ── Review card ── */
        .review-card {
            background: white; padding: 22px 24px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 16px;
        }
        .review-header {
            display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
        }
        .reviewer-photo {
            width: 44px; height: 44px; border-radius: 50%; object-fit: cover;
            flex-shrink: 0; background: #e5e7eb;
        }
        .reviewer-name { font-weight: 700; color: #1e293b; font-size: 15px; }
        .review-date   { color: #94a3b8; font-size: 12px; margin-top: 2px; }
        .overall-stars { margin-left: auto; font-size: 20px; }

        .mini-ratings {
            display: flex; flex-wrap: wrap; gap: 8px 20px;
            margin-bottom: 12px;
        }
        .mini-rating {
            font-size: 12px; color: #64748b;
        }
        .mini-rating strong { color: #374151; }

        .review-text {
            font-size: 14px; line-height: 1.7; color: #374151;
            border-top: 1px solid #f1f5f9; padding-top: 10px; margin-top: 10px;
        }

        /* ── Responsive ── */
        @media (max-width: 540px) {
            .overall-score { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
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
            <h1>⭐ Trip Reviews</h1>
            <div class="sub">
                <strong><?php echo $tripTitle; ?></strong> ·
                <?php echo $totalRatings; ?> review<?php echo $totalRatings !== 1 ? 's' : ''; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="view.php?id=<?php echo $tripId; ?>"
               style="color:#64748b;text-decoration:none;font-size:14px;">← Back</a>
            <?php if ($canRate): ?>
                <a href="rate_trip.php?trip_id=<?php echo $tripId; ?>"
                   class="btn-rate <?php echo $userRating ? 'edit' : ''; ?>">
                    <?php echo $userRating ? '✏️ Edit My Rating' : '⭐ Rate This Trip'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalRatings > 0 && $averages): ?>
    <!-- Summary card -->
    <div class="summary-card">
        <h2>📊 Overall Ratings Summary</h2>

        <div class="overall-score">
            <div class="score-big"><?php echo number_format((float)$averages['avg_overall'], 1); ?></div>
            <div>
                <div><?php echo starBar((float)$averages['avg_overall']); ?></div>
                <div class="score-label">out of 5 · <?php echo $totalRatings; ?> review<?php echo $totalRatings !== 1 ? 's' : ''; ?></div>
            </div>
        </div>

        <div class="cat-grid">
            <?php foreach ($categoryLabels as $key => $label): ?>
                <?php
                $avg = (float) ($averages['avg_' . $key] ?? 0);
                $pct = ($avg / 5) * 100;
                ?>
                <div class="cat-item">
                    <div class="cat-name"><?php echo $label; ?></div>
                    <div class="cat-bar-wrap">
                        <div class="cat-bar-bg">
                            <div class="cat-bar-fill" style="width:<?php echo round($pct); ?>%"></div>
                        </div>
                        <div class="cat-val"><?php echo number_format($avg, 1); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($totalRatings === 0): ?>
    <div class="empty-state">
        <div class="emoji">⭐</div>
        <h3>No reviews yet</h3>
        <p>
            <?php if ($canRate): ?>
                Be the first to share your experience!
            <?php elseif (!$tripCompleted): ?>
                Reviews will be available after the trip ends.
            <?php else: ?>
                No ratings have been submitted yet.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Individual reviews -->
    <?php foreach ($ratings as $r): ?>
        <div class="review-card">
            <div class="review-header">
                <img class="reviewer-photo"
                     src="<?php echo !empty($r['reviewer_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($r['reviewer_photo'])
                        : '../assets/default-avatar.jpg'; ?>"
                     alt="<?php echo htmlspecialchars($r['reviewer_name']); ?>">
                <div>
                    <div class="reviewer-name"><?php echo htmlspecialchars($r['reviewer_name']); ?></div>
                    <div class="review-date"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></div>
                </div>
                <div class="overall-stars">
                    <?php echo starBar((float) $r['overall_rating']); ?>
                    <div style="text-align:right;font-size:12px;color:#64748b;margin-top:2px;">
                        <?php echo number_format((float)$r['overall_rating'], 1); ?>/5
                    </div>
                </div>
            </div>

            <div class="mini-ratings">
                <?php foreach ($categoryLabels as $key => $label): ?>
                    <div class="mini-rating">
                        <strong><?php echo $label; ?>:</strong>
                        <?php echo (int) $r[$key . '_rating']; ?>/5
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($r['review_text'])): ?>
                <div class="review-text">
                    <?php echo nl2br(htmlspecialchars($r['review_text'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</div><!-- /.container -->
</body>
</html>
