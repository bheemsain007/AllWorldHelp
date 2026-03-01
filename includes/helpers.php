<?php

/**
 * ============================================================
 * Helper Functions Library
 * Fellow Traveler Platform
 * Task T4 — Helper Functions
 * ============================================================
 *
 * 15 reusable utility functions organised by category:
 *
 *   CATEGORY              FUNCTIONS
 *   ─────────────────     ───────────────────────────────────
 *   Database  (1)         getSetting()
 *   Security  (2)         sanitizeInput(), generateToken()
 *   Validation (3)        isValidEmail(), isValidPhone(), isValidUsername()
 *   String    (3)         generateSlug(), truncateText(), formatMoney()
 *   File      (2)         uploadImage(), deleteFile()
 *   Date/Time (2)         formatDate(), calculateAge()
 *   Utility   (2)         redirect(), jsonResponse()
 *
 * USAGE:
 *   require_once 'config/database.php';   // always first
 *   require_once 'includes/helpers.php';  // then helpers
 *
 * ============================================================
 */

// Guard: prevent duplicate inclusion errors
if (defined('HELPERS_LOADED')) return;
define('HELPERS_LOADED', true);


// ============================================================
// CATEGORY 1 — DATABASE HELPER (1 function)
// ============================================================

/**
 * Fetch a single setting value from ft_admin_settings.
 *
 * Checks if already defined in database.php to avoid redeclaration.
 * Returns the setting's value, or $default if the key doesn't exist.
 *
 * @param  string $key     The setting_key to look up
 * @param  mixed  $default Value returned when key is not found (default: null)
 * @return mixed           Setting value string, or $default
 *
 * Examples:
 *   getSetting('site_name')              // → "Fellow Traveler"
 *   getSetting('coins_enabled')          // → "1"
 *   getSetting('missing_key', 'default') // → "default"
 */
if (!function_exists('getSetting')) {
    function getSetting(string $key, mixed $default = null): mixed
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                "SELECT setting_value FROM ft_admin_settings WHERE setting_key = ? LIMIT 1"
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : $default;
        } catch (PDOException $e) {
            error_log('[FellowTraveler] getSetting() error: ' . $e->getMessage());
            return $default;
        }
    }
}


// ============================================================
// CATEGORY 2 — SECURITY HELPERS (2 functions)
// ============================================================

/**
 * Sanitize raw user input to prevent XSS attacks.
 *
 * Trims whitespace, strips HTML/PHP tags, and converts special
 * characters to HTML entities. Always call before storing or
 * displaying any user-supplied data.
 *
 * @param  string|null $input Raw input value (e.g. from $_POST, $_GET)
 * @return string             Cleaned, safe string
 *
 * Examples:
 *   sanitizeInput('<script>alert(1)</script>')  // → "&lt;script&gt;alert(1)&lt;/script&gt;"
 *   sanitizeInput('  John Doe  ')               // → "John Doe"
 *   sanitizeInput("O'Brien")                    // → "O&#039;Brien"
 */
function sanitizeInput(string|null $input): string
{
    if ($input === null) return '';
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
}


/**
 * Generate a cryptographically secure random token.
 *
 * Uses random_int() (PHP 7.0+) for true randomness — suitable for
 * CSRF tokens, password reset links, email verification links, API keys.
 *
 * @param  int    $length Number of characters in the token (default: 32)
 * @return string         Random alphanumeric token
 *
 * Examples:
 *   generateToken()     // → "aB3xK9mZ..." (32 chars)
 *   generateToken(64)   // → longer token for password reset
 *   generateToken(6)    // → "4Xm2Kp"   short OTP-style code
 */
function generateToken(int $length = 32): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max        = strlen($characters) - 1;
    $token      = '';

    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[random_int(0, $max)];
    }

    return $token;
}


// ============================================================
// CATEGORY 3 — VALIDATION HELPERS (3 functions)
// ============================================================

/**
 * Validate an email address format.
 *
 * Uses PHP's built-in FILTER_VALIDATE_EMAIL which checks RFC 5322
 * compliance including @ symbol, domain, and TLD presence.
 *
 * @param  string $email Email address to validate
 * @return bool          true if valid format, false otherwise
 *
 * Examples:
 *   isValidEmail('user@example.com')  // → true
 *   isValidEmail('user@.com')         // → false
 *   isValidEmail('notanemail')        // → false
 *   isValidEmail('a@b.co')            // → true
 */
function isValidEmail(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}


/**
 * Validate an Indian mobile phone number.
 *
 * Strips all non-numeric characters first (handles +91, spaces, dashes).
 * Then checks for exactly 10 digits starting with 6, 7, 8, or 9
 * (valid Indian mobile prefixes as per TRAI).
 *
 * @param  string $phone Phone number in any common format
 * @return bool          true if valid Indian mobile number, false otherwise
 *
 * Examples:
 *   isValidPhone('9876543210')      // → true
 *   isValidPhone('+91 98765 43210') // → true  (strips +91 and spaces)
 *   isValidPhone('5876543210')      // → false (starts with 5, invalid)
 *   isValidPhone('987654321')       // → false (only 9 digits)
 *   isValidPhone('abcd123456')      // → false (non-numeric)
 */
function isValidPhone(string $phone): bool
{
    // Strip everything except digits
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Remove leading 91 (country code) if 12 digits total
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    }

    // Must be exactly 10 digits, starting with 6-9
    return (bool) preg_match('/^[6-9][0-9]{9}$/', $phone);
}


/**
 * Validate a username format.
 *
 * Rules enforced:
 *   - Length: 3 to 20 characters
 *   - Must start with a letter (a-z or A-Z)
 *   - Allowed characters: letters, digits, underscore only
 *   - No spaces, no special chars like @, -, #, etc.
 *
 * @param  string $username Username to validate
 * @return bool             true if valid format, false otherwise
 *
 * Examples:
 *   isValidUsername('john_doe')   // → true
 *   isValidUsername('user123')    // → true
 *   isValidUsername('ab')         // → false  (too short, < 3 chars)
 *   isValidUsername('user@name')  // → false  (@ not allowed)
 *   isValidUsername('1user')      // → false  (must start with letter)
 *   isValidUsername('this_username_is_too_long_for_rules') // → false
 */
function isValidUsername(string $username): bool
{
    return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,19}$/', $username);
}


// ============================================================
// CATEGORY 4 — STRING HELPERS (3 functions)
// ============================================================

/**
 * Generate a URL-friendly slug from any text.
 *
 * Converts text to lowercase, removes special characters and accents,
 * replaces spaces with hyphens, and collapses multiple hyphens.
 * Used for trip URLs, story slugs, destination pages.
 *
 * @param  string $text Input text to slugify
 * @return string       URL-safe lowercase slug
 *
 * Examples:
 *   generateSlug('Trip to Goa Beach')      // → "trip-to-goa-beach"
 *   generateSlug('Best Cafés & Beaches')   // → "best-cafes-beaches"
 *   generateSlug('  Hello   World  ')      // → "hello-world"
 *   generateSlug('Leh–Ladakh 2026!')       // → "leh-ladakh-2026"
 */
function generateSlug(string $text): string
{
    // Convert to lowercase
    $text = mb_strtolower($text, 'UTF-8');

    // Transliterate accented characters (é→e, ü→u, etc.)
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

    // Replace any non-alphanumeric character (except hyphens) with a space
    $text = preg_replace('/[^a-z0-9\s-]/', ' ', $text);

    // Replace spaces and multiple hyphens with a single hyphen
    $text = preg_replace('/[\s-]+/', '-', trim($text));

    // Strip leading/trailing hyphens
    return trim($text, '-');
}


/**
 * Truncate a long string and append ellipsis.
 *
 * Word-aware: tries not to cut in the middle of a word unless the
 * first word itself exceeds $length. Safe for display in cards, listings.
 *
 * @param  string $text   Input string to shorten
 * @param  int    $length Maximum character count before truncating (default: 100)
 * @return string         Original string if short, or truncated + "..."
 *
 * Examples:
 *   truncateText('Short text', 100)                          // → "Short text"
 *   truncateText('A very long trip description here...', 20) // → "A very long trip..."
 *   truncateText('OneVeryLongWordWithoutSpaces', 10)         // → "OneVeryLon..."
 */
function truncateText(string $text, int $length = 100): string
{
    $text = trim($text);

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    // Try to cut at a word boundary
    $truncated = mb_substr($text, 0, $length, 'UTF-8');
    $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');

    if ($lastSpace !== false && $lastSpace > ($length * 0.6)) {
        $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
    }

    return rtrim($truncated) . '...';
}


/**
 * Format a numeric amount as Indian Rupees.
 *
 * Adds the ₹ symbol and applies Indian number formatting
 * (lakhs and crores style: 1,00,000 rather than 100,000).
 * Rounds to 0 decimal places by default for cleaner display.
 *
 * @param  int|float $amount   Numeric amount to format
 * @param  int       $decimals Decimal places (default: 0 for whole rupees)
 * @return string              Formatted string like "₹5,000" or "₹1,00,000"
 *
 * Examples:
 *   formatMoney(5000)        // → "₹5,000"
 *   formatMoney(100000)      // → "₹1,00,000"
 *   formatMoney(2500.75, 2)  // → "₹2,500.75"
 *   formatMoney(0)           // → "₹0"
 */
function formatMoney(int|float $amount, int $decimals = 0): string
{
    // Use Indian number formatting (2,2,3 grouping)
    $formatted = number_format(abs($amount), $decimals);

    // Re-apply Indian grouping for numbers >= 1 lakh
    if (abs($amount) >= 100000) {
        // Strip existing commas, split at last 3 digits, group rest by 2
        $str     = number_format(abs($amount), $decimals, '.', '');
        [$int, $dec] = array_pad(explode('.', $str), 2, '');
        $last3   = substr($int, -3);
        $rest    = substr($int, 0, -3);
        $grouped = $rest ? ltrim(chunk_split(strrev($rest), 2, ','), ',') : '';
        $grouped = strrev($grouped);
        $formatted = $grouped ? $grouped . ',' . $last3 : $last3;
        if ($decimals > 0 && $dec) $formatted .= '.' . $dec;
    }

    $prefix = $amount < 0 ? '-₹' : '₹';
    return $prefix . $formatted;
}


// ============================================================
// CATEGORY 5 — FILE HELPERS (2 functions)
// ============================================================

/**
 * Handle an image file upload with full validation.
 *
 * Validates MIME type (not just extension), file size, and uses a
 * unique timestamped filename to prevent overwrites and path traversal.
 * Creates destination directory if it doesn't exist.
 *
 * @param  array  $file $_FILES array element (e.g. $_FILES['profile_photo'])
 * @param  string $path Destination directory path (default: "uploads/")
 * @return array        ['success' => bool, 'filename' => string, 'error' => string]
 *
 * Example:
 *   $result = uploadImage($_FILES['photo'], 'uploads/profiles/');
 *   if ($result['success']) {
 *       $db_filename = $result['filename']; // save this to database
 *   } else {
 *       echo $result['error'];
 *   }
 */
function uploadImage(array $file, string $path = 'uploads/'): array
{
    // Allowed MIME types and their extensions
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $max_size = 5 * 1024 * 1024; // 5 MB

    // Check for upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit (php.ini).',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $msg = $upload_errors[$file['error'] ?? -1] ?? 'Unknown upload error.';
        return ['success' => false, 'filename' => '', 'error' => $msg];
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'filename' => '', 'error' => 'File too large. Maximum size is 5 MB.'];
    }

    // Validate MIME type using finfo (more secure than checking extension)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowed_mimes)) {
        return ['success' => false, 'filename' => '', 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP.'];
    }

    $extension = $allowed_mimes[$mime];

    // Create destination directory if it doesn't exist
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    // Generate unique filename: timestamp + random suffix
    $filename    = time() . '_' . generateToken(8) . '.' . $extension;
    $destination = rtrim($path, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => '', 'error' => 'Failed to save file. Check folder permissions (755).'];
    }

    return ['success' => true, 'filename' => $filename, 'error' => ''];
}


/**
 * Safely delete a file if it exists.
 *
 * Performs an existence check before attempting deletion to avoid PHP
 * warnings. Skips placeholder/default images to protect shared assets.
 *
 * @param  string $filepath Absolute or relative path to the file
 * @return bool             true if deleted, false if file didn't exist or delete failed
 *
 * Examples:
 *   deleteFile('uploads/profiles/avatar_123.jpg')  // → true  (deleted)
 *   deleteFile('uploads/profiles/nonexistent.jpg') // → false (not found)
 *   deleteFile('uploads/default_avatar.png')       // → false (protected default)
 */
function deleteFile(string $filepath): bool
{
    // Protect default/placeholder images from accidental deletion
    $protected = ['default_avatar.png', 'default_trip.jpg', 'placeholder.jpg'];
    foreach ($protected as $guard) {
        if (str_ends_with($filepath, $guard)) {
            return false;
        }
    }

    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }

    return false;
}


// ============================================================
// CATEGORY 6 — DATE / TIME HELPERS (2 functions)
// ============================================================

/**
 * Format a date or datetime string into a human-readable format.
 *
 * Accepts any date string that PHP's strtotime() understands, including
 * MySQL DATETIME, TIMESTAMP, and DATE columns.
 *
 * @param  string|null $date   Date string (e.g. "2026-02-20" or "2026-02-20 14:30:00")
 * @param  string      $format PHP date() format string (default: "d M Y")
 * @return string              Formatted date, or "N/A" if input is invalid
 *
 * Common formats:
 *   "d M Y"       → "20 Feb 2026"
 *   "d/m/Y"       → "20/02/2026"
 *   "D, d M Y"    → "Fri, 20 Feb 2026"
 *   "d M Y, h:i A"→ "20 Feb 2026, 02:30 PM"
 *   "M Y"         → "Feb 2026"
 *
 * Examples:
 *   formatDate('2026-02-20')                     // → "20 Feb 2026"
 *   formatDate('2026-02-20 14:30:00', 'h:i A')  // → "02:30 PM"
 *   formatDate(null)                             // → "N/A"
 */
function formatDate(string|null $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'N/A';
    }

    return date($format, $timestamp);
}


/**
 * Calculate age in years from a date of birth.
 *
 * Accurately handles leap years and the day-of-year boundary
 * (birthday hasn't occurred yet this year → returns one less).
 *
 * @param  string|null $dob Date of birth in any strtotime()-compatible format
 * @return int              Age in full years, or 0 if invalid input
 *
 * Examples:
 *   calculateAge('1995-05-15')  // → 30  (as of Feb 2026)
 *   calculateAge('2008-03-01')  // → 17  (hasn't turned 18 yet in Feb 2026)
 *   calculateAge('2000-01-01')  // → 26
 *   calculateAge(null)          // → 0
 */
function calculateAge(string|null $dob): int
{
    if (empty($dob)) return 0;

    try {
        $birth   = new DateTime($dob);
        $today   = new DateTime('today');
        $diff    = $today->diff($birth);
        return abs($diff->y);
    } catch (Exception $e) {
        return 0;
    }
}


// ============================================================
// CATEGORY 7 — UTILITY HELPERS (2 functions)
// ============================================================

/**
 * Redirect the browser to another URL and stop script execution.
 *
 * Sends an HTTP Location header. Script execution stops immediately
 * via exit() so no further PHP code runs after the redirect.
 *
 * @param  string $url    Destination URL (absolute or relative)
 * @param  int    $code   HTTP status code (default: 302 Found; use 301 for permanent)
 * @return void           This function never returns
 *
 * Examples:
 *   redirect('login.php');                         // relative redirect
 *   redirect('https://fellowtraveler.com/home');   // absolute redirect
 *   redirect('login.php', 301);                    // permanent redirect
 *   redirect($_SERVER['HTTP_REFERER'] ?? '/');     // back to previous page
 */
function redirect(string $url, int $code = 302): void
{
    // Clear any output buffers to ensure headers can be sent
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Location: ' . $url, true, $code);
    exit();
}


/**
 * Send a JSON-encoded HTTP response and stop script execution.
 *
 * Sets the correct Content-Type header, encodes the data as JSON,
 * and sets the HTTP status code. Ideal for AJAX/API endpoints.
 *
 * @param  mixed $data    Data to encode (array, object, string, etc.)
 * @param  int   $status  HTTP status code (default: 200 OK)
 * @return void           This function never returns
 *
 * Common status codes:
 *   200 → OK (success)
 *   201 → Created (new resource)
 *   400 → Bad Request (validation error)
 *   401 → Unauthorized (not logged in)
 *   403 → Forbidden (no permission)
 *   404 → Not Found
 *   500 → Internal Server Error
 *
 * Examples:
 *   jsonResponse(['success' => true, 'message' => 'Logged in!']);
 *   jsonResponse(['success' => false, 'error' => 'Invalid email'], 400);
 *   jsonResponse(['user_id' => 42, 'token' => $token], 201);
 */
function jsonResponse(mixed $data, int $status = 200): void
{
    // Remove any accidental output before headers
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}


// ============================================================
// END OF HELPERS
// Fellow Traveler Helper Functions Library v1.0
// Total functions: 15
// ============================================================
