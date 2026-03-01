<?php
// trips/delete_comment.php
// Task T22 — Delete a comment: owner or trip organizer can delete (POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripComment.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("search.php?error=csrf");
}

$userId    = (int) SessionManager::get('user_id');
$commentId = (int) ($_POST['comment_id'] ?? 0);

if ($commentId <= 0) {
    redirect("search.php");
}

// ── Fetch Comment ─────────────────────────────────────────────────────────────
$comment = TripComment::findById($commentId);
if (!$comment) {
    redirect("search.php?error=notfound");
}

$tripId = (int) $comment['trip_id'];
$trip   = Trip::findById($tripId);

// ── Permission: Comment owner OR trip organizer ───────────────────────────────
$isOwner     = (int) $comment['user_id']  === $userId;
$isOrganizer = $trip && (int) $trip['user_id'] === $userId;

if (!$isOwner && !$isOrganizer) {
    redirect("trip_discussion.php?trip_id={$tripId}&error=permission");
}

// ── Delete ────────────────────────────────────────────────────────────────────
$success = TripComment::delete($commentId);

if ($success) {
    redirect("trip_discussion.php?trip_id={$tripId}&success=deleted");
} else {
    redirect("trip_discussion.php?trip_id={$tripId}&error=failed");
}
?>
