<?php
// trips/change_status.php
// Task T29 — Change trip status (organizer only; POST + CSRF); handles open, closed, ongoing

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

// ── POST only ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("search.php");
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    redirect("search.php?error=csrf");
}

$userId    = (int) SessionManager::get('user_id');
$tripId    = (int) ($_POST['trip_id']    ?? 0);
$newStatus =       $_POST['new_status'] ?? '';

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Validate new status ───────────────────────────────────────────────────────
$allowed = ['open', 'closed', 'ongoing'];
if (!in_array($newStatus, $allowed, true)) {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Fetch Trip & Permission ───────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip || (int) $trip['user_id'] !== $userId) {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Validate transition ───────────────────────────────────────────────────────
$currentStatus = $trip['status'];
$validTransitions = [
    'open'   => ['closed', 'ongoing'],
    'closed' => ['open',   'ongoing'],
    'full'   => ['open',   'ongoing'],
];

if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus], true)) {
    redirect("manage_status.php?trip_id={$tripId}&error=invalid_status");
}

// ── Update status ─────────────────────────────────────────────────────────────
$success = Trip::updateStatus($tripId, $newStatus);

if ($success) {
    // Notify participants of status changes they care about
    $notifyStatuses = ['ongoing'];
    if (in_array($newStatus, $notifyStatuses, true)) {
        $participants = Trip::getParticipants($tripId);
        $statusMsg = [
            'ongoing' => "Trip \"{$trip['title']}\" has started! Have a great journey! 🚀",
        ];
        foreach ($participants as $p) {
            if ((int) $p['user_id'] !== $userId) {
                createNotification(
                    (int) $p['user_id'],
                    'trip_status',
                    'Trip Status Update',
                    $statusMsg[$newStatus] ?? "Trip \"{$trip['title']}\" status changed to {$newStatus}.",
                    "../trips/view.php?id={$tripId}"
                );
            }
        }
    }
    redirect("manage_status.php?trip_id={$tripId}&success=updated");
} else {
    redirect("manage_status.php?trip_id={$tripId}&error=update_failed");
}
?>
