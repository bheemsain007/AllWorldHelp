<?php
/**
 * CSRF Protection Class
 * Fellow Traveler — Task T8
 *
 * Prevents Cross-Site Request Forgery attacks using the Synchronizer Token Pattern.
 *
 * Usage in forms:
 *   require_once 'includes/CSRF.php';
 *   // Inside <form>:
 *   echo CSRF::getTokenField();
 *
 * Usage in handlers:
 *   if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
 *       die('CSRF validation failed. Invalid request.');
 *   }
 *
 * Usage in AJAX:
 *   // In <head>: echo CSRF::getTokenMeta();
 *   // In JS: headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
 *   // In handler: $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; CSRF::validateToken($token);
 */
class CSRF
{
    // ── Configuration ─────────────────────────────────────────────────────────
    const TOKEN_LIFETIME    = 3600;        // 1 hour in seconds
    const TOKEN_SESSION_KEY = 'csrf_token';
    const TOKEN_TIME_KEY    = 'csrf_token_time';

    // ─────────────────────────────────────────────────────────────────────────
    // 1. generateToken()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Generate a cryptographically secure CSRF token and store in session.
     * Reuses the existing token if it is still valid (prevents double-form issues).
     *
     * @return string 64-char hex token
     */
    public static function generateToken(): string
    {
        // Ensure session is active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Reuse existing token if it hasn't expired yet (helps with multi-tab usage)
        if (
            isset($_SESSION[self::TOKEN_SESSION_KEY], $_SESSION[self::TOKEN_TIME_KEY]) &&
            (time() - $_SESSION[self::TOKEN_TIME_KEY]) < self::TOKEN_LIFETIME
        ) {
            return $_SESSION[self::TOKEN_SESSION_KEY];
        }

        // Generate fresh 64-char hex token (256 bits of entropy)
        $token = bin2hex(random_bytes(32));

        // Store in session
        $_SESSION[self::TOKEN_SESSION_KEY] = $token;
        $_SESSION[self::TOKEN_TIME_KEY]    = time();

        return $token;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. validateToken()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate the submitted CSRF token against the session token.
     * Uses hash_equals() to prevent timing attacks.
     *
     * @param  string $token  Token from POST data or request header
     * @return bool           true = valid, false = invalid/expired/missing
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Token must be present and non-empty
        if (empty($token)) {
            return false;
        }

        // Session token must exist
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        // Check token age
        if (isset($_SESSION[self::TOKEN_TIME_KEY])) {
            $age = time() - $_SESSION[self::TOKEN_TIME_KEY];
            if ($age > self::TOKEN_LIFETIME) {
                // Expired — clean up and reject
                self::clearToken();
                return false;
            }
        }

        // Timing-safe comparison (prevents timing side-channel attacks)
        $valid = hash_equals($_SESSION[self::TOKEN_SESSION_KEY], $token);

        if ($valid) {
            // Rotate token after successful validation (prevents token reuse)
            self::rotateToken();
        }

        return $valid;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. getTokenField()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Return an HTML hidden input field containing the CSRF token.
     * Drop inside any <form> that uses POST.
     *
     * @return string  HTML: <input type="hidden" name="csrf_token" value="...">
     */
    public static function getTokenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. getTokenMeta()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Return an HTML <meta> tag with the CSRF token — for AJAX requests.
     * Place in <head>. Read in JS via: document.querySelector('meta[name="csrf-token"]').content
     *
     * @return string  HTML: <meta name="csrf-token" content="...">
     */
    public static function getTokenMeta(): string
    {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: validateOrDie()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate token and immediately terminate with HTTP 403 if invalid.
     * Convenience method for handlers — avoids repetitive if/die blocks.
     *
     * @param string $token    Token from $_POST or header (defaults to $_POST['csrf_token'])
     * @param string $message  Custom error message
     */
    public static function validateOrDie(string $token = '', string $message = 'CSRF validation failed. Invalid or expired request.'): void
    {
        if (empty($token)) {
            $token = $_POST['csrf_token'] ?? '';
        }

        if (!self::validateToken($token)) {
            http_response_code(403);
            // If JSON client, return JSON error
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (str_contains($accept, 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => $message, 'code' => 403]);
            } else {
                echo '<h2>403 Forbidden</h2><p>' . htmlspecialchars($message) . '</p>';
            }
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: getTokenValue()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Get the raw token string (for custom form implementations).
     *
     * @return string
     */
    public static function getTokenValue(): string
    {
        return self::generateToken();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Remove the current token from session.
     */
    private static function clearToken(): void
    {
        unset($_SESSION[self::TOKEN_SESSION_KEY], $_SESSION[self::TOKEN_TIME_KEY]);
    }

    /**
     * Generate a fresh token immediately (called after successful validation).
     * Prevents replay attacks — each validated token can only be used once.
     */
    private static function rotateToken(): void
    {
        $newToken = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_SESSION_KEY] = $newToken;
        $_SESSION[self::TOKEN_TIME_KEY]    = time();
    }
}
?>
