-- =============================================================================
-- Fellow Traveler Platform - Seed Data
-- Task T2: Initial Database Population
-- Version: 1.0
-- Date: February 2026
-- Total: 63 rows across 4 tables
-- =============================================================================
-- Tables populated:
--   1. ft_coin_plans      → 27 rows (coin earning action definitions)
--   2. ft_coin_rewards    → 10 rows (coin redemption reward catalog)
--   3. ft_admin_settings  → 22 rows (platform configuration)
--   4. ft_coin_levels     →  4 rows (user tier definitions)
-- =============================================================================
-- IMPORTANT: Run AFTER Task T1 schema is fully executed (25 tables must exist).
-- If tables already have data (e.g., from T1 pre-seed), uncomment the TRUNCATE
-- section below to reset before inserting. Otherwise INSERT IGNORE is safe.
-- =============================================================================

USE fellow_traveler;

-- =============================================================================
-- OPTIONAL: TRUNCATE (uncomment ONLY if you want to reset existing data)
-- =============================================================================
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE ft_coin_plans;
-- TRUNCATE TABLE ft_coin_rewards;
-- TRUNCATE TABLE ft_admin_settings;
-- TRUNCATE TABLE ft_coin_levels;
-- SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SECTION 1: ft_coin_levels (4 rows)
-- Insert first — ft_user_coins references this table (FK dependency)
-- =============================================================================

INSERT IGNORE INTO ft_coin_levels
    (level_name, level_code, min_coins, max_coins, color_hex, perks)
VALUES
    -- Level 1: Bronze (default, 0 - 499 coins)
    (
        'Bronze Traveler',
        'bronze',
        0,
        499,
        '#CD7F32',
        '["Access to all basic features", "Standard support", "0% coin bonus"]'
    ),
    -- Level 2: Silver (500 - 1999 coins)
    (
        'Silver Explorer',
        'silver',
        500,
        1999,
        '#C0C0C0',
        '["Priority support", "10% coin bonus on all actions", "Silver badge on profile", "Featured in traveler search"]'
    ),
    -- Level 3: Gold (2000 - 4999 coins)
    (
        'Gold Adventurer',
        'gold',
        2000,
        4999,
        '#FFD700',
        '["VIP support", "20% coin bonus on all actions", "Gold badge on profile", "Early access to new features", "Trip discount 5%"]'
    ),
    -- Level 4: Platinum (5000+ coins, no upper limit)
    (
        'Platinum Nomad',
        'platinum',
        5000,
        NULL,
        '#E5E4E2',
        '["Dedicated 24/7 support", "35% coin bonus on all actions", "Platinum badge on profile", "Beta features access", "Exclusive events invites", "Trip discount 10%"]'
    );


-- =============================================================================
-- SECTION 2: ft_coin_plans (27 rows)
-- Defines every action a user can perform to earn coins
-- =============================================================================

INSERT IGNORE INTO ft_coin_plans
    (action_name, action_key, coins_awarded, description, max_per_day, max_per_month, max_lifetime, category, is_active)
VALUES

    -- ── ONBOARDING ACTIONS ──────────────────────────────────────────────────

    -- 1. Profile Completion
    (
        'Complete Profile',
        'profile_complete',
        100,
        'Complete full profile with all details (name, bio, city, photo)',
        1,
        NULL,
        1,
        'onboarding',
        1
    ),

    -- 2. Email Verification
    (
        'Verify Email',
        'email_verify',
        25,
        'Verify email address via confirmation link',
        1,
        NULL,
        1,
        'onboarding',
        1
    ),

    -- 3. Phone Verification
    (
        'Verify Phone',
        'phone_verify',
        25,
        'Verify phone number via OTP',
        1,
        NULL,
        1,
        'onboarding',
        1
    ),

    -- 4. KYC Verification
    (
        'KYC Verified',
        'kyc_verified',
        150,
        'KYC identity documents submitted and approved by admin',
        1,
        NULL,
        1,
        'onboarding',
        1
    ),

    -- ── DAILY ACTIONS ───────────────────────────────────────────────────────

    -- 5. Daily Login
    (
        'Daily Login',
        'daily_login',
        10,
        'Login to the platform once per day',
        1,
        30,
        NULL,
        'daily',
        1
    ),

    -- 6. Daily Check-in Streak
    (
        'Daily Check-in',
        'daily_checkin',
        15,
        'Check-in daily streak bonus (consecutive day reward)',
        1,
        30,
        NULL,
        'daily',
        1
    ),

    -- 7. App Open
    (
        'App Open',
        'app_open',
        5,
        'Open and actively use the app daily',
        1,
        30,
        NULL,
        'daily',
        1
    ),

    -- ── TRIP ACTIONS ─────────────────────────────────────────────────────────

    -- 8. Create a Trip
    (
        'Create Trip',
        'trip_create',
        80,
        'Create and publish a new trip listing',
        5,
        NULL,
        NULL,
        'trips',
        1
    ),

    -- 9. Join a Trip
    (
        'Join Trip',
        'trip_join',
        50,
        'Join another user\'s trip as a member',
        10,
        NULL,
        NULL,
        'trips',
        1
    ),

    -- 10. Complete a Trip
    (
        'Complete Trip',
        'trip_complete',
        200,
        'Successfully complete a trip and mark it as done',
        5,
        NULL,
        NULL,
        'trips',
        1
    ),

    -- 11. Bookmark a Trip
    (
        'Bookmark Trip',
        'trip_bookmark',
        3,
        'Save a trip to your bookmarks / wishlist',
        20,
        NULL,
        NULL,
        'trips',
        1
    ),

    -- ── SOCIAL ACTIONS ───────────────────────────────────────────────────────

    -- 12. Send Connection Request
    (
        'Send Connection',
        'connection_send',
        5,
        'Send a connection request to another traveler',
        10,
        NULL,
        NULL,
        'social',
        1
    ),

    -- 13. Accept Connection Request
    (
        'Accept Connection',
        'connection_accept',
        10,
        'Accept an incoming connection request',
        20,
        NULL,
        NULL,
        'social',
        1
    ),

    -- 14. Follow a User
    (
        'Follow User',
        'follow_user',
        3,
        'Follow another traveler\'s profile',
        20,
        NULL,
        NULL,
        'social',
        1
    ),

    -- 15. Send a Message
    (
        'Send Message',
        'message_send',
        2,
        'Send a private message to a connected user',
        50,
        NULL,
        NULL,
        'social',
        1
    ),

    -- ── CONTENT ACTIONS ─────────────────────────────────────────────────────

    -- 16. Write a Review
    (
        'Write Review',
        'review_write',
        40,
        'Write a detailed review for a completed trip',
        5,
        NULL,
        NULL,
        'content',
        1
    ),

    -- 17. Write a Story
    (
        'Write Story',
        'story_write',
        60,
        'Publish a travel story / blog post',
        3,
        NULL,
        NULL,
        'content',
        1
    ),

    -- 18. Like a Story
    (
        'Like Story',
        'story_like',
        2,
        'Like another user\'s travel story',
        30,
        NULL,
        NULL,
        'content',
        1
    ),

    -- 19. Like a Trip
    (
        'Like Trip',
        'trip_like',
        2,
        'Like / express interest in a trip listing',
        40,
        NULL,
        NULL,
        'content',
        1
    ),

    -- ── ENGAGEMENT ACTIONS ───────────────────────────────────────────────────

    -- 20. View a Profile
    (
        'View Profile',
        'profile_view',
        1,
        'View another traveler\'s profile page',
        50,
        NULL,
        NULL,
        'engagement',
        1
    ),

    -- 21. View a Trip
    (
        'View Trip',
        'trip_view',
        1,
        'View full trip details page',
        100,
        NULL,
        NULL,
        'engagement',
        1
    ),

    -- 22. Use Search Feature
    (
        'Search',
        'search_perform',
        1,
        'Use the search feature to find trips or travelers',
        20,
        NULL,
        NULL,
        'engagement',
        1
    ),

    -- ── SHARING ACTIONS ──────────────────────────────────────────────────────

    -- 23. Share a Trip
    (
        'Share Trip',
        'trip_share',
        15,
        'Share a trip listing on social media or via link',
        10,
        NULL,
        NULL,
        'social',
        1
    ),

    -- 24. Share the App
    (
        'Share App',
        'app_share',
        20,
        'Share the Fellow Traveler app with friends',
        5,
        30,
        NULL,
        'social',
        1
    ),

    -- ── REFERRAL ACTIONS ─────────────────────────────────────────────────────

    -- 25. Referral Signup
    (
        'Referral Signup',
        'referral_signup',
        100,
        'A friend signs up using your referral code',
        999,
        NULL,
        NULL,
        'referral',
        1
    ),

    -- 26. Referral Verified
    (
        'Referral KYC Verified',
        'referral_verified',
        200,
        'A friend you referred completes their KYC verification',
        999,
        NULL,
        NULL,
        'referral',
        1
    ),

    -- ── PREMIUM ACTION ───────────────────────────────────────────────────────

    -- 27. Premium Purchase
    (
        'Premium Purchase',
        'premium_purchase',
        500,
        'Purchase a premium subscription plan',
        1,
        NULL,
        NULL,
        'premium',
        1
    );


-- =============================================================================
-- SECTION 3: ft_coin_rewards (10 rows)
-- Defines what users can redeem their coins for
-- =============================================================================

INSERT IGNORE INTO ft_coin_rewards
    (reward_name, reward_description, coins_required, reward_type, reward_value, validity_days, stock, is_active, sort_order)
VALUES

    -- 1. Premium Membership - 7 Days
    (
        'Premium 7 Days',
        'Enjoy 7 days of Premium membership — ad-free experience, unlimited trip applications, priority listing',
        300,
        'premium_days',
        '{"days": 7}',
        7,
        NULL,
        1,
        1
    ),

    -- 2. Premium Membership - 30 Days
    (
        'Premium 30 Days',
        'Full 30-day Premium membership — all premium features including profile boost and advanced filters',
        999,
        'premium_days',
        '{"days": 30}',
        30,
        NULL,
        1,
        2
    ),

    -- 3. Trip Boost - 3 Days
    (
        'Trip Boost',
        'Boost your trip to the top of search results for 3 days — get more visibility and members faster',
        200,
        'feature',
        '{"type": "trip_top", "days": 3}',
        3,
        NULL,
        1,
        3
    ),

    -- 4. Spotlight Trip - 7 Days
    (
        'Spotlight Trip',
        'Feature your trip on the homepage spotlight section for 7 days — maximum visibility',
        500,
        'feature',
        '{"type": "trip_spotlight", "days": 7}',
        7,
        NULL,
        1,
        4
    ),

    -- 5. Fast KYC Review
    (
        'Fast KYC',
        'Priority KYC review — your documents reviewed within 2 hours instead of 2-3 business days',
        400,
        'feature',
        '{"type": "kyc_priority"}',
        NULL,
        NULL,
        1,
        5
    ),

    -- 6. Profile Boost - 3 Days
    (
        'Profile Boost',
        'Highlight your profile in traveler search results for 3 days — stand out and get more connections',
        150,
        'feature',
        '{"type": "profile_boost", "days": 3}',
        3,
        NULL,
        1,
        6
    ),

    -- 7. Extra Connection Requests - 30 Days
    (
        'Extra Connections',
        'Send 50 additional connection requests over the next 30 days — beyond your normal daily limit',
        100,
        'feature',
        '{"type": "extra_connections", "count": 50}',
        30,
        NULL,
        1,
        7
    ),

    -- 8. Gift Card ₹500
    (
        'Gift Card ₹500',
        '₹500 gift card redeemable on Amazon or Flipkart — delivered to your registered email within 24 hours',
        2000,
        'cashback',
        '{"currency": "INR", "value": 500, "platforms": ["Amazon", "Flipkart"]}',
        365,
        50,
        1,
        8
    ),

    -- 9. Gift Card ₹1000 (Travel Voucher)
    (
        'Gift Card ₹1000',
        '₹1000 travel booking voucher — redeem on MakeMyTrip or Goibibo for flight or hotel bookings',
        3500,
        'cashback',
        '{"currency": "INR", "value": 1000, "platforms": ["MakeMyTrip", "Goibibo"]}',
        365,
        25,
        1,
        9
    ),

    -- 10. Verified Badge (Lifetime)
    (
        'Verified Badge',
        'Earn a permanent blue verification badge on your profile — builds trust with fellow travelers. Lifetime validity',
        1000,
        'feature',
        '{"type": "verified_badge", "permanent": true}',
        NULL,
        NULL,
        1,
        10
    );


-- =============================================================================
-- SECTION 4: ft_admin_settings (22 rows)
-- Core platform configuration — editable via admin panel after launch
-- =============================================================================

INSERT INTO ft_admin_settings
    (setting_key, setting_value, setting_type, category, description, is_public)
VALUES
    -- ── GENERAL SETTINGS ─────────────────────────────────────────────────────
    ('site_name',               'Fellow Traveler',            'string',  'general',  'Platform display name shown in header and emails',         1),
    ('site_url',                'https://fellowtraveler.com', 'string',  'general',  'Main website URL (no trailing slash)',                      1),
    ('site_email',              'info@fellowtraveler.com',    'string',  'general',  'Public contact email for support queries',                  1),
    ('site_tagline',            'Find Your Travel Buddy',     'string',  'general',  'Homepage hero tagline / marketing slogan',                  1),

    -- ── FILE UPLOAD SETTINGS ─────────────────────────────────────────────────
    ('max_upload_size',         '5242880',                    'integer', 'general',  'Maximum file upload size in bytes (default: 5MB = 5242880)', 0),
    ('allowed_image_types',     'jpg,jpeg,png,webp',          'string',  'general',  'Comma-separated allowed image file extensions',              0),

    -- ── SECURITY SETTINGS ────────────────────────────────────────────────────
    ('session_lifetime',        '86400',                      'integer', 'security', 'Session expiry in seconds (default: 86400 = 24 hours)',     0),
    ('login_max_attempts',      '5',                          'integer', 'security', 'Maximum failed login attempts before account lockout',      0),
    ('lockout_minutes',         '15',                         'integer', 'security', 'Account lockout duration in minutes after max attempts',    0),
    ('password_min_length',     '6',                          'integer', 'security', 'Minimum password character length (recommended: 8+)',       1),

    -- ── KYC SETTINGS ─────────────────────────────────────────────────────────
    ('kyc_required',            '0',                          'boolean', 'general',  '1 = KYC mandatory to use platform, 0 = optional',           0),
    ('kyc_auto_approve',        '0',                          'boolean', 'general',  '1 = auto-approve KYC submissions, 0 = manual review',       0),

    -- ── TRIP SETTINGS ────────────────────────────────────────────────────────
    ('free_trips_enabled',      '1',                          'boolean', 'general',  '1 = allow free trip listings, 0 = paid trips only',         1),
    ('featured_trips_count',    '6',                          'integer', 'general',  'Number of featured trips shown on homepage',                 1),
    ('trip_approval_required',  '0',                          'boolean', 'general',  '1 = admin must approve trips before listing, 0 = instant',  0),

    -- ── DISPLAY / MARKETING STATS ────────────────────────────────────────────
    ('total_members',           '150',                        'integer', 'general',  'Member count shown on homepage (can be manually updated)',   1),
    ('total_trips',             '50',                         'integer', 'general',  'Trip count shown on homepage (can be manually updated)',     1),
    ('total_countries',         '1',                          'integer', 'general',  'Countries count shown on homepage stats',                    1),
    ('happy_travelers',         '100',                        'integer', 'general',  'Happy travelers count shown on homepage / about page',       1),

    -- ── EMAIL SETTINGS ───────────────────────────────────────────────────────
    ('smtp_enabled',            '1',                          'boolean', 'email',    '1 = email sending active via SMTP, 0 = disabled',            0),

    -- ── COIN SYSTEM SETTINGS ─────────────────────────────────────────────────
    ('coins_enabled',           '1',                          'boolean', 'coins',    '1 = coin system active, 0 = disable all coin earning',       0),
    ('referral_bonus',          '100',                        'integer', 'coins',    'Bonus coins awarded to referrer when friend signs up',       1)

ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description   = VALUES(description);


-- =============================================================================
-- VERIFICATION QUERIES
-- Run these after execution to confirm all rows inserted correctly
-- =============================================================================

-- Total row counts (should match: 4, 27, 10, 22)
-- SELECT 'ft_coin_levels'     AS table_name, COUNT(*) AS row_count FROM ft_coin_levels
-- UNION ALL
-- SELECT 'ft_coin_plans',     COUNT(*) FROM ft_coin_plans
-- UNION ALL
-- SELECT 'ft_coin_rewards',   COUNT(*) FROM ft_coin_rewards
-- UNION ALL
-- SELECT 'ft_admin_settings', COUNT(*) FROM ft_admin_settings;

-- Sample: Check first 5 coin plans
-- SELECT plan_id, action_key, coins_awarded, max_per_day FROM ft_coin_plans LIMIT 5;

-- Sample: Check all coin levels
-- SELECT level_id, level_name, min_coins, max_coins FROM ft_coin_levels;

-- Sample: Check admin site name
-- SELECT setting_value FROM ft_admin_settings WHERE setting_key = 'site_name';

-- Sample: Verify referral bonus setting
-- SELECT setting_key, setting_value FROM ft_admin_settings WHERE setting_key = 'referral_bonus';

-- =============================================================================
-- END OF SEED DATA
-- Total inserted: 63 rows
--   ft_coin_levels     → 4 rows
--   ft_coin_plans      → 27 rows
--   ft_coin_rewards    → 10 rows
--   ft_admin_settings  → 22 rows
-- =============================================================================
