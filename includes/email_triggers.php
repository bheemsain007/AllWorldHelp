<?php
// includes/email_triggers.php
// Task T35 — Automated email trigger functions (call these after the relevant DB action)

require_once __DIR__ . '/Email.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Trigger welcome email after new user registration.
 */
function triggerWelcomeEmail(int $userId, string $email, string $name): void
{
    Email::sendWelcome($email, $name);
}

/**
 * Trigger password-reset email.
 */
function triggerPasswordResetEmail(string $email, string $name, string $resetToken): void
{
    Email::sendPasswordReset($email, $name, $resetToken);
}

/**
 * Trigger trip booking confirmation.
 */
function triggerTripBookingEmail(int $userId, int $tripId): void
{
    $db = Database::getInstance()->getConnection();

    $su = $db->prepare("SELECT name, email FROM ft_users WHERE user_id=?");
    $su->execute([$userId]);
    $user = $su->fetch(PDO::FETCH_ASSOC);

    $st = $db->prepare("SELECT * FROM ft_trips WHERE trip_id=?");
    $st->execute([$tripId]);
    $trip = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$trip) return;

    $duration = (int) ceil(
        (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / 86400
    );

    Email::sendTripBooking($user['email'], $user['name'], $trip['title'], [
        'destination' => $trip['destination'],
        'start_date'  => date('d M Y', strtotime($trip['start_date'])),
        'end_date'    => date('d M Y', strtotime($trip['end_date'])),
        'duration'    => $duration,
        'trip_id'     => $tripId,
        'site_url'    => 'https://fellowtraveler.com',
    ]);
}

/**
 * Trigger "trip completed — please rate" email to all participants.
 */
function triggerTripCompletedEmails(int $tripId): void
{
    $db = Database::getInstance()->getConnection();

    // Title
    $s = $db->prepare("SELECT title FROM ft_trips WHERE trip_id=?");
    $s->execute([$tripId]);
    $title = (string) $s->fetchColumn();

    // All accepted participants (excluding organizer handled separately)
    $s = $db->prepare(
        "SELECT u.user_id, u.name, u.email
           FROM ft_trip_participants tp
           JOIN ft_users u ON u.user_id = tp.user_id
          WHERE tp.trip_id=? AND tp.status='accepted'"
    );
    $s->execute([$tripId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $u) {
        Email::sendTripCompleted($u['email'], $u['name'], $title, $tripId);
    }
}

/**
 * Trigger new-message notification.
 */
function triggerNewMessageEmail(int $receiverId, int $senderId): void
{
    $db = Database::getInstance()->getConnection();

    $sr = $db->prepare("SELECT name, email FROM ft_users WHERE user_id=?");
    $sr->execute([$receiverId]);
    $receiver = $sr->fetch(PDO::FETCH_ASSOC);

    $ss = $db->prepare("SELECT name FROM ft_users WHERE user_id=?");
    $ss->execute([$senderId]);
    $senderName = (string) $ss->fetchColumn();

    if (!$receiver) return;
    Email::sendNewMessage($receiver['email'], $receiver['name'], $senderName, $senderId);
}
