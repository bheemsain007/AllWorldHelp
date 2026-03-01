<?php
/**
 * ProfileHelper Class
 * Fellow Traveler — Task T11
 *
 * Static helper methods for user profile display:
 *   - getProfileStats($userId)        → array  (trips, connections, reviews, coins)
 *   - getVerificationBadge($user)     → string (HTML badge or '')
 *   - getPremiumBadge($user)          → string (HTML badge or '')
 *   - getCoinLevelBadge($userId)      → string (HTML badge)
 *   - canViewProfile($viewerId, $profileId) → bool
 */
class ProfileHelper
{
    // ── 1. getProfileStats() ─────────────────────────────────────────────────
    /**
     * Returns aggregate statistics for a user's profile.
     *
     * @param  int   $userId
     * @return array ['trips', 'connections', 'reviews', 'coins']
     */
    public static function getProfileStats(int $userId): array
    {
        global $pdo;

        try {
            // Trips created by user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ft_trips WHERE user_id = ?");
            $stmt->execute([$userId]);
            $trips = (int) $stmt->fetchColumn();

            // Accepted connections (user appears on either side)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM ft_connections
                 WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'"
            );
            $stmt->execute([$userId, $userId]);
            $connections = (int) $stmt->fetchColumn();

            // Reviews received by this user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ft_reviews WHERE reviewed_user_id = ?");
            $stmt->execute([$userId]);
            $reviews = (int) $stmt->fetchColumn();

            // Coin balance
            $stmt = $pdo->prepare("SELECT balance FROM ft_user_coins WHERE user_id = ?");
            $stmt->execute([$userId]);
            $coins = (int) ($stmt->fetchColumn() ?: 0);

            return compact('trips', 'connections', 'reviews', 'coins');

        } catch (PDOException $e) {
            error_log("ProfileHelper::getProfileStats error: " . $e->getMessage());
            return ['trips' => 0, 'connections' => 0, 'reviews' => 0, 'coins' => 0];
        }
    }

    // ── 2. getVerificationBadge() ────────────────────────────────────────────
    /**
     * Returns an HTML badge if the user is verified, otherwise empty string.
     *
     * @param  array  $user  User row from DB (must contain 'is_verified')
     * @return string        HTML <span> badge or ''
     */
    public static function getVerificationBadge(array $user): string
    {
        if (!empty($user['is_verified'])) {
            return '<span class="badge badge-verified">✓ Verified</span>';
        }
        return '';
    }

    // ── 3. getPremiumBadge() ─────────────────────────────────────────────────
    /**
     * Returns an HTML badge if the user is premium, otherwise empty string.
     *
     * @param  array  $user  User row from DB (must contain 'is_premium')
     * @return string        HTML <span> badge or ''
     */
    public static function getPremiumBadge(array $user): string
    {
        if (!empty($user['is_premium'])) {
            return '<span class="badge badge-premium">★ Premium</span>';
        }
        return '';
    }

    // ── 4. getCoinLevelBadge() ───────────────────────────────────────────────
    /**
     * Returns an HTML badge showing the user's coin level (Bronze/Silver/Gold/Platinum).
     * Falls back to Bronze if no matching level found.
     *
     * @param  int    $userId
     * @return string HTML <span> badge
     */
    public static function getCoinLevelBadge(int $userId): string
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT cl.level_name
                FROM ft_user_coins uc
                JOIN ft_coin_levels cl ON uc.balance >= cl.min_coins
                WHERE uc.user_id = ?
                ORDER BY cl.min_coins DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $level = $stmt->fetchColumn();

            if ($level) {
                $class = 'badge-' . strtolower($level);
                return '<span class="badge ' . htmlspecialchars($class) . '">'
                     . htmlspecialchars($level) . '</span>';
            }

            return '<span class="badge badge-bronze">Bronze</span>';

        } catch (PDOException $e) {
            error_log("ProfileHelper::getCoinLevelBadge error: " . $e->getMessage());
            return '<span class="badge badge-bronze">Bronze</span>';
        }
    }

    // ── 5. canViewProfile() ──────────────────────────────────────────────────
    /**
     * Check whether $viewerId is allowed to view $profileId's profile.
     * Currently all profiles are public; privacy settings will be added later.
     *
     * @param  int|null $viewerId   ID of the logged-in user (null = guest)
     * @param  int      $profileId  ID of the profile being viewed
     * @return bool
     */
    public static function canViewProfile(?int $viewerId, int $profileId): bool
    {
        // Owner can always see their own profile
        if ($viewerId === $profileId) {
            return true;
        }

        // TODO (T36+): check ft_users.privacy_level when privacy settings are added
        // For now all profiles are public
        return true;
    }
}
?>
