<?php
// profile/photo_upload.php
// Task T12 — Handles profile photo upload and replaces old photo

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

// ── Check file was submitted ──────────────────────────────────────────────────
if (!isset($_FILES['profile_photo']) ||
    $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
    redirect("edit.php?error=nofile");
}

// ── Validate via InputValidator ───────────────────────────────────────────────
$result = InputValidator::validateFile($_FILES['profile_photo'], [
    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
    'max_size'      => 5 * 1024 * 1024,   // 5 MB
    'required'      => true,
]);

if (!$result['success']) {
    redirect("edit.php?error=photo");
}

// ── Prepare Upload Directory ──────────────────────────────────────────────────
$uploadDir = __DIR__ . '/../uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Generate Unique Filename ──────────────────────────────────────────────────
$ext      = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
$filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;

// ── Fetch old photo before overwriting ───────────────────────────────────────
$user     = User::findById($userId);
$oldPhoto = $user['profile_photo'] ?? null;

// ── Move File & Update DB ─────────────────────────────────────────────────────
if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filepath)) {

    $success = User::update($userId, ['profile_photo' => $filename]);

    if ($success) {
        // Remove old photo to save disk space (skip default avatar reference)
        if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
            unlink($uploadDir . $oldPhoto);
        }
        redirect("edit.php?success=1");
    } else {
        // DB update failed — remove the file we just uploaded
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        redirect("edit.php?error=photo");
    }

} else {
    redirect("edit.php?error=photo");
}
?>
