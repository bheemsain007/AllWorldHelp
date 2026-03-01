<?php
// reviews/submit_review.php
// Task T20 — Handle review POST: validate, create or update review

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Review.php";
require_once "../models/Connection.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("../trips/search.php");
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("../trips/search.php?error=csrf");
}

$reviewerId = (int) SessionManager::get('user_id');
$revieweeId = (int) ($_POST['reviewee_id'] ?? 0);
$rating     = (int) ($_POST['rating']      ?? 0);
$reviewText = InputValidator::sanitizeString($_POST['review_text'] ?? '');

if ($revieweeId <= 0) {
    redirect("../trips/search.php");
}

// ── Guards ────────────────────────────────────────────────────────────────────
if ($reviewerId === $revieweeId) {
    redirect("../profile/public_profile.php?user_id={$revieweeId}&error=self_review");
}

if ($rating < 1 || $rating > 5) {
    redirect("write_review.php?user_id={$revieweeId}&error=invalid_rating");
}

if (mb_strlen($reviewText) < 20 || mb_strlen($reviewText) > 500) {
    redirect("write_review.php?user_id={$revieweeId}&error=invalid_review");
}

if (!Connection::areConnected($reviewerId, $revieweeId)) {
    redirect("write_review.php?user_id={$revieweeId}&error=not_connected");
}

// ── Create or Update ──────────────────────────────────────────────────────────
$reviewData = [
    'reviewer_id' => $reviewerId,
    'reviewee_id' => $revieweeId,
    'rating'      => $rating,
    'review_text' => $reviewText,
];

$existingReview = Review::findByUsers($reviewerId, $revieweeId);

if ($existingReview) {
    $success = Review::update((int) $existingReview['review_id'], $reviewData);
} else {
    $success = Review::create($reviewData);
}

if ($success) {
    redirect("../profile/public_profile.php?user_id={$revieweeId}&success=review_submitted");
} else {
    redirect("write_review.php?user_id={$revieweeId}&error=failed");
}
?>
