<?php
// trips/submit_rating.php
// Task T28 — Handle rating POST: validate, calculate overall, insert or update

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripRating.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

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

// ── Collect & validate ratings (each must be 1–5) ─────────────────────────────
$ratingKeys = ['organization', 'communication', 'value', 'experience', 'recommend'];
$ratings    = [];

foreach ($ratingKeys as $k) {
    $val = (int) ($_POST[$k . '_rating'] ?? 0);
    if ($val < 1 || $val > 5) {
        redirect("rate_trip.php?trip_id={$tripId}&error=invalid_rating");
    }
    $ratings[$k . '_rating'] = $val;
}

// ── Overall = average of 5 ───────────────────────────────────────────────────
$overallRating = array_sum($ratings) / count($ratings);

$reviewText = InputValidator::sanitizeString($_POST['review_text'] ?? '');

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Guard: trip must be completed ────────────────────────────────────────────
if (strtotime($trip['end_date']) > time()) {
    redirect("rate_trip.php?trip_id={$tripId}&error=not_completed");
}

// ── Guard: must be a participant ─────────────────────────────────────────────
if (!Trip::isParticipant($tripId, $userId)) {
    redirect("rate_trip.php?trip_id={$tripId}&error=not_participant");
}

// ── Build data array ──────────────────────────────────────────────────────────
$data = array_merge($ratings, [
    'trip_id'        => $tripId,
    'user_id'        => $userId,
    'overall_rating' => $overallRating,
    'review_text'    => $reviewText,
]);

// ── Insert or update ──────────────────────────────────────────────────────────
$existing = TripRating::findByUserAndTrip($userId, $tripId);

if ($existing) {
    $success = TripRating::update((int) $existing['rating_id'], $data);
} else {
    $success = TripRating::create($data);
}

if ($success) {
    redirect("trip_reviews.php?trip_id={$tripId}&success=rated");
} else {
    redirect("rate_trip.php?trip_id={$tripId}&error=failed");
}
?>
