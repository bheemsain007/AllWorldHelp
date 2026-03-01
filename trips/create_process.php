<?php
// trips/create_process.php
// Task T15 — Handles trip creation POST: validate, upload image, build itinerary, insert row

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/RateLimiter.php";
require_once "../includes/helpers.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("create.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("create.php?error=csrf");
}

$userId = (int) SessionManager::get('user_id');

// ── Rate limiting: max 10 trips per day per user ──────────────────────────────
$rateLimitKey = 'trip_create_' . $userId;
if (RateLimiter::isBlocked($rateLimitKey, RateLimiter::LIMIT_TRIP_CREATE, RateLimiter::WINDOW_TRIP_CREATE)) {
    redirect("create.php?error=ratelimit");
}

// ── Sanitize Inputs ───────────────────────────────────────────────────────────
$title       = InputValidator::sanitizeString($_POST['title']       ?? '');
$destination = InputValidator::sanitizeString($_POST['destination'] ?? '');
$description = InputValidator::sanitizeString($_POST['description'] ?? '');
$startDate   = InputValidator::sanitizeString($_POST['start_date']  ?? '');
$endDate     = InputValidator::sanitizeString($_POST['end_date']    ?? '');
$budget      = (int) ($_POST['budget_per_person'] ?? 0);
$maxTravelers= (int) ($_POST['max_travelers']     ?? 0);
$tripType    = InputValidator::sanitizeString($_POST['trip_type']   ?? '');

// ── Validate ──────────────────────────────────────────────────────────────────
if (!InputValidator::validateLength($title, 5, 100)) {
    redirect("create.php?error=title");
}

if (!InputValidator::validateLength($destination, 2, 100)) {
    redirect("create.php?error=destination");
}

if (mb_strlen($description) < 50 || mb_strlen($description) > 2000) {
    redirect("create.php?error=description");
}

if (!InputValidator::validateDate($startDate) || !InputValidator::validateDate($endDate)) {
    redirect("create.php?error=date");
}

if (strtotime($startDate) <= time()) {
    redirect("create.php?error=past_date");
}

if (strtotime($endDate) <= strtotime($startDate)) {
    redirect("create.php?error=date_order");
}

if ($budget < 500) {
    redirect("create.php?error=budget");
}

if ($maxTravelers < 2 || $maxTravelers > 50) {
    redirect("create.php?error=travelers");
}

$allowedTypes = ['solo', 'group', 'family', 'couple', 'friends'];
if (!in_array($tripType, $allowedTypes, true)) {
    redirect("create.php?error=type");
}

// ── Handle Image Upload ───────────────────────────────────────────────────────
$imageName = null;

if (isset($_FILES['trip_image']) && $_FILES['trip_image']['error'] === UPLOAD_ERR_OK) {

    $result = InputValidator::validateFile($_FILES['trip_image'], [
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size'      => 5 * 1024 * 1024,  // 5 MB
        'required'      => false,
    ]);

    if ($result['success']) {
        $uploadDir = __DIR__ . '/../uploads/trips/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext       = strtolower(pathinfo($_FILES['trip_image']['name'], PATHINFO_EXTENSION));
        $imageName = 'trip_' . $userId . '_' . time() . '_' . uniqid() . '.' . $ext;

        if (!move_uploaded_file($_FILES['trip_image']['tmp_name'], $uploadDir . $imageName)) {
            $imageName = null;  // Upload failed — silently proceed without image
        }
    }
}

// ── Process Itinerary ─────────────────────────────────────────────────────────
$itinerary = null;

if (!empty($_POST['itinerary']) && is_array($_POST['itinerary'])) {
    $itineraryData = [];

    foreach ($_POST['itinerary'] as $index => $dayPlan) {
        $dayPlan = InputValidator::sanitizeString($dayPlan);
        if ($dayPlan !== '') {
            $itineraryData[$index + 1] = $dayPlan;  // 1-indexed day numbers
        }
    }

    if (!empty($itineraryData)) {
        $itinerary = json_encode($itineraryData);
    }
}

// ── Create Trip ───────────────────────────────────────────────────────────────
$tripData = [
    'user_id'          => $userId,
    'title'            => $title,
    'destination'      => $destination,
    'description'      => $description,
    'start_date'       => $startDate,
    'end_date'         => $endDate,
    'budget_per_person'=> $budget,
    'max_travelers'    => $maxTravelers,
    'trip_type'        => $tripType,
    'image'            => $imageName,
    'itinerary'        => $itinerary,
    'status'           => 'open',
];

$tripId = Trip::create($tripData);

if ($tripId) {
    // Record rate-limit hit on success
    RateLimiter::increment($rateLimitKey);

    // ── Award coins (implement in T34-T38) ────────────────────────────────────
    // CoinManager::award($userId, 'trip_create', 80);

    redirect("view.php?id={$tripId}&success=created");
} else {
    redirect("create.php?error=failed");
}
?>
