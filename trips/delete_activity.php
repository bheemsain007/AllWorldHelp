<?php
// trips/delete_activity.php
// Task T25 — Delete an itinerary activity (organizer only; POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripItinerary.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

// ── POST only ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("search.php?error=csrf");
}

$userId      = (int) SessionManager::get('user_id');
$itineraryId = (int) ($_POST['itinerary_id'] ?? 0);
$tripId      = (int) ($_POST['trip_id']      ?? 0);

if ($itineraryId <= 0 || $tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Activity ────────────────────────────────────────────────────────────
$activity = TripItinerary::findById($itineraryId);
if (!$activity || (int) $activity['trip_id'] !== $tripId) {
    redirect("itinerary.php?trip_id={$tripId}&error=not_found");
}

// ── Fetch Trip & Permission ───────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip || (int) $trip['user_id'] !== $userId) {
    redirect("itinerary.php?trip_id={$tripId}&error=permission");
}

// ── Delete ────────────────────────────────────────────────────────────────────
$success = TripItinerary::delete($itineraryId);

if ($success) {
    redirect("itinerary.php?trip_id={$tripId}&success=deleted");
} else {
    redirect("itinerary.php?trip_id={$tripId}&error=failed");
}
?>
