<?php
// trips/add_activity.php
// Task T25 — Add itinerary activity (organizer only; GET shows form, POST processes)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripItinerary.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

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
    redirect("itinerary.php?trip_id={$tripId}&error=permission");
}

// ── Calculate trip duration (number of days) ──────────────────────────────────
$durationDays = 1;
if (!empty($trip['start_date']) && !empty($trip['end_date'])) {
    $startTs      = strtotime($trip['start_date']);
    $endTs        = strtotime($trip['end_date']);
    $durationDays = max(1, (int) ceil(($endTs - $startTs) / 86400) + 1);
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$error       = null;
$oldDay      = 1;
$oldTime     = '';
$oldTitle    = '';
$oldLocation = '';
$oldDesc     = '';
$oldCat      = 'activity';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = '❌ Security token invalid. Please try again.';
    } else {

        $dayNumber     = (int)    ($_POST['day_number']    ?? 1);
        $activityTime  =           $_POST['activity_time']  ?? '';
        $activityTitle = InputValidator::sanitizeString($_POST['activity_title'] ?? '');
        $location      = InputValidator::sanitizeString($_POST['location']       ?? '');
        $description   = InputValidator::sanitizeString($_POST['description']    ?? '');
        $category      =           $_POST['category']       ?? 'activity';

        // Preserve on error
        $oldDay      = $dayNumber;
        $oldTime     = htmlspecialchars($activityTime);
        $oldTitle    = htmlspecialchars($activityTitle);
        $oldLocation = htmlspecialchars($location);
        $oldDesc     = htmlspecialchars($description);
        $oldCat      = $category;

        if ($dayNumber < 1 || $dayNumber > $durationDays) {
            $error = '❌ Invalid day number. Must be between 1 and ' . $durationDays . '.';
        } elseif (empty($activityTime)) {
            $error = '❌ Activity time is required.';
        } elseif (mb_strlen($activityTitle) < 2 || mb_strlen($activityTitle) > 200) {
            $error = '❌ Activity title must be between 2 and 200 characters.';
        } elseif (!TripItinerary::isValidCategory($category)) {
            $error = '❌ Invalid category selected.';
        } else {
            $success = TripItinerary::create([
                'trip_id'        => $tripId,
                'day_number'     => $dayNumber,
                'activity_time'  => $activityTime,
                'activity_title' => $activityTitle,
                'location'       => $location,
                'description'    => $description,
                'category'       => $category,
            ]);

            if ($success) {
                redirect("itinerary.php?trip_id={$tripId}&success=added");
            } else {
                $error = '❌ Failed to add activity. Please try again.';
            }
        }
    }
}

$csrfField    = CSRF::getTokenField();
$tripTitle    = htmlspecialchars($trip['title']);
$categoryMeta = TripItinerary::getCategoryMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Activity — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 640px; margin: 36px auto; padding: 0 20px; }
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }
        .trip-banner {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 12px 18px; border-radius: 8px; margin-bottom: 22px;
            font-size: 14px; color: #1e3a8a;
        }
        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        .field-label { display: block; font-weight: 700; margin-bottom: 7px; color: #374151; }
        .required { color: #dc2626; }
        input[type="number"], input[type="text"], input[type="time"], select, textarea {
            width: 100%; padding: 11px 13px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #1d4ed8; }
        textarea { resize: vertical; min-height: 100px; line-height: 1.5; }
        .char-info { font-size: 12px; color: #94a3b8; margin-top: 5px; }
        .row-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn-submit {
            width: 100%; padding: 14px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 17px;
            font-weight: 700; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #1e40af; }
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
        <a href="itinerary.php?trip_id=<?php echo $tripId; ?>">Itinerary</a>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="trip-banner">
        📅 Adding activity for: <strong><?php echo $tripTitle; ?></strong>
        (<?php echo $durationDays; ?> day<?php echo $durationDays !== 1 ? 's' : ''; ?>)
    </div>

    <div class="form-card">
        <h1>➕ Add Activity</h1>

        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

            <div class="row-two">
                <!-- Day Number -->
                <div class="form-group">
                    <label class="field-label" for="day_number">
                        Day <span class="required">*</span>
                    </label>
                    <select id="day_number" name="day_number" required>
                        <?php for ($d = 1; $d <= $durationDays; $d++): ?>
                            <option value="<?php echo $d; ?>"
                                <?php echo ($oldDay === $d) ? 'selected' : ''; ?>>
                                Day <?php echo $d; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Time -->
                <div class="form-group">
                    <label class="field-label" for="activity_time">
                        Time <span class="required">*</span>
                    </label>
                    <input
                        type="time"
                        id="activity_time"
                        name="activity_time"
                        value="<?php echo $oldTime; ?>"
                        required
                    >
                </div>
            </div>

            <!-- Activity Title -->
            <div class="form-group">
                <label class="field-label" for="activity_title">
                    Activity Title <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="activity_title"
                    name="activity_title"
                    maxlength="200"
                    placeholder="e.g., Visit Taj Mahal"
                    value="<?php echo $oldTitle; ?>"
                    required
                >
            </div>

            <!-- Location -->
            <div class="form-group">
                <label class="field-label" for="location">Location <span style="color:#94a3b8;font-size:12px;">(optional)</span></label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="200"
                    placeholder="e.g., Agra, India"
                    value="<?php echo $oldLocation; ?>"
                >
            </div>

            <!-- Category -->
            <div class="form-group">
                <label class="field-label" for="category">
                    Category <span class="required">*</span>
                </label>
                <select id="category" name="category" required>
                    <?php foreach ($categoryMeta as $val => $meta): ?>
                        <option value="<?php echo $val; ?>"
                            <?php echo ($oldCat === $val) ? 'selected' : ''; ?>>
                            <?php echo $meta['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="field-label" for="description">
                    Notes <span style="color:#94a3b8;font-size:12px;">(optional)</span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    maxlength="500"
                    placeholder="Additional details, meeting points, etc."
                ><?php echo $oldDesc; ?></textarea>
                <div class="char-info">Max 500 characters</div>
            </div>

            <button type="submit" class="btn-submit">📅 Add Activity</button>
        </form>

        <a href="itinerary.php?trip_id=<?php echo $tripId; ?>" class="back-link">
            ← Back to itinerary
        </a>
    </div>

</div><!-- /.container -->
</body>
</html>
