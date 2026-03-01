<?php
// moderation/submit_report.php
// Task T30 — Report submission POST handler

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Report.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("../dashboard.php");
}

// ── CSRF ───────────────────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("report_form.php?error=csrf&type=" . urlencode($_POST['reported_type'] ?? '') . "&id=" . (int)($_POST['reported_id'] ?? 0));
}

$userId       = (int) SessionManager::get('user_id');
$reportedType = trim($_POST['reported_type'] ?? '');
$reportedId   = (int) ($_POST['reported_id'] ?? 0);
$category     = trim($_POST['category'] ?? '');
$description  = InputValidator::sanitizeString($_POST['description'] ?? '');

// ── Validate type & id ─────────────────────────────────────────────────────────
if (!Report::isValidType($reportedType) || $reportedId <= 0) {
    redirect("../dashboard.php?error=invalid_report");
}

// ── Prevent self-report ────────────────────────────────────────────────────────
if ($reportedType === 'user' && $reportedId === $userId) {
    redirect("../dashboard.php?error=cannot_report_self");
}

// ── Validate category ──────────────────────────────────────────────────────────
if (!Report::isValidCategory($category)) {
    redirect("report_form.php?error=category&type={$reportedType}&id={$reportedId}");
}

// ── Validate description length ────────────────────────────────────────────────
if (mb_strlen($description) > 1000) {
    redirect("report_form.php?error=desc_length&type={$reportedType}&id={$reportedId}");
}

// ── Duplicate check (same reporter + type + id within 24 h) ───────────────────
$duplicate = Report::findDuplicate($userId, $reportedType, $reportedId);
if ($duplicate) {
    redirect("../dashboard.php?error=already_reported");
}

// ── Insert report ──────────────────────────────────────────────────────────────
$success = Report::create([
    'reporter_id'   => $userId,
    'reported_type' => $reportedType,
    'reported_id'   => $reportedId,
    'category'      => $category,
    'description'   => $description,
]);

if ($success) {
    redirect("../dashboard.php?success=report_submitted");
} else {
    redirect("report_form.php?error=failed&type={$reportedType}&id={$reportedId}");
}
