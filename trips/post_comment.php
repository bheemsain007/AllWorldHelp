<?php
// trips/post_comment.php
// Task T22 — Handle POST: validate comment, check participant, insert, redirect

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripComment.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("search.php?error=csrf");
}

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Permission: Organizer or accepted participant ──────────────────────────────
if (!Trip::isParticipant($tripId, $userId)) {
    redirect("trip_discussion.php?trip_id={$tripId}&error=not_participant");
}

// ── Validate Comment ──────────────────────────────────────────────────────────
$commentText = InputValidator::sanitizeString($_POST['comment_text'] ?? '');

if (mb_strlen($commentText) < 10 || mb_strlen($commentText) > 500) {
    redirect("trip_discussion.php?trip_id={$tripId}&error=invalid_comment");
}

// ── Insert ────────────────────────────────────────────────────────────────────
$success = TripComment::create([
    'trip_id'      => $tripId,
    'user_id'      => $userId,
    'comment_text' => $commentText,
]);

if ($success) {
    redirect("trip_discussion.php?trip_id={$tripId}&success=commented");
} else {
    redirect("trip_discussion.php?trip_id={$tripId}&error=failed");
}
?>
