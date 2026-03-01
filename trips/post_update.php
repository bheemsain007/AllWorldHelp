<?php
// trips/post_update.php
// Task T21 — Organizer-only form to post a trip update (GET shows form, POST processes)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripUpdate.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_GET['trip_id'] ?? $_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip & Permission ───────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

if ((int) $trip['user_id'] !== $userId) {
    redirect("view.php?id={$tripId}&error=permission");
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = '❌ Security token invalid. Please try again.';
    } else {

        $updateText = InputValidator::sanitizeString($_POST['update_text'] ?? '');
        $updateType = $_POST['update_type'] ?? 'info';

        if (mb_strlen($updateText) < 10 || mb_strlen($updateText) > 500) {
            $error = '❌ Update must be between 10 and 500 characters.';
        } elseif (!TripUpdate::isValidType($updateType)) {
            $error = '❌ Invalid update type selected.';
        } else {
            $success = TripUpdate::create([
                'trip_id'     => $tripId,
                'user_id'     => $userId,
                'update_text' => $updateText,
                'update_type' => $updateType,
            ]);

            if ($success) {
                // Notify all accepted participants (excluding poster)
                $participants = Trip::getParticipants($tripId);
                foreach ($participants as $p) {
                    if ((int) $p['user_id'] !== $userId) {
                        createNotification(
                            (int) $p['user_id'],
                            'trip_update',
                            'New Trip Update',
                            "New {$updateType} update on \"{$trip['title']}\"",
                            "../trips/trip_feed.php?trip_id={$tripId}"
                        );
                    }
                }
                redirect("trip_feed.php?trip_id={$tripId}&success=posted");
            } else {
                $error = '❌ Failed to post update. Please try again.';
            }
        }
    }
}

$csrfField  = CSRF::getTokenField();
$tripTitle  = htmlspecialchars($trip['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Update — <?php echo $tripTitle; ?></title>
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

        /* ── Error ── */
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }

        /* ── Trip context banner ── */
        .trip-banner {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 14px;
        }
        .trip-banner img {
            width: 52px; height: 52px; object-fit: cover; border-radius: 8px;
            flex-shrink: 0;
        }
        .trip-banner-info h3 { color: #1d4ed8; font-size: 15px; }
        .trip-banner-info p  { color: #64748b; font-size: 13px; margin-top: 2px; }

        /* ── Form Card ── */
        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 24px; }

        /* ── Update Type Selector ── */
        .type-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
            margin-bottom: 24px;
        }
        .type-option { display: none; }
        .type-label {
            border: 2px solid #e5e7eb; border-radius: 10px; padding: 14px 12px;
            cursor: pointer; text-align: center; font-size: 14px; font-weight: 600;
            transition: all 0.15s; color: #374151; background: white;
            display: flex; flex-direction: column; gap: 6px; align-items: center;
        }
        .type-label .icon { font-size: 22px; }
        .type-option:checked + .type-label { border-color: #1d4ed8; background: #eff6ff; color: #1d4ed8; }
        .type-label:hover { border-color: #93c5fd; }

        /* Per-type colour accents */
        .type-option[value="warning"]:checked + .type-label    { border-color: #ea580c; background: #fff7ed; color: #ea580c; }
        .type-option[value="reminder"]:checked + .type-label   { border-color: #ca8a04; background: #fefce8; color: #ca8a04; }
        .type-option[value="completion"]:checked + .type-label { border-color: #16a34a; background: #f0fdf4; color: #16a34a; }

        /* ── Textarea ── */
        .form-group { margin-bottom: 24px; }
        label.field-label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
            resize: vertical; min-height: 130px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #1d4ed8; }
        .char-info {
            display: flex; justify-content: space-between;
            margin-top: 6px; font-size: 13px; color: #94a3b8;
        }
        .char-info .cnt.warn { color: #f59e0b; }
        .char-info .cnt.min  { color: #dc2626; }

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
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="trip_feed.php?trip_id=<?php echo $tripId; ?>">Updates Feed</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Trip context -->
    <div class="trip-banner">
        <img src="<?php echo !empty($trip['image'])
            ? '../uploads/trips/' . htmlspecialchars($trip['image'])
            : '../assets/default-trip.jpg'; ?>"
             alt="<?php echo $tripTitle; ?>">
        <div class="trip-banner-info">
            <h3><?php echo $tripTitle; ?></h3>
            <p>📍 <?php echo htmlspecialchars($trip['destination']); ?></p>
        </div>
    </div>

    <div class="form-card">
        <h1>📢 Post Trip Update</h1>

        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

            <!-- Update Type -->
            <div class="form-group">
                <label class="field-label">Update Type</label>
                <div class="type-grid">
                    <input type="radio" name="update_type" id="type_info"       value="info"       class="type-option" checked>
                    <label for="type_info" class="type-label">
                        <span class="icon">ℹ️</span> Information
                    </label>

                    <input type="radio" name="update_type" id="type_warning"    value="warning"    class="type-option">
                    <label for="type_warning" class="type-label">
                        <span class="icon">⚠️</span> Warning
                    </label>

                    <input type="radio" name="update_type" id="type_reminder"   value="reminder"   class="type-option">
                    <label for="type_reminder" class="type-label">
                        <span class="icon">⏰</span> Reminder
                    </label>

                    <input type="radio" name="update_type" id="type_completion" value="completion" class="type-option">
                    <label for="type_completion" class="type-label">
                        <span class="icon">✅</span> Trip Completed
                    </label>
                </div>
            </div>

            <!-- Update Text -->
            <div class="form-group">
                <label class="field-label" for="updateText">
                    Update Message <span style="color:#dc2626">*</span>
                </label>
                <textarea
                    id="updateText"
                    name="update_text"
                    placeholder="Write your update here… e.g. 'Meeting point changed to Central Park at 8 AM'"
                    maxlength="500"
                    required
                ></textarea>
                <div class="char-info">
                    <span>Minimum 10 characters</span>
                    <span class="cnt" id="charCount">0/500</span>
                </div>
            </div>

            <button type="submit" class="btn-submit">📢 Post Update</button>
        </form>

        <a href="trip_feed.php?trip_id=<?php echo $tripId; ?>" class="back-link">
            ← View all updates
        </a>
    </div>

</div><!-- /.container -->

<script>
    const ta  = document.getElementById('updateText');
    const cnt = document.getElementById('charCount');

    function updateCount() {
        const len = ta.value.length;
        cnt.textContent = len + '/500';
        cnt.className = 'cnt';
        if (len < 10)  cnt.classList.add('min');
        if (len > 450) cnt.classList.add('warn');
    }
    ta.addEventListener('input', updateCount);
    updateCount();
</script>
</body>
</html>
