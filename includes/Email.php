<?php
// includes/Email.php
// Task T35 — Email sending class with PHPMailer + queue system

require_once __DIR__ . '/../config/smtp_config.php';
require_once __DIR__ . '/../config/database.php';

// ── Auto-load PHPMailer from vendor or manual install ─────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Graceful fallback: if PHPMailer not installed, queue all emails
    if (!defined('EMAIL_QUEUE')) define('EMAIL_QUEUE', true);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Email
{
    // ── Public entry point ─────────────────────────────────────────────────────
    public static function send(
        string $to,
        string $subject,
        string $body,
        bool   $isHtml = true
    ): bool {
        if (!EMAIL_ENABLED) return false;

        if (EMAIL_QUEUE) {
            return self::addToQueue($to, $subject, $body, $isHtml);
        }
        return self::sendNow($to, $subject, $body, $isHtml);
    }

    // ── Direct send via PHPMailer ──────────────────────────────────────────────
    public static function sendNow(
        string $to,
        string $subject,
        string $body,
        bool   $isHtml = true
    ): bool {
        if (!class_exists(PHPMailer::class)) {
            // PHPMailer not installed — fall back to queue
            return self::addToQueue($to, $subject, $body, $isHtml);
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            if ($isHtml) {
                $mail->AltBody = strip_tags($body);
            }

            $mail->send();
            self::log($to, $subject, 'sent');
            return true;
        } catch (MailException $e) {
            self::log($to, $subject, 'failed', $mail->ErrorInfo);
            if (EMAIL_DEBUG) error_log("Email Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    // ── Template loader ────────────────────────────────────────────────────────
    public static function loadTemplate(string $name, array $vars = []): ?string
    {
        $path = __DIR__ . '/../templates/email/' . $name . '.html';
        if (!file_exists($path)) return null;

        $tpl = file_get_contents($path);
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $tpl);
        }
        return $tpl;
    }

    // ── High-level send helpers ────────────────────────────────────────────────
    public static function sendWelcome(string $email, string $name): bool
    {
        $body = self::loadTemplate('welcome', [
            'user_name' => $name,
            'site_url'  => 'https://fellowtraveler.com',
        ]);
        return $body ? self::send($email, '🌍 Welcome to Fellow Traveler!', $body) : false;
    }

    public static function sendPasswordReset(string $email, string $name, string $token): bool
    {
        $url  = 'https://fellowtraveler.com/auth/reset_password.php?token=' . urlencode($token);
        $body = self::loadTemplate('password_reset', ['user_name' => $name, 'reset_url' => $url]);
        return $body ? self::send($email, 'Reset Your Password — Fellow Traveler', $body) : false;
    }

    public static function sendTripBooking(string $email, string $name, string $tripTitle, array $details): bool
    {
        $body = self::loadTemplate('trip_booking', array_merge(
            ['user_name' => $name, 'trip_title' => $tripTitle],
            $details
        ));
        return $body ? self::send($email, '✅ Trip Booking Confirmed: ' . $tripTitle, $body) : false;
    }

    public static function sendTripCompleted(string $email, string $name, string $tripTitle, int $tripId): bool
    {
        $body = self::loadTemplate('trip_completed', [
            'user_name'  => $name,
            'trip_title' => $tripTitle,
            'rate_url'   => "https://fellowtraveler.com/trips/rate_trip.php?trip_id={$tripId}",
        ]);
        return $body ? self::send($email, '⭐ Rate Your Trip: ' . $tripTitle, $body) : false;
    }

    public static function sendNewMessage(string $email, string $recipientName, string $senderName, int $senderId): bool
    {
        $chatUrl = "https://fellowtraveler.com/messages/chat.php?user_id={$senderId}";
        $body    = self::buildSimpleEmail(
            "💬 New Message from {$senderName}",
            "Hi {$recipientName},<br><br>You have a new message from <strong>{$senderName}</strong>.",
            'View Message',
            $chatUrl
        );
        return self::send($email, "New Message from {$senderName}", $body);
    }

    // ── Simple inline email builder (no template file needed) ─────────────────
    public static function buildSimpleEmail(string $heading, string $bodyHtml, string $btnText = '', string $btnUrl = ''): string
    {
        $btn = $btnText
            ? "<div style='text-align:center;margin:28px 0;'><a href='{$btnUrl}' style='display:inline-block;padding:12px 30px;background:#1d4ed8;color:white;text-decoration:none;border-radius:6px;font-weight:700;'>{$btnText}</a></div>"
            : '';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:0;font-family:Arial,sans-serif;background:#f5f5f5;'>
<div style='max-width:600px;margin:0 auto;background:white;'>
<div style='background:linear-gradient(135deg,#1d4ed8,#7c3aed);padding:28px;text-align:center;color:white;'>
<h1 style='margin:0;font-size:22px;'>🌍 Fellow Traveler</h1></div>
<div style='padding:32px;'><h2 style='color:#0f172a;'>{$heading}</h2><p style='color:#374151;line-height:1.6;'>{$bodyHtml}</p>{$btn}</div>
<div style='background:#f8fafc;padding:20px;text-align:center;color:#64748b;font-size:12px;'>
© 2026 Fellow Traveler. All rights reserved.</div>
</div></body></html>";
    }

    // ── Queue helpers ──────────────────────────────────────────────────────────
    private static function addToQueue(string $to, string $subject, string $body, bool $isHtml): bool
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO ft_email_queue (to_email, subject, body, is_html, status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())"
        );
        return $stmt->execute([$to, $subject, $body, $isHtml ? 1 : 0]);
    }

    // ── Logging ────────────────────────────────────────────────────────────────
    private static function log(string $to, string $subject, string $status, string $error = ''): void
    {
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO ft_email_logs (to_email, subject, status, error_message, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$to, $subject, $status, $error ?: null]);
        } catch (\Throwable $e) {
            // Silent — logging failure must not break application
        }
    }
}
