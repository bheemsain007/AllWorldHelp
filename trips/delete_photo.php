<?php
// trips/delete_photo.php
// Task T23 — Delete a trip photo: photo owner OR trip organizer can delete

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripPhoto.php";
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

$userId  = (int) SessionManager::get('user_id');
$photoId = (int) ($_POST['photo_id'] ?? 0);
$tripId  = (int) ($_POST['trip_id']  ?? 0);

if ($photoId <= 0 || $tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Photo & Trip ────────────────────────────────────────────────────────
$photo = TripPhoto::findById($photoId);
if (!$photo) {
    redirect("gallery.php?trip_id={$tripId}&error=photo_not_found");
}

$trip = Trip::findById((int) $photo['trip_id']);

// ── Permission: photo owner OR trip organizer ─────────────────────────────────
$isOwner     = ((int) $photo['user_id']  === $userId);
$isOrganizer = $trip && ((int) $trip['user_id'] === $userId);

if (!$isOwner && !$isOrganizer) {
    redirect("gallery.php?trip_id={$tripId}&error=permission");
}

// ── Delete File From Disk ─────────────────────────────────────────────────────
$filePath = __DIR__ . '/../uploads/trip_photos/' . basename($photo['photo_filename']);
if (file_exists($filePath)) {
    unlink($filePath);
}

// ── Delete DB Record ──────────────────────────────────────────────────────────
$success = TripPhoto::delete($photoId);

if ($success) {
    redirect("gallery.php?trip_id={$tripId}&success=deleted");
} else {
    redirect("gallery.php?trip_id={$tripId}&error=delete_failed");
}
?>
