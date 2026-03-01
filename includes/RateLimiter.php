<?php
/**
 * RateLimiter Class
 * Fellow Traveler — Task T10
 *
 * Session-based rate limiting to prevent brute-force attacks and abuse.
 * Supports: login throttling, registration throttling, API rate limits.
 *
 * Quick reference:
 *   RateLimiter::check($key, $limit, $window)         → bool (true = allowed)
 *   RateLimiter::increment($key)                      → void
 *   RateLimiter::getRemainingAttempts($key, $l, $w)   → int
 *   RateLimiter::getTimeUntilReset($key, $window)     → int (seconds)
 *   RateLimiter::reset($key)                          → void
 *
 * Usage (login example):
 *   $key = 'login_' . $email;
 *   if (!RateLimiter::check($key, 5, 900)) {
 *       $wait = RateLimiter::getTimeUntilReset($key, 900);
 *       redirect('login.php?error=ratelimit&wait=' . ceil($wait / 60));
 *   }
 *   // ... attempt login ...
 *   if ($success) { RateLimiter::reset($key); }
 *   else          { RateLimiter::increment($key); }
 */
class RateLimiter
{
    // ── Pre-configured Limits ─────────────────────────────────────────────────
    const LIMIT_LOGIN        = 5;      // max login attempts
    const WINDOW_LOGIN       = 900;    // 15 minutes

    const LIMIT_REGISTER     = 3;      // max registrations from same IP
    const WINDOW_REGISTER    = 86400;  // 24 hours

    const LIMIT_PASSWORD_RESET = 3;    // max password reset requests
    const WINDOW_PASSWORD_RESET = 3600; // 1 hour

    const LIMIT_API          = 100;    // max API calls
    const WINDOW_API         = 3600;   // 1 hour

    const LIMIT_TRIP_CREATE  = 10;     // max trips created per day
    const WINDOW_TRIP_CREATE = 86400;  // 24 hours

    // Session namespace
    private const SESSION_KEY = '_ft_rate_limit';

    // ─────────────────────────────────────────────────────────────────────────
    // 1. check()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check if the rate limit has been exceeded for a given key.
     *
     * @param  string $key    Unique identifier, e.g. "login_user@email.com"
     * @param  int    $limit  Maximum allowed attempts in the window
     * @param  int    $window Time window in seconds (default 900 = 15 min)
     * @return bool           true = within limit (allowed), false = limit exceeded (blocked)
     */
    public static function check(string $key, int $limit = 5, int $window = 900): bool
    {
        self::ensureSession();
        self::cleanOldAttempts($key, $window);

        $attempts = self::getAttempts($key);

        return count($attempts) < $limit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. increment()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Record an attempt — call this after each failed action.
     *
     * @param string $key Unique identifier
     */
    public static function increment(string $key): void
    {
        self::ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key] = [];
        }

        // Record the timestamp of this attempt
        $_SESSION[self::SESSION_KEY][$key][] = time();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. getRemainingAttempts()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * How many more attempts are allowed before the limit is reached.
     *
     * @param  string $key
     * @param  int    $limit
     * @param  int    $window
     * @return int    Remaining attempts (0 = fully blocked)
     */
    public static function getRemainingAttempts(string $key, int $limit, int $window): int
    {
        self::cleanOldAttempts($key, $window);
        $attempts = self::getAttempts($key);
        return max(0, $limit - count($attempts));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. getTimeUntilReset()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Seconds remaining until the oldest attempt falls outside the window.
     * Returns 0 if no attempts recorded.
     *
     * @param  string $key
     * @param  int    $window
     * @return int    Seconds until cooldown ends
     */
    public static function getTimeUntilReset(string $key, int $window): int
    {
        $attempts = self::getAttempts($key);

        if (empty($attempts)) {
            return 0;
        }

        // The counter resets when the oldest attempt exits the window
        $oldestAttempt = min($attempts);
        $resetAt       = $oldestAttempt + $window;
        $secondsLeft   = $resetAt - time();

        return max(0, $secondsLeft);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. reset()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Clear all recorded attempts for a key.
     * Call on successful login to allow re-authentication without waiting.
     *
     * @param string $key
     */
    public static function reset(string $key): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY][$key]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: checkOrDie()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Check rate limit and immediately terminate with HTTP 429 if exceeded.
     * Convenience method for API endpoints.
     *
     * @param string $key
     * @param int    $limit
     * @param int    $window
     */
    public static function checkOrDie(string $key, int $limit = 100, int $window = 3600): void
    {
        if (!self::check($key, $limit, $window)) {
            $wait = self::getTimeUntilReset($key, $window);
            http_response_code(429); // Too Many Requests
            header('Retry-After: ' . $wait);
            header('Content-Type: application/json');
            echo json_encode([
                'error'       => 'Rate limit exceeded.',
                'retry_after' => $wait,
                'message'     => 'Too many requests. Try again in ' . ceil($wait / 60) . ' minute(s).',
            ]);
            exit;
        }
        self::increment($key);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: isBlocked()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Alias for !check() — reads more naturally in if statements.
     *
     * @param  string $key
     * @param  int    $limit
     * @param  int    $window
     * @return bool           true = blocked, false = allowed
     */
    public static function isBlocked(string $key, int $limit = 5, int $window = 900): bool
    {
        return !self::check($key, $limit, $window);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BONUS: getStatus()
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Get a full status snapshot for a rate-limit key.
     *
     * @param  string $key
     * @param  int    $limit
     * @param  int    $window
     * @return array  ['allowed', 'attempts', 'remaining', 'reset_in', 'limit', 'window']
     */
    public static function getStatus(string $key, int $limit, int $window): array
    {
        self::cleanOldAttempts($key, $window);
        $attempts   = count(self::getAttempts($key));
        $remaining  = max(0, $limit - $attempts);
        $allowed    = $remaining > 0;
        $resetIn    = self::getTimeUntilReset($key, $window);

        return [
            'allowed'   => $allowed,
            'attempts'  => $attempts,
            'remaining' => $remaining,
            'reset_in'  => $resetIn,
            'limit'     => $limit,
            'window'    => $window,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Start session if not already active.
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize namespace
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    /**
     * Retrieve raw timestamps for a key.
     */
    private static function getAttempts(string $key): array
    {
        return $_SESSION[self::SESSION_KEY][$key] ?? [];
    }

    /**
     * Remove timestamps older than $window seconds.
     */
    private static function cleanOldAttempts(string $key, int $window): void
    {
        self::ensureSession();

        $cutoff   = time() - $window;
        $attempts = self::getAttempts($key);

        // Keep only attempts within the time window
        $attempts = array_values(array_filter($attempts, fn($ts) => $ts > $cutoff));

        $_SESSION[self::SESSION_KEY][$key] = $attempts;
    }
}
?>
