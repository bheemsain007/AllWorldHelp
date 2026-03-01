<?php
/**
 * InputValidator Class
 * Fellow Traveler — Task T9
 *
 * Advanced input validation & sanitization.
 * Protects against: XSS, SQL Injection, Path Traversal, File Upload exploits.
 *
 * Quick reference:
 *   InputValidator::sanitizeString($str)             → cleaned plain text
 *   InputValidator::validateEmail($email)            → bool
 *   InputValidator::validateURL($url)                → bool
 *   InputValidator::validateInt($val, $min, $max)    → int|false
 *   InputValidator::validateFloat($val, $min, $max)  → float|false
 *   InputValidator::validateDate($date, $format)     → bool
 *   InputValidator::validatePhone($phone)            → bool
 *   InputValidator::sanitizeHTML($html, $allowed)    → string (safe HTML)
 *   InputValidator::validateFile($file, $options)    → ['success'=>bool, ...]
 *   InputValidator::validateLength($str, $min, $max) → bool
 *   InputValidator::validateAlphanumeric($str)       → bool
 *   InputValidator::escapeOutput($str)               → HTML-safe string
 */
class InputValidator
{
    // ── Allowed MIME types for file validation ─────────────────────────────
    private const MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // 1. sanitizeString()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Sanitize plain-text user input.
     * Removes NULL bytes, strips HTML tags, converts special chars to entities.
     *
     * @param  mixed  $str  Raw input
     * @return string       Safe plain text
     */
    public static function sanitizeString(mixed $str): string
    {
        if ($str === null || $str === false) return '';
        $str = (string) $str;

        // Remove NULL bytes (used in some injection attacks)
        $str = str_replace(chr(0), '', $str);

        // Trim leading/trailing whitespace
        $str = trim($str);

        // Remove all HTML/PHP tags
        $str = strip_tags($str);

        // Encode remaining special HTML characters
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $str;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. validateEmail()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate email address format.
     * Uses PHP's built-in FILTER_VALIDATE_EMAIL.
     *
     * @param  mixed  $email
     * @return bool
     */
    public static function validateEmail(mixed $email): bool
    {
        $email = trim((string)$email);

        if (empty($email)) return false;

        // RFC-compliant email validation
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. validateURL()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate a URL — must be http or https only.
     * Rejects javascript:, data:, ftp: and other dangerous schemes.
     *
     * @param  mixed  $url
     * @return bool
     */
    public static function validateURL(mixed $url): bool
    {
        $url = trim((string)$url);

        if (empty($url)) return false;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Only allow http and https
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. validateInt()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate an integer with optional min/max bounds.
     *
     * @param  mixed    $value
     * @param  int|null $min   Minimum value (inclusive)
     * @param  int|null $max   Maximum value (inclusive)
     * @return int|false       Validated integer, or false on failure
     */
    public static function validateInt(mixed $value, ?int $min = null, ?int $max = null): int|false
    {
        $options = [];
        if ($min !== null) $options['min_range'] = $min;
        if ($max !== null) $options['max_range'] = $max;

        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => $options]);

        return ($result !== false) ? (int)$result : false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. validateFloat()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate a floating-point number with optional min/max bounds.
     *
     * @param  mixed      $value
     * @param  float|null $min
     * @param  float|null $max
     * @return float|false
     */
    public static function validateFloat(mixed $value, ?float $min = null, ?float $max = null): float|false
    {
        $result = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($result === false) return false;

        $f = (float)$result;

        if ($min !== null && $f < $min) return false;
        if ($max !== null && $f > $max) return false;

        return $f;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. validateDate()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate a date string against a given format.
     *
     * @param  mixed  $date    e.g. '2026-03-01'
     * @param  string $format  PHP date format, default 'Y-m-d'
     * @return bool
     */
    public static function validateDate(mixed $date, string $format = 'Y-m-d'): bool
    {
        $date = trim((string)$date);

        if (empty($date)) return false;

        $d = DateTime::createFromFormat($format, $date);

        // Also check the formatted date matches (catches values like 2024-02-31)
        return $d !== false && $d->format($format) === $date;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. validatePhone()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate Indian mobile phone number.
     * Accepts: 10-digit starting with 6-9, strips spaces/dashes/+91 prefix.
     *
     * @param  mixed $phone
     * @return bool
     */
    public static function validatePhone(mixed $phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', (string)$phone);

        // Strip country code +91 or 0091
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 13 && str_starts_with($phone, '0091')) {
            $phone = substr($phone, 4);
        }

        // Must be 10 digits starting with 6-9
        return (bool) preg_match('/^[6-9][0-9]{9}$/', $phone);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. sanitizeHTML()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Allow only a safe subset of HTML — for rich text bio/description fields.
     * Strips all tags not in $allowed, and removes dangerous event attributes.
     *
     * @param  mixed    $html     Raw HTML input
     * @param  string[] $allowed  Permitted tag names (no angle brackets)
     * @return string             Safe HTML
     */
    public static function sanitizeHTML(mixed $html, array $allowed = ['p', 'br', 'b', 'i', 'u', 'a', 'ul', 'ol', 'li']): string
    {
        $html = (string)$html;

        // Build allowed-tags string for strip_tags (e.g. '<p><br><b>')
        $allowedTags = '<' . implode('><', $allowed) . '>';

        // Strip disallowed tags
        $html = strip_tags($html, $allowedTags);

        // Remove dangerous event attributes (onclick, onload, onerror, etc.)
        $html = preg_replace('/(<[^>]+)\s+on\w+=["\'][^"\']*["\']/i', '$1', $html);

        // Remove javascript: protocol in href/src
        $html = preg_replace('/(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '$1="#"', $html);

        // Remove style attributes (can contain CSS expressions)
        $html = preg_replace('/(<[^>]+)\s+style=["\'][^"\']*["\']/i', '$1', $html);

        // Remove data: URIs
        $html = preg_replace('/(href|src)\s*=\s*["\']?\s*data:[^"\'>\s]*/i', '$1="#"', $html);

        return trim($html);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. validateFile()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate a file upload from $_FILES.
     * Checks: upload errors, size, extension, and MIME type.
     *
     * @param  array $file     $_FILES['field'] array
     * @param  array $options  Override defaults:
     *                           'allowed_types' => ['jpg','png']
     *                           'max_size'      => bytes (default 5MB)
     *                           'required'      => bool (default true)
     * @return array ['success' => bool, 'error' => string, 'file' => array]
     */
    public static function validateFile(array $file, array $options = []): array
    {
        $defaults = [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size'      => 5 * 1024 * 1024, // 5 MB
            'required'      => true,
        ];
        $opts = array_merge($defaults, $options);

        // ── No file uploaded ─────────────────────────────────────────────────
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return $opts['required']
                ? ['success' => false, 'error' => 'No file uploaded.']
                : ['success' => true];
        }

        // ── Upload error ──────────────────────────────────────────────────────
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server max size.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form max size.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
            ];
            return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error.'];
        }

        // ── Size check ────────────────────────────────────────────────────────
        if ($file['size'] > $opts['max_size']) {
            $maxMB = round($opts['max_size'] / (1024 * 1024), 1);
            return ['success' => false, 'error' => "File too large (max {$maxMB} MB)."];
        }

        // ── Extension check ───────────────────────────────────────────────────
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $opts['allowed_types'], true)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $opts['allowed_types'])];
        }

        // ── MIME type check (prevent double-extension attacks) ─────────────────
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Build allowed MIME set from extension list
            $allowedMimes = array_filter(
                array_map(fn($e) => self::MIME_MAP[$e] ?? null, $opts['allowed_types'])
            );

            if (!in_array($mimeType, $allowedMimes, true)) {
                return ['success' => false, 'error' => 'File type mismatch. Possible spoofed extension.'];
            }
        }

        // ── Security: ensure it's actually an uploaded file ───────────────────
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid file source.'];
        }

        return ['success' => true, 'file' => $file, 'extension' => $ext];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. validateLength()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate string length (multibyte-safe).
     *
     * @param  mixed $str
     * @param  int   $min  Minimum length (default 0)
     * @param  int   $max  Maximum length (default 255)
     * @return bool
     */
    public static function validateLength(mixed $str, int $min = 0, int $max = 255): bool
    {
        $str = (string)$str;
        $len = mb_strlen($str, 'UTF-8');
        return $len >= $min && $len <= $max;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. validateAlphanumeric()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check that a string contains only letters and numbers (a-z, A-Z, 0-9).
     * Optionally allow additional characters via $extra pattern fragment.
     *
     * @param  mixed  $str
     * @param  string $extra  Additional chars to allow in regex char class, e.g. '_-'
     * @return bool
     */
    public static function validateAlphanumeric(mixed $str, string $extra = ''): bool
    {
        $str = (string)$str;

        if (empty($str)) return false;

        $pattern = '/^[a-zA-Z0-9' . preg_quote($extra, '/') . ']+$/';
        return (bool) preg_match($pattern, $str);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. escapeOutput()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Escape a string for safe HTML output.
     * Always use this when printing user-supplied data in HTML context.
     *
     * @param  mixed  $str
     * @return string       HTML-entity-encoded string
     */
    public static function escapeOutput(mixed $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: sanitizeUsername()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate username: 3-20 chars, starts with letter, letters/numbers/underscores only.
     *
     * @param  mixed $username
     * @return bool
     */
    public static function validateUsername(mixed $username): bool
    {
        $username = trim((string)$username);
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,19}$/', $username);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: sanitizeFilename()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Sanitize a filename to prevent path traversal attacks.
     * Removes ../, strips special chars, keeps only safe characters.
     *
     * @param  mixed  $filename
     * @return string
     */
    public static function sanitizeFilename(mixed $filename): string
    {
        $filename = basename((string)$filename);

        // Allow only alphanumeric, dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');

        return $filename ?: 'file';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: validatePassword()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate password strength.
     * Returns array: ['valid' => bool, 'errors' => string[]]
     *
     * @param  mixed $password
     * @param  int   $minLength   Default 6
     * @return array
     */
    public static function validatePassword(mixed $password, int $minLength = 6): array
    {
        $password = (string)$password;
        $errors   = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters.";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: sanitizeAll()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Sanitize an entire associative array of string inputs at once.
     * Skips keys in $skip (e.g. password fields that should not be stripped).
     *
     * @param  array    $data   e.g. $_POST
     * @param  string[] $skip   Keys to leave untouched
     * @return array
     */
    public static function sanitizeAll(array $data, array $skip = ['password', 'confirm_password']): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $skip, true)) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                $clean[$key] = self::sanitizeString($value);
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitizeAll($value, $skip);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
?>
