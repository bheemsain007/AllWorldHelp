<?php
// profile/update.php
// Task T12 — Handles profile info update POST request

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("edit.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("edit.php?error=csrf");
}

$userId = (int) SessionManager::get('user_id');

// ── Sanitize Inputs ───────────────────────────────────────────────────────────
$name    = InputValidator::sanitizeString($_POST['name']    ?? '');
$bio     = InputValidator::sanitizeString($_POST['bio']     ?? '');
$phone   = InputValidator::sanitizeString($_POST['phone']   ?? '');
$city    = InputValidator::sanitizeString($_POST['city']    ?? '');
$country = InputValidator::sanitizeString($_POST['country'] ?? '');
$dob     = InputValidator::sanitizeString($_POST['dob']     ?? '');

// ── Validate ──────────────────────────────────────────────────────────────────

// Name is required: 2–50 chars
if (!InputValidator::validateLength($name, 2, 50)) {
    redirect("edit.php?error=validation");
}

// Bio optional but max 500 chars
if (mb_strlen($bio) > 500) {
    redirect("edit.php?error=validation");
}

// Phone: optional but must be valid if provided
if ($phone !== '' && !InputValidator::validatePhone($phone)) {
    redirect("edit.php?error=validation");
}

// DOB: optional but must be a valid date if provided
if ($dob !== '' && !InputValidator::validateDate($dob)) {
    redirect("edit.php?error=validation");
}

// City / country optional, cap at 100 chars each
if ($city    !== '' && !InputValidator::validateLength($city,    1, 100)) { redirect("edit.php?error=validation"); }
if ($country !== '' && !InputValidator::validateLength($country, 1, 100)) { redirect("edit.php?error=validation"); }

// ── Build Update Payload ──────────────────────────────────────────────────────
$updateData = [
    'name'    => $name,
    'bio'     => $bio    ?: null,
    'phone'   => $phone  ?: null,
    'city'    => $city   ?: null,
    'country' => $country ?: null,
    'dob'     => $dob    ?: null,
];

// ── Persist ───────────────────────────────────────────────────────────────────
$success = User::update($userId, $updateData);

if ($success) {
    // Keep session name in sync
    SessionManager::set('name', $name);
    redirect("edit.php?success=1");
} else {
    redirect("edit.php?error=update");
}
?>
