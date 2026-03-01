<?php
// trips/delete_expense.php
// Task T24 — Delete an expense: payer OR trip organizer can delete (POST + CSRF)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripExpense.php";
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

$userId    = (int) SessionManager::get('user_id');
$expenseId = (int) ($_POST['expense_id'] ?? 0);
$tripId    = (int) ($_POST['trip_id']    ?? 0);

if ($expenseId <= 0 || $tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Expense ─────────────────────────────────────────────────────────────
$expense = TripExpense::findById($expenseId);
if (!$expense || (int) $expense['trip_id'] !== $tripId) {
    redirect("expenses.php?trip_id={$tripId}&error=not_found");
}

// ── Fetch Trip for organizer check ────────────────────────────────────────────
$trip = Trip::findById($tripId);

// ── Permission: payer OR organizer ───────────────────────────────────────────
$isPayer     = ((int) $expense['paid_by']  === $userId);
$isOrganizer = $trip && ((int) $trip['user_id'] === $userId);

if (!$isPayer && !$isOrganizer) {
    redirect("expenses.php?trip_id={$tripId}&error=permission");
}

// ── Delete ────────────────────────────────────────────────────────────────────
$success = TripExpense::delete($expenseId);

if ($success) {
    redirect("expenses.php?trip_id={$tripId}&success=deleted");
} else {
    redirect("expenses.php?trip_id={$tripId}&error=failed");
}
?>
