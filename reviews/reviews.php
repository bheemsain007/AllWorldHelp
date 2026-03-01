<?php
// reviews/reviews.php
// Task T20 — View all reviews received by a user with average rating summary

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Review.php";
require_once "../models/User.php";
require_once "../models/Connection.php";
require_once "../includes/helpers.php";

$profileUserId = (int) ($_GET['user_id'] ?? 0);

if ($profileUserId <= 0) {
    redirect("../trips/search.php");
}

// ── Fetch User & Reviews ──────────────────────────────────────────────────────
$profileUser   = User::findById($profileUserId);
if (!$profileUser) {
    redirect("../trips/search.php?error=notfound");
}

$reviews       = Review::getReviewsForUser($profileUserId);
$avgRating     = Review::getAverageRating($profileUserId);
$totalReviews  = count($reviews);

// ── Current Viewer Context ────────────────────────────────────────────────────
$viewerId      = (int) SessionManager::get('user_id');
$isOwn         = ($viewerId === $profileUserId);
$canReview     = $viewerId && !$isOwn && Connection::areConnected($viewerId, $profileUserId);
$ownReview     = $viewerId && !$isOwn ? Review::findByUsers($viewerId, $profileUserId) : null;

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash     = null;
$flashType = null;
if ($_GET['success'] ?? '' === 'review_submitted') {
    $flash     = '✅ Review submitted successfully!';
    $flashType = 'success';
}

// ── Star helper ───────────────────────────────────────────────────────────────
function renderStars(float $rating, bool $large = false): string {
    $size  = $large ? '28px' : '18px';
    $out   = '';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= round($rating) ? '#fbbf24' : '#d1d5db';
        $out  .= "<span style=\"color:{$color};font-size:{$size};\">★</span>";
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews for <?php echo htmlspecialchars($profileUser['name']); ?> — Fellow Traveler</title>
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
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        /* ── Summary Card ── */
        .summary-card {
            background: white; padding: 28px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1); margin-bottom: 24px;
            display: flex; gap: 24px; align-items: center; flex-wrap: wrap;
        }
        .summary-avatar img {
            width: 80px; height: 80px; border-radius: 50%;
            object-fit: cover; border: 3px solid #e5e7eb;
        }
        .summary-info { flex: 1; }
        .summary-info h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 6px; }
        .summary-info .uname { color: #64748b; font-size: 14px; margin-bottom: 10px; }
        .avg-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .avg-number { font-size: 42px; font-weight: 700; color: #1e293b; line-height: 1; }
        .avg-detail { color: #64748b; font-size: 14px; margin-top: 4px; }
        .summary-actions { display: flex; flex-direction: column; gap: 8px; }
        .btn {
            display: inline-block; padding: 10px 20px; border-radius: 8px;
            font-size: 14px; font-weight: 600; text-decoration: none;
            text-align: center; cursor: pointer; border: none;
        }
        .btn-primary { background: #1d4ed8; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: white; color: #1d4ed8; border: 2px solid #1d4ed8; }
        .btn-secondary:hover { background: #eff6ff; }

        /* ── Review Card ── */
        .review-card {
            background: white; padding: 22px 24px; border-radius: 10px;
            margin-bottom: 14px; box-shadow: 0 2px 5px rgba(0,0,0,.07);
        }
        .review-header {
            display: flex; gap: 14px; align-items: center; margin-bottom: 14px;
        }
        .review-header img {
            width: 48px; height: 48px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb;
        }
        .reviewer-name { font-weight: 700; color: #1d4ed8; font-size: 15px; }
        .reviewer-name a { text-decoration: none; color: inherit; }
        .reviewer-name a:hover { text-decoration: underline; }
        .reviewer-meta { color: #64748b; font-size: 13px; margin-top: 3px; }
        .review-text {
            color: #374151; line-height: 1.65; font-size: 15px; margin-bottom: 10px;
        }
        .review-date { color: #94a3b8; font-size: 13px; }

        /* ── Your Review Banner ── */
        .own-review-banner {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
            font-size: 14px; color: #1e40af;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 48px; margin-bottom: 14px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../profile/public_profile.php?user_id=<?php echo $profileUserId; ?>">
            Back to Profile
        </a>
        <?php if ($viewerId): ?>
            <a href="../auth/logout.php">Logout</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary-card">
        <div class="summary-avatar">
            <img
                src="<?php echo !empty($profileUser['profile_photo'])
                    ? '../uploads/profiles/' . htmlspecialchars($profileUser['profile_photo'])
                    : '../assets/default-avatar.png'; ?>"
                alt="<?php echo htmlspecialchars($profileUser['name']); ?>"
            >
        </div>

        <div class="summary-info">
            <h1><?php echo htmlspecialchars($profileUser['name']); ?></h1>
            <div class="uname">@<?php echo htmlspecialchars($profileUser['username']); ?></div>
            <div class="avg-row">
                <?php if ($totalReviews > 0): ?>
                    <div class="avg-number"><?php echo number_format($avgRating, 1); ?></div>
                    <div>
                        <div><?php echo renderStars($avgRating, true); ?></div>
                        <div class="avg-detail">
                            Based on <?php echo $totalReviews; ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color:#64748b;">No reviews yet</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canReview): ?>
            <div class="summary-actions">
                <a href="write_review.php?user_id=<?php echo $profileUserId; ?>"
                   class="btn <?php echo $ownReview ? 'btn-secondary' : 'btn-primary'; ?>">
                    <?php echo $ownReview ? '✏️ Edit Your Review' : '⭐ Write Review'; ?>
                </a>
                <a href="../profile/public_profile.php?user_id=<?php echo $profileUserId; ?>"
                   class="btn btn-secondary">View Profile</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Your review banner -->
    <?php if ($ownReview): ?>
        <div class="own-review-banner">
            <span>
                You reviewed <?php echo htmlspecialchars($profileUser['name']); ?> on
                <?php echo date('M j, Y', strtotime($ownReview['created_at'])); ?> —
                <?php echo renderStars((float) $ownReview['rating']); ?>
            </span>
            <a href="write_review.php?user_id=<?php echo $profileUserId; ?>"
               class="btn btn-secondary" style="padding:6px 14px;font-size:13px;">Edit</a>
        </div>
    <?php endif; ?>

    <!-- Reviews List -->
    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="emoji">⭐</div>
            <h2>No reviews yet</h2>
            <p>
                <?php if ($canReview): ?>
                    Be the first to review <?php echo htmlspecialchars($profileUser['name']); ?>!
                <?php else: ?>
                    <?php echo htmlspecialchars($profileUser['name']); ?> has no reviews yet.
                <?php endif; ?>
            </p>
            <?php if ($canReview): ?>
                <a href="write_review.php?user_id=<?php echo $profileUserId; ?>"
                   class="btn btn-primary">Write the First Review</a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <img
                        src="<?php echo !empty($review['profile_photo'])
                            ? '../uploads/profiles/' . htmlspecialchars($review['profile_photo'])
                            : '../assets/default-avatar.png'; ?>"
                        alt="<?php echo htmlspecialchars($review['reviewer_name']); ?>"
                    >
                    <div>
                        <div class="reviewer-name">
                            <a href="../profile/public_profile.php?user_id=<?php echo (int) $review['reviewer_id']; ?>">
                                <?php echo htmlspecialchars($review['reviewer_name']); ?>
                            </a>
                        </div>
                        <div class="reviewer-meta">
                            <?php echo renderStars((float) $review['rating']); ?>
                            <span style="margin-left:6px;"><?php echo $review['rating']; ?>/5</span>
                        </div>
                    </div>
                </div>

                <div class="review-text">
                    <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                </div>

                <div class="review-date">
                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
