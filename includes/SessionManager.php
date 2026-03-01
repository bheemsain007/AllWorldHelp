<?php
/**
 * SessionManager Class
 * Fellow Traveler — Task T7
 *
 * Advanced session handling with:
 *   - Secure cookie parameters (HttpOnly, SameSite, Secure)
 *   - 24-hour inactivity timeout
 *   - Automatic session ID regeneration every 30 minutes
 *   - Session fixation & hijacking protection
 *   - IP + User-Agent fingerprinting
 *
 * Usage:
 *   SessionManager::start();          // on every page
 *   SessionManager::set('key', val);
 *   SessionManager::get('key');
 *   SessionManager::isActive();       // check login
 *   SessionManager::destroy();        // logout
 */
class SessionManager
{
    // ── Configuration ────────────────────────────────────────────────────────
    const SESSION_LIFETIME       = 86400; // 24 hours (seconds)
    const REGENERATE_INTERVAL    = 1800;  // 30 minutes (seconds)
    const SESSION_COOKIE_NAME    = 'FT_SESSION';

    // ─────────────────────────────────────────────────────────────────────────
    // 1. start()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Initialize the session with secure cookie parameters.
     * Call this at the top of every page (instead of session_start()).
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return; // Already started
        }

        // Custom session name (harder to guess than PHPSESSID)
        session_name(self::SESSION_COOKIE_NAME);

        // Secure cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,                              // 0 = browser-session cookie (lifetime enforced in PHP)
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,                           // Block JavaScript access
            'samesite' => 'Strict',                       // CSRF protection
        ]);

        session_start();

        // ── Initialize metadata on brand-new session ─────────────────────────
        if (!isset($_SESSION['_ft_initialized'])) {
            $_SESSION['_ft_initialized']      = time();
            $_SESSION['_ft_last_activity']    = time();
            $_SESSION['_ft_last_regeneration']= time();
            $_SESSION['_ft_ip']               = $_SERVER['REMOTE_ADDR']     ?? '';
            $_SESSION['_ft_ua']               = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. destroy()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Completely destroy the session — use for logout.
     */
    public static function destroy(): void
    {
        // Clear all session data
        $_SESSION = [];

        // Delete the session cookie from the client
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the server-side session
        session_destroy();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. regenerate()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Regenerate session ID — call after login and every 30 minutes.
     * Prevents session fixation attacks.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true); // true = delete old session file
        $_SESSION['_ft_last_regeneration'] = time();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. set()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Store a value in the session.
     *
     * @param string $key   Session key
     * @param mixed  $value Any serializable value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. get()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Retrieve a value from the session.
     *
     * @param string $key     Session key
     * @param mixed  $default Default if key not set
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. checkTimeout()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check if the session has timed out due to inactivity.
     * Also triggers auto-regeneration every REGENERATE_INTERVAL seconds.
     *
     * @return bool true = session timed out (and destroyed), false = still active
     */
    public static function checkTimeout(): bool
    {
        if (!isset($_SESSION['_ft_last_activity'])) {
            return false; // Not a tracked session
        }

        $elapsed = time() - $_SESSION['_ft_last_activity'];

        if ($elapsed > self::SESSION_LIFETIME) {
            self::destroy();
            return true; // Timed out
        }

        // Auto-regenerate session ID every 30 minutes
        if (isset($_SESSION['_ft_last_regeneration'])) {
            $sinceRegen = time() - $_SESSION['_ft_last_regeneration'];
            if ($sinceRegen > self::REGENERATE_INTERVAL) {
                self::regenerate();
            }
        }

        return false; // Still active
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. updateActivity()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Refresh the last-activity timestamp.
     * Call this on every protected page load to reset the inactivity timer.
     */
    public static function updateActivity(): void
    {
        $_SESSION['_ft_last_activity'] = time();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. isActive()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check whether the current session is valid and the user is logged in.
     *
     * @return bool true = logged in & not timed out
     */
    public static function isActive(): bool
    {
        // Session must be started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        // User must be logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check timeout (also handles auto-regeneration)
        if (self::checkTimeout()) {
            return false; // Expired
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: remove()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Remove a single key from the session.
     *
     * @param string $key
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: has()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check if a session key exists and is not null.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: flash()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Set a flash message — read once then auto-deleted.
     * Write:  SessionManager::flash('success', 'Profile updated!');
     * Read:   SessionManager::getFlash('success');
     *
     * @param string $type    e.g. 'success', 'error', 'info'
     * @param string $message
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_ft_flash'][$type] = $message;
    }

    /**
     * Retrieve and delete a flash message.
     *
     * @param string $type
     * @param string $default
     * @return string
     */
    public static function getFlash(string $type, string $default = ''): string
    {
        $msg = $_SESSION['_ft_flash'][$type] ?? $default;
        unset($_SESSION['_ft_flash'][$type]);
        return $msg;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: userId() / userName() — convenience getters
    // ─────────────────────────────────────────────────────────────────────────
    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    public static function userName(): string
    {
        return (string)($_SESSION['name'] ?? '');
    }

    public static function userEmail(): string
    {
        return (string)($_SESSION['email'] ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: validateFingerprint()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Validate IP + User-Agent fingerprint to detect session hijacking.
     * Call after isActive() for high-security pages.
     *
     * @return bool false = fingerprint mismatch (possible hijack)
     */
    public static function validateFingerprint(): bool
    {
        $currentIp = $_SERVER['REMOTE_ADDR']     ?? '';
        $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $storedIp  = $_SESSION['_ft_ip'] ?? '';
        $storedUa  = $_SESSION['_ft_ua'] ?? '';

        // Allow IP mismatch (mobile networks change IPs), but flag UA mismatch
        if (!empty($storedUa) && $currentUa !== $storedUa) {
            self::destroy();
            return false; // Likely hijack
        }

        return true;
    }
}
?>
