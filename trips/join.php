<?php
// trips/join.php
// Task T14 — Join trip handler (POST only)
// Adds user to ft_trip_participants and increments joined_travelers counter.

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/helpers.php";

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

$tripId = (int) ($_POST['trip_id'] ?? 0);
$userId = (int) SessionManager::get('user_id');

if (!$tripId) {
    redirect("search.php");
}

// ── Fetch trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);

if (!$trip || $trip['status'] === 'deleted') {
    redirect("view.php?id={$tripId}&error=notfound");
}

// ── Guard: owner cannot join own trip ─────────────────────────────────────────
if ((int) $trip['user_id'] === $userId) {
    redirect("view.php?id={$tripId}&error=owner");
}

// ── Guard: seats available ────────────────────────────────────────────────────
$seatsAvailable = (int) $trip['max_travelers'] - (int) $trip['joined_travelers'];
if ($seatsAvailable <= 0) {
    redirect("view.php?id={$tripId}&error=full");
}

// ── Guard: not already joined ─────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT 1 FROM ft_trip_participants WHERE trip_id = ? AND user_id = ?"
);
$stmt->execute([$tripId, $userId]);
if ($stmt->fetchColumn()) {
    redirect("view.php?id={$tripId}&error=already");
}

// ── Atomic join (transaction) ─────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 1. Insert participant row
    $stmt = $pdo->prepare("
        INSERT INTO ft_trip_participants (trip_id, user_id, status, joined_at)
        VALUES (?, ?, 'accepted', NOW())
    ");
    $stmt->execute([$tripId, $userId]);

    // 2. Increment joined_travelers counter on the trip
    $stmt = $pdo->prepare("
        UPDATE ft_trips
        SET joined_travelers = joined_travelers + 1,
            updated_at       = NOW()
        WHERE trip_id = ?
          AND joined_travelers < max_travelers
    ");
    $stmt->execute([$tripId]);

    if ($stmt->rowCount() === 0) {
        // Race-condition: someone grabbed the last seat just before us
        $pdo->rollBack();
        redirect("view.php?id={$tripId}&error=full");
    }

    $pdo->commit();

    // ── Award coins (implement in T34-T38) ────────────────────────────────────
    // CoinManager::award($userId, 'trip_join', 50);

    redirect("view.php?id={$tripId}&success=joined");

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Trip::join error: " . $e->getMessage());
    redirect("view.php?id={$tripId}&error=failed");
}
?>
