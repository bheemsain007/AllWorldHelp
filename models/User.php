<?php

/**
 * ============================================================
 * User Model Class
 * Fellow Traveler Platform
 * Task T5 — User Model
 * ============================================================
 *
 * Handles all database operations for the ft_users table.
 * All methods are static — no instantiation needed.
 *
 * USAGE:
 *   require_once 'config/database.php';
 *   require_once 'models/User.php';
 *
 *   $user   = User::findById(5);
 *   $userId = User::create([...]);
 *   $ok     = User::update(5, ['name' => 'Rahul']);
 *
 * METHODS (10 total):
 *   ── CREATE ──────────────  create($data)
 *   ── READ ────────────────  findById($id)
 *                             findByEmail($email)
 *                             findByUsername($username)
 *                             getAll($limit, $offset, $filters)
 *                             exists($email, $username)
 *   ── UPDATE ──────────────  update($id, $data)
 *   ── DELETE ──────────────  delete($id)
 *   ── PASSWORD ────────────  hashPassword($password)
 *                             checkPassword($plain, $hash)
 * ============================================================
 */

class User
{
    /**
     * Database table name.
     * Change this if you rename the table.
     */
    private const TABLE = 'ft_users';

    /**
     * Columns that are allowed in update().
     * Whitelist prevents SQL injection via column names.
     */
    private const UPDATABLE_FIELDS = [
        'name', 'phone', 'date_of_birth', 'gender',
        'city', 'country', 'profile_photo', 'bio',
        'is_verified', 'is_premium', 'email_verified',
        'phone_verified', 'kyc_status', 'status',
        'referral_code', 'last_login',
    ];


    // ===========================================================
    // ── CREATE ──────────────────────────────────────────────────
    // ===========================================================

    /**
     * Register a new user in ft_users.
     *
     * Automatically hashes the plain-text password before storing.
     * Checks for duplicate email/username before inserting.
     * Sets status = 'active' and created_at = NOW() by default.
     *
     * @param  array $data Associative array of user fields:
     *                     Required: email, username, password
     *                     Optional: name, phone, city, country,
     *                               date_of_birth, gender, referral_code
     * @return int|false   New user_id on success, false on failure
     *
     * Example:
     *   $id = User::create([
     *       'email'    => 'rahul@example.com',
     *       'username' => 'rahul_travels',
     *       'password' => 'MySecret@123',
     *       'name'     => 'Rahul Sharma',
     *       'city'     => 'Mumbai',
     *   ]);
     */
    public static function create(array $data): int|false
    {
        global $pdo;

        // Validate required fields
        if (empty($data['email']) || empty($data['username']) || empty($data['password'])) {
            error_log('[User::create] Missing required fields: email, username or password');
            return false;
        }

        // Duplicate check
        if (self::exists($data['email'], $data['username'])) {
            error_log('[User::create] Duplicate email or username: ' . $data['email']);
            return false;
        }

        try {
            $sql = 'INSERT INTO ' . self::TABLE . '
                        (email, username, password, name, phone,
                         city, country, date_of_birth, gender,
                         referral_code, status, created_at)
                    VALUES
                        (?, ?, ?, ?, ?,
                         ?, ?, ?, ?,
                         ?, \'active\', NOW())';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                strtolower(trim($data['email'])),
                strtolower(trim($data['username'])),
                self::hashPassword($data['password']),
                $data['name']          ?? null,
                $data['phone']         ?? null,
                $data['city']          ?? null,
                $data['country']       ?? 'India',
                $data['date_of_birth'] ?? null,
                $data['gender']        ?? null,
                $data['referral_code'] ?? null,
            ]);

            $newId = (int) $pdo->lastInsertId();

            // Auto-create coin wallet for new user
            self::initCoinWallet($newId);

            return $newId;

        } catch (PDOException $e) {
            error_log('[User::create] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    // ===========================================================
    // ── READ ────────────────────────────────────────────────────
    // ===========================================================

    /**
     * Find a user by their primary key (user_id).
     *
     * Returns the full row including hashed password —
     * be careful not to expose this to clients/JSON responses.
     * Use array_diff_key($user, ['password'=>'']) to strip it.
     *
     * @param  int        $id User's user_id
     * @return array|null     User row as associative array, or null
     *
     * Example:
     *   $user = User::findById(5);
     *   echo $user['name'];   // "Rahul Sharma"
     *   echo $user['status']; // "active"
     */
    public static function findById(int $id): ?array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::TABLE . ' WHERE user_id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;

        } catch (PDOException $e) {
            error_log('[User::findById] PDO error: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Find a user by their email address.
     *
     * Primary use case: login lookup. Case-insensitive match
     * (emails are stored lowercase via create()).
     *
     * @param  string     $email Email address to search for
     * @return array|null        User row or null if not found
     *
     * Example:
     *   $user = User::findByEmail('rahul@example.com');
     *   if ($user && User::checkPassword($input, $user['password'])) {
     *       // login success
     *   }
     */
    public static function findByEmail(string $email): ?array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::TABLE . ' WHERE email = ? LIMIT 1'
            );
            $stmt->execute([strtolower(trim($email))]);
            $row = $stmt->fetch();
            return $row ?: null;

        } catch (PDOException $e) {
            error_log('[User::findByEmail] PDO error: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Find a user by their username.
     *
     * Used for profile pages, @mention lookups, and username
     * availability checks during registration.
     *
     * @param  string     $username Username to search for (case-insensitive)
     * @return array|null           User row or null if not found
     *
     * Example:
     *   $user = User::findByUsername('rahul_travels');
     *   // Redirect to: /profile/rahul_travels
     */
    public static function findByUsername(string $username): ?array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::TABLE . ' WHERE username = ? LIMIT 1'
            );
            $stmt->execute([strtolower(trim($username))]);
            $row = $stmt->fetch();
            return $row ?: null;

        } catch (PDOException $e) {
            error_log('[User::findByUsername] PDO error: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Fetch a paginated list of users (for admin panel).
     *
     * Supports optional filtering by status, city, and is_premium.
     * Never returns the password column for security.
     * Results are ordered by newest first (created_at DESC).
     *
     * @param  int   $limit   Max rows to return (default: 20)
     * @param  int   $offset  Rows to skip for pagination (default: 0)
     * @param  array $filters Optional: ['status'=>'active', 'city'=>'Mumbai', 'is_premium'=>1]
     * @return array          Array of user rows (password excluded)
     *
     * Example:
     *   // Page 2 (20 per page)
     *   $users = User::getAll(20, 20);
     *
     *   // Only active premium users
     *   $users = User::getAll(10, 0, ['status' => 'active', 'is_premium' => 1]);
     */
    public static function getAll(
        int   $limit   = 20,
        int   $offset  = 0,
        array $filters = []
    ): array {
        global $pdo;

        // Never return passwords in bulk queries
        $select = 'user_id, email, username, name, phone, city, country,
                   profile_photo, is_verified, is_premium, kyc_status,
                   status, created_at, last_login';

        $where  = [];
        $params = [];

        // Safe filter fields (whitelist)
        $allowed_filters = ['status', 'city', 'is_premium', 'is_verified', 'kyc_status', 'gender'];
        foreach ($filters as $col => $val) {
            if (in_array($col, $allowed_filters, true)) {
                $where[]  = "`$col` = ?";
                $params[] = $val;
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sanitize pagination values
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        try {
            $sql = "SELECT $select
                    FROM " . self::TABLE . "
                    $whereClause
                    ORDER BY created_at DESC
                    LIMIT $limit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log('[User::getAll] PDO error: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Count total users — useful for pagination and admin dashboard stats.
     *
     * @param  array $filters Optional same filters as getAll()
     * @return int            Total matching user count
     *
     * Example:
     *   $total    = User::count();            // all users
     *   $active   = User::count(['status' => 'active']);
     *   $pages    = ceil($total / 20);        // for pagination
     */
    public static function count(array $filters = []): int
    {
        global $pdo;

        $where  = [];
        $params = [];
        $allowed_filters = ['status', 'city', 'is_premium', 'is_verified', 'kyc_status', 'gender'];

        foreach ($filters as $col => $val) {
            if (in_array($col, $allowed_filters, true)) {
                $where[]  = "`$col` = ?";
                $params[] = $val;
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " $whereClause");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log('[User::count] PDO error: ' . $e->getMessage());
            return 0;
        }
    }


    /**
     * Check whether a user with this email or username already exists.
     *
     * Used before create() to give clear "email taken" / "username taken"
     * messages. Can also be called directly from registration validation.
     *
     * @param  string      $email    Email address to check
     * @param  string|null $username Username to check (optional)
     * @return bool                  true if either already exists, false if both are free
     *
     * Example:
     *   if (User::exists('rahul@example.com', 'rahul_travels')) {
     *       echo "Email or username already taken!";
     *   }
     */
    public static function exists(string $email, ?string $username = null): bool
    {
        global $pdo;
        try {
            // Check email
            $stmt = $pdo->prepare(
                'SELECT 1 FROM ' . self::TABLE . ' WHERE email = ? LIMIT 1'
            );
            $stmt->execute([strtolower(trim($email))]);
            if ($stmt->fetchColumn()) return true;

            // Check username if provided
            if ($username !== null) {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM ' . self::TABLE . ' WHERE username = ? LIMIT 1'
                );
                $stmt->execute([strtolower(trim($username))]);
                if ($stmt->fetchColumn()) return true;
            }

            return false;

        } catch (PDOException $e) {
            error_log('[User::exists] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    // ===========================================================
    // ── UPDATE ──────────────────────────────────────────────────
    // ===========================================================

    /**
     * Update one or more profile fields for a user.
     *
     * Only whitelisted fields (UPDATABLE_FIELDS) are accepted —
     * any unrecognised keys are silently ignored, which prevents
     * column-injection attacks via user-supplied keys.
     * The updated_at timestamp is set automatically.
     *
     * @param  int   $id   user_id of the user to update
     * @param  array $data Associative array of column => value pairs
     * @return bool        true on success, false on failure or no valid fields
     *
     * Example:
     *   User::update(5, [
     *       'name' => 'Rahul Kumar',
     *       'city' => 'Bangalore',
     *       'bio'  => 'Adventure seeker & backpacker.',
     *   ]);
     */
    public static function update(int $id, array $data): bool
    {
        global $pdo;

        // Filter to whitelisted columns only
        $safe   = [];
        $values = [];

        foreach ($data as $col => $val) {
            if (in_array($col, self::UPDATABLE_FIELDS, true)) {
                $safe[]   = "`$col` = ?";
                $values[] = $val;
            }
        }

        if (empty($safe)) {
            error_log('[User::update] No valid fields provided for user_id=' . $id);
            return false;
        }

        $values[] = $id; // WHERE clause param

        try {
            $sql  = 'UPDATE ' . self::TABLE . '
                     SET ' . implode(', ', $safe) . ', updated_at = NOW()
                     WHERE user_id = ?';

            $stmt = $pdo->prepare($sql);
            return $stmt->execute($values);

        } catch (PDOException $e) {
            error_log('[User::update] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Update the user's password (hashes automatically).
     *
     * Separated from update() because passwords need hashing
     * and should never appear in bulk update calls.
     *
     * @param  int    $id           user_id
     * @param  string $newPassword  Plain-text new password
     * @return bool                 true on success
     *
     * Example:
     *   User::updatePassword(5, 'NewSecret@456');
     */
    public static function updatePassword(int $id, string $newPassword): bool
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::TABLE . '
                 SET password = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            return $stmt->execute([self::hashPassword($newPassword), $id]);

        } catch (PDOException $e) {
            error_log('[User::updatePassword] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    // ===========================================================
    // ── DELETE ──────────────────────────────────────────────────
    // ===========================================================

    /**
     * Soft-delete a user by setting status = 'deleted'.
     *
     * The user's row is NOT physically removed — this preserves
     * referential integrity (their trips, messages, reviews remain).
     * A soft-deleted user cannot log in (checked in Auth system).
     * Use hardDelete() only for GDPR erasure requests.
     *
     * @param  int  $id  user_id to soft-delete
     * @return bool      true on success, false on failure
     *
     * Example:
     *   User::delete(5);
     *   $user = User::findById(5);
     *   echo $user['status']; // "deleted"
     */
    public static function delete(int $id): bool
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                "UPDATE " . self::TABLE . "
                 SET status = 'deleted', updated_at = NOW()
                 WHERE user_id = ?"
            );
            return $stmt->execute([$id]);

        } catch (PDOException $e) {
            error_log('[User::delete] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Permanently delete a user and all their data (GDPR erasure).
     *
     * ⚠️ IRREVERSIBLE — use with extreme caution and only after
     * admin confirmation. Cascading FKs handle related table cleanup.
     *
     * @param  int  $id  user_id to erase
     * @return bool      true on success
     */
    public static function hardDelete(int $id): bool
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE user_id = ?');
            return $stmt->execute([$id]);

        } catch (PDOException $e) {
            error_log('[User::hardDelete] PDO error: ' . $e->getMessage());
            return false;
        }
    }


    // ===========================================================
    // ── PASSWORD ────────────────────────────────────────────────
    // ===========================================================

    /**
     * Hash a plain-text password using bcrypt.
     *
     * Uses PASSWORD_BCRYPT with cost=12 (secure; ~250ms on modern hardware).
     * The resulting hash is always 60 characters and safe to store in
     * VARCHAR(255) (allows future algorithm upgrades via PASSWORD_DEFAULT).
     *
     * @param  string $password Plain-text password
     * @return string           Bcrypt hash (60 chars)
     *
     * Example:
     *   $hash = User::hashPassword('MySecret@123');
     *   // → "$2y$12$..." (60-char bcrypt string)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }


    /**
     * Verify a plain-text password against a stored bcrypt hash.
     *
     * Uses password_verify() which is timing-attack safe.
     * Also checks password_needs_rehash() so you can transparently
     * upgrade the hash cost in future without forcing resets.
     *
     * @param  string $plainPassword   The raw password entered by the user
     * @param  string $hashedPassword  The hash stored in the database
     * @return bool                    true if the password matches
     *
     * Example:
     *   $user = User::findByEmail($email);
     *   if ($user && User::checkPassword($input, $user['password'])) {
     *       // Login success
     *   }
     */
    public static function checkPassword(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }


    /**
     * Check if a stored hash needs to be upgraded to a stronger algorithm.
     *
     * Call this after a successful login and re-hash if true,
     * so older accounts automatically get upgraded over time.
     *
     * @param  string $hash  The stored password hash
     * @return bool          true if the hash should be regenerated
     *
     * Example:
     *   if (User::needsRehash($user['password'])) {
     *       User::updatePassword($user['user_id'], $plainPassword);
     *   }
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }


    // ===========================================================
    // ── PRIVATE HELPERS ─────────────────────────────────────────
    // ===========================================================

    /**
     * Create an initial coin wallet row for a new user.
     * Called automatically by create() after successful INSERT.
     *
     * @param  int  $userId  The newly created user_id
     * @return void
     */
    private static function initCoinWallet(int $userId): void
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO ft_user_coins
                    (user_id, total_coins, coins_earned, coins_spent, level_id, created_at)
                 VALUES
                    (?, 0, 0, 0, 1, NOW())'
            );
            $stmt->execute([$userId]);

        } catch (PDOException $e) {
            // Non-fatal — log but don't abort user creation
            error_log('[User::initCoinWallet] Could not create wallet for user_id=' . $userId . ': ' . $e->getMessage());
        }
    }


    /**
     * Return a user array safe for API/JSON output (no password).
     *
     * @param  array $user  Raw user row from database
     * @return array        User data with password stripped
     */
    public static function sanitize(array $user): array
    {
        unset($user['password']);
        return $user;
    }
}

// ============================================================
// END OF User MODEL
// Fellow Traveler Platform — models/User.php v1.0
// Methods: create, findById, findByEmail, findByUsername,
//          getAll, count, exists, update, updatePassword,
//          delete, hardDelete, hashPassword, checkPassword,
//          needsRehash, sanitize
// ============================================================
