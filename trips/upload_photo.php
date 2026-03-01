<?php
// trips/upload_photo.php
// Task T23 — Handle photo upload: validate file, save to disk, insert DB record

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripPhoto.php";
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

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Permission ────────────────────────────────────────────────────────────────
if (!Trip::isParticipant($tripId, $userId)) {
    redirect("gallery.php?trip_id={$tripId}&error=not_participant");
}

// ── File Validation ───────────────────────────────────────────────────────────
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    redirect("gallery.php?trip_id={$tripId}&error=upload_failed");
}

$result = InputValidator::validateFile($_FILES['photo'], [
    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
    'max_size'      => 5 * 1024 * 1024,   // 5 MB
    'required'      => true,
]);

if (!$result['success']) {
    redirect("gallery.php?trip_id={$tripId}&error=invalid_file");
}

// ── Caption ───────────────────────────────────────────────────────────────────
$caption = InputValidator::sanitizeString($_POST['caption'] ?? '');
$caption = mb_substr($caption, 0, 200);   // enforce model max

// ── Ensure Upload Directory ───────────────────────────────────────────────────
$uploadDir = __DIR__ . '/../uploads/trip_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Generate Unique Filename ──────────────────────────────────────────────────
$ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
$filename = 'trip_' . $tripId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$filepath = $uploadDir . $filename;

// ── Move File ─────────────────────────────────────────────────────────────────
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
    redirect("gallery.php?trip_id={$tripId}&error=upload_failed");
}

// ── Insert DB Record ──────────────────────────────────────────────────────────
$success = TripPhoto::create([
    'trip_id'        => $tripId,
    'user_id'        => $userId,
    'photo_filename' => $filename,
    'caption'        => $caption,
]);

if ($success) {
    redirect("gallery.php?trip_id={$tripId}&success=uploaded");
} else {
    // Roll back the uploaded file so we don't leave orphans
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    redirect("gallery.php?trip_id={$tripId}&error=upload_failed");
}
?>
