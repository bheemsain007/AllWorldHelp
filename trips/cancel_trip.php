<?php
// trips/cancel_trip.php
// Task T29 — Cancel trip with reason (organizer only; GET shows form, POST processes)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
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

// ── Already terminal ──────────────────────────────────────────────────────────
if (in_array($trip['status'], ['completed', 'cancelled'], true)) {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = '❌ Security token invalid. Please try again.';
    } else {
        $reason = InputValidator::sanitizeString($_POST['reason'] ?? '');

        if (mb_strlen($reason) < 10) {
            $error = '❌ Please provide a cancellation reason (minimum 10 characters).';
        } else {
            // Mark cancelled
            $success = Trip::updateStatus($tripId, 'cancelled');

            if ($success) {
                // Notify all participants
                $participants = Trip::getParticipants($tripId);
                foreach ($participants as $p) {
                    if ((int) $p['user_id'] !== $userId) {
                        createNotification(
                            (int) $p['user_id'],
                            'trip_cancelled',
                            'Trip Cancelled',
                            "\"{$trip['title']}\" has been cancelled. Reason: {$reason}",
                            "../trips/view.php?id={$tripId}"
                        );
                    }
                }
                redirect("view.php?id={$tripId}&success=cancelled");
            } else {
                $error = '❌ Failed to cancel trip. Please try again.';
            }
        }
    }
}

$csrfField = CSRF::getTokenField();
$tripTitle = htmlspecialchars($trip['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Trip — <?php echo $tripTitle; ?></title>
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

        .container { max-width: 580px; margin: 36px auto; padding: 0 20px; }

        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }

        .warning-box {
            background: #fef3c7; border: 1px solid #fde68a;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
            color: #713f12; font-size: 14px; line-height: 1.6;
        }
        .warning-box strong { display: block; margin-bottom: 4px; }

        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #dc2626; font-size: 22px; margin-bottom: 8px; }
        .form-card .trip-name { color: #64748b; font-size: 14px; margin-bottom: 24px; }

        .form-group { margin-bottom: 20px; }
        .field-label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
            resize: vertical; min-height: 110px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #dc2626; }

        .btn-row { display: flex; gap: 12px; }
        .btn-cancel-trip {
            flex: 1; padding: 14px; background: #dc2626; color: white;
            border: none; border-radius: 8px; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-cancel-trip:hover { background: #b91c1c; }
        .btn-back {
            padding: 14px 24px; background: #e5e7eb; color: #374151;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 600;
            cursor: pointer; text-decoration: none; text-align: center;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #d1d5db; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="manage_status.php?trip_id=<?php echo $tripId; ?>">Status</a>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="warning-box">
        <strong>⚠️ Warning — This action cannot be undone</strong>
        Cancelling this trip will permanently set its status to "Cancelled".
        All accepted participants will receive a notification.
    </div>

    <div class="form-card">
        <h1>❌ Cancel Trip</h1>
        <div class="trip-name"><?php echo $tripTitle; ?></div>

        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

            <div class="form-group">
                <label class="field-label" for="reason">
                    Cancellation Reason <span style="color:#dc2626;">*</span>
                </label>
                <textarea
                    id="reason"
                    name="reason"
                    placeholder="Please explain why this trip is being cancelled (e.g., weather conditions, organizer unavailability, insufficient participants…)"
                    minlength="10"
                    maxlength="500"
                    required
                ></textarea>
                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Minimum 10 characters</div>
            </div>

            <div class="btn-row">
                <a href="manage_status.php?trip_id=<?php echo $tripId; ?>" class="btn-back">← Go Back</a>
                <button type="submit" class="btn-cancel-trip">Confirm Cancellation</button>
            </div>
        </form>
    </div>

</div><!-- /.container -->
</body>
</html>
