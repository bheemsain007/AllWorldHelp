<?php
// trips/delete.php
// Task T16 — Handles trip soft-delete POST: permission check, started-trip guard, Trip::delete()

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

// Only accept POST
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
    redirect("search.php?error=notfound");
}

// ── Fetch Trip & Permission Check ─────────────────────────────────────────────
$trip = Trip::findById($tripId);

if (!$trip) {
    redirect("search.php?error=notfound");
}

// Only the organizer can delete
if ((int) $trip['user_id'] !== $userId) {
    redirect("view.php?id={$tripId}&error=permission");
}

// ── Guard: Cannot delete a trip that has already started ──────────────────────
if (strtotime($trip['start_date']) <= time()) {
    redirect("view.php?id={$tripId}&error=already_started");
}

// ── Soft Delete ───────────────────────────────────────────────────────────────
$success = Trip::delete($tripId);   // sets status = 'deleted' in ft_trips

if ($success) {
    redirect("search.php?success=deleted");
} else {
    redirect("view.php?id={$tripId}&error=delete_failed");
}
?>
