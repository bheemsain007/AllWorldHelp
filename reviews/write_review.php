<?php
// reviews/write_review.php
// Task T20 — Write or edit a review for another user (must be a connection)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Review.php";
require_once "../models/User.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$reviewerId = (int) SessionManager::get('user_id');
$revieweeId = (int) ($_GET['user_id'] ?? 0);

if ($revieweeId <= 0) {
    redirect("../trips/search.php");
}

// ── Guards ────────────────────────────────────────────────────────────────────
if ($reviewerId === $revieweeId) {
    redirect("../profile/my_profile.php?error=self_review");
}

$reviewee = User::findById($revieweeId);
if (!$reviewee) {
    redirect("../trips/search.php?error=notfound");
}

if (!Connection::areConnected($reviewerId, $revieweeId)) {
    redirect("../profile/public_profile.php?user_id={$revieweeId}&error=not_connected");
}

// ── Existing Review? (edit mode) ──────────────────────────────────────────────
$existingReview = Review::findByUsers($reviewerId, $revieweeId);
$isEdit         = (bool) $existingReview;

// ── Flash (from submit redirect) ─────────────────────────────────────────────
$flashMap = [
    'invalid_rating' => ['❌ Please select a rating (1–5 stars).', 'error'],
    'invalid_review' => ['❌ Review must be between 20 and 500 characters.', 'error'],
    'not_connected'  => ['❌ You can only review your connections.', 'error'],
    'failed'         => ['❌ Something went wrong. Please try again.', 'error'],
];
$flash     = null;
$flashType = null;
$eKey      = $_GET['error'] ?? '';
if ($eKey && isset($flashMap[$eKey])) {
    [$flash, $flashType] = $flashMap[$eKey];
}

$csrfField    = CSRF::getTokenField();
$initRating   = $existingReview ? (int) $existingReview['rating'] : 0;
$initText     = $existingReview ? htmlspecialchars($existingReview['review_text']) : '';
$revieweeName = htmlspecialchars($reviewee['name']);
$revieweeUser = htmlspecialchars($reviewee['username']);
$revieweePhoto = !empty($reviewee['profile_photo'])
    ? '../uploads/profiles/' . htmlspecialchars($reviewee['profile_photo'])
    : '../assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit Review' : 'Write Review'; ?> — Fellow Traveler</title>
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
        .container { max-width: 620px; margin: 36px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Form Card ── */
        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #1d4ed8; font-size: 24px; margin-bottom: 24px; }

        /* ── Reviewee info block ── */
        .user-info {
            display: flex; gap: 16px; align-items: center;
            padding: 16px 20px; background: #f8fafc;
            border-radius: 10px; margin-bottom: 28px;
            border: 1px solid #e5e7eb;
        }
        .user-info img {
            width: 60px; height: 60px; border-radius: 50%;
            object-fit: cover; border: 2px solid #e5e7eb;
        }
        .user-info h3 { color: #1d4ed8; font-size: 17px; margin-bottom: 3px; }
        .user-info p  { color: #64748b; font-size: 14px; }

        /* ── Form Elements ── */
        .form-group { margin-bottom: 24px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #374151; }
        .required { color: #dc2626; }

        /* ── Star Rating ── */
        .star-row { display: flex; gap: 8px; }
        .star {
            font-size: 44px; cursor: pointer; color: #d1d5db;
            transition: color 0.15s, transform 0.1s; user-select: none;
        }
        .star:hover  { transform: scale(1.15); }
        .star.active { color: #fbbf24; }
        .rating-label {
            margin-top: 8px; font-size: 14px; color: #64748b; min-height: 20px;
        }

        /* ── Textarea ── */
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
            resize: vertical; min-height: 120px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #1d4ed8; }
        .char-info {
            display: flex; justify-content: space-between;
            margin-top: 6px; font-size: 13px; color: #94a3b8;
        }
        .char-info .count.warn { color: #f59e0b; }
        .char-info .count.min  { color: #dc2626; }

        /* ── Submit Button ── */
        .btn-submit {
            width: 100%; padding: 14px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 17px;
            font-weight: 700; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #1e40af; }
        .btn-submit:disabled { background: #93c5fd; cursor: not-allowed; }

        /* ── Back link ── */
        .back-link {
            display: inline-block; margin-top: 16px; color: #64748b;
            text-decoration: none; font-size: 14px;
        }
        .back-link:hover { color: #1d4ed8; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../profile/public_profile.php?user_id=<?php echo $revieweeId; ?>">
            Back to Profile
        </a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <div class="form-card">
        <h1><?php echo $isEdit ? '✏️ Edit Review' : '⭐ Write Review'; ?></h1>

        <!-- Reviewee card -->
        <div class="user-info">
            <img src="<?php echo $revieweePhoto; ?>" alt="<?php echo $revieweeName; ?>">
            <div>
                <h3><?php echo $revieweeName; ?></h3>
                <p>@<?php echo $revieweeUser; ?></p>
            </div>
        </div>

        <form action="submit_review.php" method="POST" id="reviewForm">
            <?php echo $csrfField; ?>
            <input type="hidden" name="reviewee_id" value="<?php echo $revieweeId; ?>">

            <!-- Star Rating -->
            <div class="form-group">
                <label>Rating <span class="required">*</span></label>
                <div class="star-row" id="starRow">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?php echo $i <= $initRating ? 'active' : ''; ?>"
                              data-val="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="<?php echo $initRating; ?>" required>
                <div class="rating-label" id="ratingLabel">
                    <?php
                    $labels = [0=>'Select a rating',1=>'Poor',2=>'Fair',3=>'Good',4=>'Very Good',5=>'Excellent'];
                    echo $labels[$initRating];
                    ?>
                </div>
            </div>

            <!-- Review Text -->
            <div class="form-group">
                <label>Review <span class="required">*</span></label>
                <textarea
                    name="review_text"
                    id="reviewText"
                    placeholder="Share your experience with this traveler… (20–500 characters)"
                    maxlength="500"
                    required
                ><?php echo $initText; ?></textarea>
                <div class="char-info">
                    <span>Minimum 20 characters</span>
                    <span class="count" id="charCount">
                        <?php echo mb_strlen($existingReview['review_text'] ?? ''); ?>/500
                    </span>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <?php echo $isEdit ? '💾 Update Review' : '⭐ Submit Review'; ?>
            </button>
        </form>

        <a href="../profile/public_profile.php?user_id=<?php echo $revieweeId; ?>" class="back-link">
            ← Back to <?php echo $revieweeName; ?>'s profile
        </a>
    </div>

</div><!-- /.container -->

<script>
    // ── Star Rating ───────────────────────────────────────────────────────────
    const stars     = document.querySelectorAll('.star');
    const ratingIn  = document.getElementById('ratingInput');
    const label     = document.getElementById('ratingLabel');
    const labels    = {0:'Select a rating',1:'Poor',2:'Fair',3:'Good',4:'Very Good',5:'Excellent'};
    let current     = <?php echo $initRating; ?>;

    stars.forEach(star => {
        star.addEventListener('mouseover', () => highlightStars(parseInt(star.dataset.val)));
        star.addEventListener('mouseout',  () => highlightStars(current));
        star.addEventListener('click', () => {
            current = parseInt(star.dataset.val);
            ratingIn.value = current;
            highlightStars(current);
        });
    });

    function highlightStars(n) {
        stars.forEach((s, i) => s.classList.toggle('active', i < n));
        label.textContent = labels[n] || '';
    }

    // ── Character Counter ─────────────────────────────────────────────────────
    const ta        = document.getElementById('reviewText');
    const charCount = document.getElementById('charCount');

    function updateCount() {
        const len = ta.value.length;
        charCount.textContent = len + '/500';
        charCount.className = 'count';
        if (len < 20) charCount.classList.add('min');
    }
    ta.addEventListener('input', updateCount);
    updateCount();

    // ── Form Validation ───────────────────────────────────────────────────────
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        if (!current || current < 1) {
            e.preventDefault();
            alert('Please select a rating before submitting.');
            return;
        }
        if (ta.value.trim().length < 20) {
            e.preventDefault();
            alert('Review must be at least 20 characters.');
        }
    });
</script>
</body>
</html>
