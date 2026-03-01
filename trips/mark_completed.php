<?php
// trips/mark_completed.php
// Task T29 — Mark an ongoing trip as completed (organizer only; POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

// ── POST only ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("search.php?error=csrf");
}

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip & Permission ───────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

if ((int) $trip['user_id'] !== $userId) {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Guard: must be ongoing ────────────────────────────────────────────────────
if ($trip['status'] !== 'ongoing') {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Mark completed ────────────────────────────────────────────────────────────
$success = Trip::updateStatus($tripId, 'completed');

if ($success) {
    // Notify all participants — they can now rate the trip
    $participants = Trip::getParticipants($tripId);
    foreach ($participants as $p) {
        if ((int) $p['user_id'] !== $userId) {
            createNotification(
                (int) $p['user_id'],
                'trip_completed',
                'Trip Completed! ⭐',
                "\"{$trip['title']}\" has been completed. Share your experience — rate the trip!",
                "../trips/rate_trip.php?trip_id={$tripId}"
            );
        }
    }
    redirect("view.php?id={$tripId}&success=marked_completed");
} else {
    redirect("manage_status.php?trip_id={$tripId}&error=update_failed");
}
?>
