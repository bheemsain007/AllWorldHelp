<?php
// trips/update.php
// Task T16 — Handles trip edit POST: permission check, validate, upload image, update row

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
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

if ((int) $trip['user_id'] !== $userId) {
    redirect("view.php?id={$tripId}&error=permission");
}

// ── Detect Locked State ───────────────────────────────────────────────────────
$hasStarted      = strtotime($trip['start_date']) <= time();
$hasParticipants = (int) $trip['joined_travelers'] > 0;

// ── Sanitize Inputs ───────────────────────────────────────────────────────────
$description  = InputValidator::sanitizeString($_POST['description']       ?? '');
$budget       = (int) ($_POST['budget_per_person'] ?? 0);
$maxTravelers = (int) ($_POST['max_travelers']     ?? 0);

// Locked fields: use original trip values if trip has started
if ($hasStarted) {
    $title       = $trip['title'];
    $destination = $trip['destination'];
    $startDate   = $trip['start_date'];
    $endDate     = $trip['end_date'];
    $tripType    = $trip['trip_type'];
} else {
    $title       = InputValidator::sanitizeString($_POST['title']       ?? '');
    $destination = InputValidator::sanitizeString($_POST['destination'] ?? '');
    $startDate   = InputValidator::sanitizeString($_POST['start_date']  ?? '');
    $endDate     = InputValidator::sanitizeString($_POST['end_date']    ?? '');
    $tripType    = InputValidator::sanitizeString($_POST['trip_type']   ?? '');
}

// Budget locked if has participants
if ($hasParticipants) {
    $budget = (int) $trip['budget_per_person'];
}

// ── Validate Non-Locked Fields ────────────────────────────────────────────────
if (!InputValidator::validateLength($title, 5, 100)) {
    redirect("edit.php?id={$tripId}&error=title");
}

if (!InputValidator::validateLength($destination, 2, 100)) {
    redirect("edit.php?id={$tripId}&error=destination");
}

if (mb_strlen($description) < 50 || mb_strlen($description) > 2000) {
    redirect("edit.php?id={$tripId}&error=description");
}

if (!$hasStarted) {
    if (!InputValidator::validateDate($startDate) || !InputValidator::validateDate($endDate)) {
        redirect("edit.php?id={$tripId}&error=date");
    }

    if (strtotime($startDate) <= time()) {
        redirect("edit.php?id={$tripId}&error=past_date");
    }

    if (strtotime($endDate) <= strtotime($startDate)) {
        redirect("edit.php?id={$tripId}&error=date_order");
    }

    $allowedTypes = ['solo', 'group', 'family', 'couple', 'friends'];
    if (!in_array($tripType, $allowedTypes, true)) {
        redirect("edit.php?id={$tripId}&error=type");
    }
}

// ── Validate Budget & Travelers ───────────────────────────────────────────────
if (!$hasParticipants && $budget < 500) {
    redirect("edit.php?id={$tripId}&error=budget");
}

if ($maxTravelers < 2 || $maxTravelers > 50) {
    redirect("edit.php?id={$tripId}&error=travelers");
}

// Max travelers cannot be set below current participant count
if ($maxTravelers < (int) $trip['joined_travelers']) {
    redirect("edit.php?id={$tripId}&error=max_travelers");
}

// ── Handle Image Upload ───────────────────────────────────────────────────────
$imageName = $trip['image'];  // Keep existing by default

if (isset($_FILES['trip_image']) && $_FILES['trip_image']['error'] === UPLOAD_ERR_OK) {

    $result = InputValidator::validateFile($_FILES['trip_image'], [
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size'      => 5 * 1024 * 1024,
        'required'      => false,
    ]);

    if ($result['success']) {
        $uploadDir = __DIR__ . '/../uploads/trips/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext         = strtolower(pathinfo($_FILES['trip_image']['name'], PATHINFO_EXTENSION));
        $newImageName = 'trip_' . $userId . '_' . time() . '_' . uniqid() . '.' . $ext;

        if (move_uploaded_file($_FILES['trip_image']['tmp_name'], $uploadDir . $newImageName)) {
            // Delete old image if it exists
            if (!empty($trip['image'])) {
                $oldPath = $uploadDir . $trip['image'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $imageName = $newImageName;
        }
        // If move fails, silently keep existing image
    }
}

// ── Process Itinerary ─────────────────────────────────────────────────────────
$itinerary = $trip['itinerary'];  // Keep existing by default

if (!empty($_POST['itinerary']) && is_array($_POST['itinerary'])) {
    $itineraryData = [];

    foreach ($_POST['itinerary'] as $index => $dayPlan) {
        $dayPlan = InputValidator::sanitizeString($dayPlan);
        if ($dayPlan !== '') {
            $itineraryData[$index + 1] = $dayPlan;
        }
    }

    $itinerary = !empty($itineraryData) ? json_encode($itineraryData) : null;
}

// ── Build Update Payload ──────────────────────────────────────────────────────
$updateData = [
    'title'             => $title,
    'destination'       => $destination,
    'description'       => $description,
    'start_date'        => $startDate,
    'end_date'          => $endDate,
    'budget_per_person' => $budget,
    'max_travelers'     => $maxTravelers,
    'trip_type'         => $tripType,
    'image'             => $imageName,
    'itinerary'         => $itinerary,
];

// ── Persist ───────────────────────────────────────────────────────────────────
$success = Trip::update($tripId, $updateData);

if ($success) {
    redirect("view.php?id={$tripId}&success=updated");
} else {
    redirect("edit.php?id={$tripId}&error=failed");
}
?>
