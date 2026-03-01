<?php
// trips/rate_trip.php
// Task T28 — Star-rating form for completed trips (participants only; GET shows form, POST in submit_rating.php)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripRating.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_GET['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Guard: Trip must be completed (end_date in the past) ──────────────────────
if (strtotime($trip['end_date']) > time()) {
    redirect("view.php?id={$tripId}&error=not_completed");
}

// ── Guard: Only participants can rate ────────────────────────────────────────
if (!Trip::isParticipant($tripId, $userId)) {
    redirect("view.php?id={$tripId}&error=not_participant");
}

// ── Existing rating (for edit mode) ──────────────────────────────────────────
$existing = TripRating::findByUserAndTrip($userId, $tripId);

$csrfField = CSRF::getTokenField();
$tripTitle = htmlspecialchars($trip['title']);
$isEdit    = $existing !== null;

// Flash error from redirect
$flashError = null;
$errMap = [
    'invalid_rating'  => '❌ All ratings must be between 1 and 5 stars.',
    'not_completed'   => '❌ You can only rate trips after they are completed.',
    'not_participant' => '❌ Only trip participants can submit ratings.',
    'failed'          => '❌ Failed to save rating. Please try again.',
];
if (!empty($_GET['error']) && isset($errMap[$_GET['error']])) {
    $flashError = $errMap[$_GET['error']];
}

// Rating categories definition
$categories = [
    'organization'  => ['label' => 'Organization',     'desc' => 'How well was the trip planned and executed?'],
    'communication' => ['label' => 'Communication',    'desc' => 'How clear and timely was organizer communication?'],
    'value'         => ['label' => 'Value for Money',  'desc' => 'Was the trip worth the cost?'],
    'experience'    => ['label' => 'Overall Experience','desc' => 'How much did you enjoy the trip?'],
    'recommend'     => ['label' => 'Would Recommend',  'desc' => 'Would you recommend this organizer to others?'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit Rating' : 'Rate Trip'; ?> — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 680px; margin: 36px auto; padding: 0 20px; }

        /* ── Alert ── */
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }

        /* ── Trip banner ── */
        .trip-banner {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 24px;
            font-size: 14px; color: #1e3a8a;
        }
        .trip-banner strong { font-size: 15px; }

        /* ── Rating Card ── */
        .rating-card {
            background: white; padding: 28px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1); margin-bottom: 20px;
        }
        .rating-card h2 { color: #1d4ed8; font-size: 16px; margin-bottom: 6px; }
        .rating-card p  { color: #64748b; font-size: 13px; margin-bottom: 14px; }

        /* ── Stars ── */
        .stars {
            display: flex; gap: 8px; font-size: 34px; cursor: pointer;
            margin-bottom: 8px;
        }
        .star { color: #d1d5db; transition: color 0.12s; user-select: none; line-height: 1; }
        .star.selected { color: #f59e0b; }

        /* ── Review textarea ── */
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
            resize: vertical; min-height: 110px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #1d4ed8; }

        /* ── Submit ── */
        .btn-submit {
            width: 100%; padding: 14px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 17px; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #1e40af; }

        /* ── Back link ── */
        .back-link {
            display: inline-block; margin-top: 14px; color: #64748b;
            text-decoration: none; font-size: 14px;
        }
        .back-link:hover { color: #1d4ed8; }

        .page-title { color: #1d4ed8; font-size: 24px; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="trip_reviews.php?trip_id=<?php echo $tripId; ?>">Reviews</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flashError): ?>
        <div class="alert-error"><?php echo $flashError; ?></div>
    <?php endif; ?>

    <div class="trip-banner">
        ⭐ <?php echo $isEdit ? 'Editing your rating for' : 'Rate your experience on'; ?>:
        <strong><?php echo $tripTitle; ?></strong>
        <span style="float:right;color:#64748b;">📍 <?php echo htmlspecialchars($trip['destination']); ?></span>
    </div>

    <h1 class="page-title"><?php echo $isEdit ? '✏️ Edit Your Rating' : '⭐ Rate This Trip'; ?></h1>

    <form method="POST" action="submit_rating.php" id="ratingForm">
        <?php echo $csrfField; ?>
        <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

        <?php foreach ($categories as $key => $cat): ?>
            <?php $existingVal = $existing ? (int) $existing[$key . '_rating'] : 0; ?>
            <div class="rating-card">
                <h2><?php echo htmlspecialchars($cat['label']); ?></h2>
                <p><?php echo htmlspecialchars($cat['desc']); ?></p>
                <div class="stars" data-key="<?php echo $key; ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?php echo $i <= $existingVal ? 'selected' : ''; ?>"
                              data-value="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden"
                       name="<?php echo $key; ?>_rating"
                       id="input_<?php echo $key; ?>"
                       value="<?php echo $existingVal > 0 ? $existingVal : ''; ?>"
                       required>
            </div>
        <?php endforeach; ?>

        <!-- Written review -->
        <div class="rating-card">
            <h2>📝 Your Review <span style="font-weight:400;color:#94a3b8;font-size:13px;">(optional)</span></h2>
            <p>Share your experience with the community.</p>
            <textarea name="review_text" maxlength="500"
                placeholder="What went well? What could be improved? Would you recommend this trip?"
            ><?php echo $existing ? htmlspecialchars($existing['review_text']) : ''; ?></textarea>
        </div>

        <button type="submit" class="btn-submit">
            <?php echo $isEdit ? '💾 Update Rating' : '⭐ Submit Rating'; ?>
        </button>
    </form>

    <a href="view.php?id=<?php echo $tripId; ?>" class="back-link">← Back to Trip</a>

</div><!-- /.container -->

<script>
document.querySelectorAll('.stars').forEach(function(container) {
    const key    = container.dataset.key;
    const stars  = container.querySelectorAll('.star');
    const input  = document.getElementById('input_' + key);

    function paintStars(count) {
        stars.forEach(function(s, idx) {
            s.classList.toggle('selected', idx < count);
        });
    }

    stars.forEach(function(star) {
        // Hover preview
        star.addEventListener('mouseenter', function() {
            paintStars(parseInt(this.dataset.value));
        });

        // Click to set
        star.addEventListener('click', function() {
            const val = parseInt(this.dataset.value);
            input.value = val;
            paintStars(val);
        });
    });

    // Restore selected state on mouse leave
    container.addEventListener('mouseleave', function() {
        paintStars(parseInt(input.value) || 0);
    });
});

// Validate all ratings are set before submit
document.getElementById('ratingForm').addEventListener('submit', function(e) {
    const keys = ['organization', 'communication', 'value', 'experience', 'recommend'];
    for (const k of keys) {
        const v = parseInt(document.getElementById('input_' + k).value);
        if (!v || v < 1 || v > 5) {
            e.preventDefault();
            alert('Please rate all 5 categories before submitting.');
            return;
        }
    }
});
</script>
</body>
</html>
