-- =============================================================================
-- Fellow Traveler Platform - Database Schema
-- Task T1: Database Schema Creation
-- Version: 1.0
-- Date: February 2026
-- Description: Complete schema with 25 tables for Fellow Traveler platform
-- =============================================================================

-- Create and select database
CREATE DATABASE IF NOT EXISTS fellow_traveler
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE fellow_traveler;

-- Disable foreign key checks during creation
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- SECTION 1: CORE USER TABLES (4 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_users
-- Purpose: Main user accounts and profiles
-- Rows Expected: ~10,000 for 10K users
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_users (
    user_id          INT             NOT NULL AUTO_INCREMENT,
    email            VARCHAR(255)    NOT NULL,
    username         VARCHAR(50)     NOT NULL,
    password         VARCHAR(255)    NOT NULL,
    name             VARCHAR(100)    DEFAULT NULL,
    phone            VARCHAR(20)     DEFAULT NULL,
    date_of_birth    DATE            DEFAULT NULL,
    gender           VARCHAR(10)     DEFAULT NULL,
    city             VARCHAR(100)    DEFAULT NULL,
    country          VARCHAR(100)    DEFAULT 'India',
    profile_photo    VARCHAR(255)    DEFAULT NULL,
    bio              TEXT            DEFAULT NULL,
    referral_code    VARCHAR(20)     DEFAULT NULL,
    is_verified      TINYINT(1)      DEFAULT 0,
    is_premium       TINYINT(1)      DEFAULT 0,
    email_verified   TINYINT(1)      DEFAULT 0,
    phone_verified   TINYINT(1)      DEFAULT 0,
    kyc_status       VARCHAR(20)     DEFAULT 'pending',
    status           VARCHAR(20)     DEFAULT 'active',
    last_login       TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_email    (email),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_phone    (phone),
    INDEX idx_city         (city),
    INDEX idx_status       (status),
    INDEX idx_referral     (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_sessions
-- Purpose: Active login sessions tracking
-- Rows Expected: ~3,000-5,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_sessions (
    session_id       INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    session_token    VARCHAR(255)    NOT NULL,
    ip_address       VARCHAR(45)     DEFAULT NULL,
    user_agent       TEXT            DEFAULT NULL,
    device_type      VARCHAR(50)     DEFAULT NULL,
    is_active        TINYINT(1)      DEFAULT 1,
    last_activity    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at       TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    UNIQUE KEY uq_token       (session_token),
    INDEX idx_user_id         (user_id),
    INDEX idx_is_active       (is_active),
    INDEX idx_last_activity   (last_activity),
    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_kyc_documents
-- Purpose: Identity verification documents
-- Rows Expected: ~3,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_kyc_documents (
    kyc_id           INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    document_type    VARCHAR(50)     NOT NULL COMMENT 'aadhar/passport/driving_license/voter_id',
    document_number  VARCHAR(100)    DEFAULT NULL,
    document_front   VARCHAR(255)    DEFAULT NULL,
    document_back    VARCHAR(255)    DEFAULT NULL,
    selfie_photo     VARCHAR(255)    DEFAULT NULL,
    status           VARCHAR(20)     DEFAULT 'pending' COMMENT 'pending/under_review/approved/rejected',
    rejection_reason VARCHAR(255)    DEFAULT NULL,
    admin_notes      TEXT            DEFAULT NULL,
    verified_by      INT             DEFAULT NULL COMMENT 'Admin user_id who verified',
    submitted_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    verified_at      TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kyc_id),
    INDEX idx_user_id  (user_id),
    INDEX idx_status   (status),
    CONSTRAINT fk_kyc_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_activity_logs
-- Purpose: User action audit trail
-- Rows Expected: ~500,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_activity_logs (
    log_id           BIGINT          NOT NULL AUTO_INCREMENT,
    user_id          INT             DEFAULT NULL,
    action           VARCHAR(100)    NOT NULL,
    module           VARCHAR(50)     DEFAULT NULL COMMENT 'auth/trips/profile/admin/etc',
    description      TEXT            DEFAULT NULL,
    ip_address       VARCHAR(45)     DEFAULT NULL,
    user_agent       TEXT            DEFAULT NULL,
    reference_id     INT             DEFAULT NULL,
    reference_type   VARCHAR(50)     DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    INDEX idx_user_id     (user_id),
    INDEX idx_action      (action),
    INDEX idx_module      (module),
    INDEX idx_created_at  (created_at),
    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 2: TRIP TABLES (2 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_trips
-- Purpose: Trip listings with all details
-- Rows Expected: ~1,500
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_trips (
    trip_id            INT             NOT NULL AUTO_INCREMENT,
    user_id            INT             NOT NULL,
    title              VARCHAR(200)    NOT NULL,
    destination        VARCHAR(200)    NOT NULL,
    start_date         DATE            DEFAULT NULL,
    end_date           DATE            DEFAULT NULL,
    duration_days      INT             DEFAULT NULL,
    budget_min         INT             DEFAULT NULL,
    budget_max         INT             DEFAULT NULL,
    currency           VARCHAR(10)     DEFAULT 'INR',
    description        TEXT            DEFAULT NULL,
    requirements       TEXT            DEFAULT NULL,
    trip_type          VARCHAR(50)     DEFAULT NULL COMMENT 'adventure/leisure/backpacking/cultural/etc',
    members_needed     INT             DEFAULT 1,
    members_joined     INT             DEFAULT 0,
    gender_preference  VARCHAR(20)     DEFAULT 'any' COMMENT 'male/female/any',
    age_min            INT             DEFAULT NULL,
    age_max            INT             DEFAULT NULL,
    cover_image        VARCHAR(255)    DEFAULT NULL,
    is_free            TINYINT(1)      DEFAULT 1,
    is_featured        TINYINT(1)      DEFAULT 0,
    is_private         TINYINT(1)      DEFAULT 0,
    status             VARCHAR(20)     DEFAULT 'open' COMMENT 'open/ongoing/completed/cancelled',
    views              INT             DEFAULT 0,
    created_at         TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (trip_id),
    INDEX idx_user_id      (user_id),
    INDEX idx_destination  (destination),
    INDEX idx_status       (status),
    INDEX idx_start_date   (start_date),
    INDEX idx_trip_type    (trip_type),
    INDEX idx_is_featured  (is_featured),
    INDEX idx_created_at   (created_at),
    CONSTRAINT fk_trips_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_bookmarks
-- Purpose: Saved/bookmarked trips by users
-- Rows Expected: ~5,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_bookmarks (
    bookmark_id      INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    trip_id          INT             NOT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bookmark_id),
    UNIQUE KEY uq_user_trip  (user_id, trip_id),
    INDEX idx_user_id        (user_id),
    INDEX idx_trip_id        (trip_id),
    CONSTRAINT fk_bookmarks_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookmarks_trip
        FOREIGN KEY (trip_id) REFERENCES ft_trips (trip_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 3: SOCIAL TABLES (5 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_connections
-- Purpose: Friend/connection requests (M:M)
-- Rows Expected: ~10,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_connections (
    connection_id    INT             NOT NULL AUTO_INCREMENT,
    sender_id        INT             NOT NULL,
    receiver_id      INT             NOT NULL,
    status           VARCHAR(20)     DEFAULT 'pending' COMMENT 'pending/accepted/rejected/withdrawn',
    message          VARCHAR(255)    DEFAULT NULL COMMENT 'Optional connection request message',
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (connection_id),
    UNIQUE KEY uq_connection     (sender_id, receiver_id),
    INDEX idx_sender_id          (sender_id),
    INDEX idx_receiver_id        (receiver_id),
    INDEX idx_status             (status),
    CONSTRAINT fk_connections_sender
        FOREIGN KEY (sender_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_connections_receiver
        FOREIGN KEY (receiver_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_followers
-- Purpose: Follow/unfollow system
-- Rows Expected: ~20,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_followers (
    follower_id          INT         NOT NULL AUTO_INCREMENT,
    user_id              INT         NOT NULL COMMENT 'User being followed',
    follower_user_id     INT         NOT NULL COMMENT 'User who follows',
    created_at           TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id),
    UNIQUE KEY uq_follow         (user_id, follower_user_id),
    INDEX idx_user_id            (user_id),
    INDEX idx_follower_user_id   (follower_user_id),
    CONSTRAINT fk_followers_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_followers_follower
        FOREIGN KEY (follower_user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_messages
-- Purpose: Private chat messages between users
-- Rows Expected: ~10,000 (messages), more with chat history
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_messages (
    message_id       INT             NOT NULL AUTO_INCREMENT,
    sender_id        INT             NOT NULL,
    receiver_id      INT             NOT NULL,
    trip_id          INT             DEFAULT NULL COMMENT 'Optional trip context',
    message          TEXT            NOT NULL,
    message_type     VARCHAR(20)     DEFAULT 'text' COMMENT 'text/image/file',
    attachment_url   VARCHAR(255)    DEFAULT NULL,
    is_read          TINYINT(1)      DEFAULT 0,
    is_deleted       TINYINT(1)      DEFAULT 0,
    read_at          TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    INDEX idx_sender_id    (sender_id),
    INDEX idx_receiver_id  (receiver_id),
    INDEX idx_trip_id      (trip_id),
    INDEX idx_is_read      (is_read),
    INDEX idx_created_at   (created_at),
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_receiver
        FOREIGN KEY (receiver_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_trip
        FOREIGN KEY (trip_id) REFERENCES ft_trips (trip_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_reviews
-- Purpose: Trip reviews and ratings
-- Rows Expected: ~2,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_reviews (
    review_id        INT             NOT NULL AUTO_INCREMENT,
    trip_id          INT             NOT NULL,
    reviewer_id      INT             NOT NULL COMMENT 'User writing the review',
    reviewed_user_id INT             DEFAULT NULL COMMENT 'Optional: reviewing a trip organizer',
    rating           TINYINT         NOT NULL COMMENT '1-5 star rating',
    review_text      TEXT            DEFAULT NULL,
    photos           TEXT            DEFAULT NULL COMMENT 'JSON array of photo URLs',
    is_anonymous     TINYINT(1)      DEFAULT 0,
    status           VARCHAR(20)     DEFAULT 'published' COMMENT 'published/hidden/flagged',
    admin_notes      TEXT            DEFAULT NULL,
    helpful_count    INT             DEFAULT 0,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uq_review          (trip_id, reviewer_id),
    INDEX idx_trip_id             (trip_id),
    INDEX idx_reviewer_id         (reviewer_id),
    INDEX idx_rating              (rating),
    INDEX idx_status              (status),
    CONSTRAINT fk_reviews_trip
        FOREIGN KEY (trip_id) REFERENCES ft_trips (trip_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_notifications
-- Purpose: User alerts and notifications
-- Rows Expected: ~50,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_notifications (
    notification_id  INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    type             VARCHAR(50)     NOT NULL COMMENT 'connection_request/message/trip_update/review/coin/system',
    title            VARCHAR(255)    NOT NULL,
    message          TEXT            DEFAULT NULL,
    related_id       INT             DEFAULT NULL COMMENT 'ID of related entity (trip_id, user_id, etc)',
    related_type     VARCHAR(50)     DEFAULT NULL COMMENT 'trip/user/message/review/coin',
    action_url       VARCHAR(255)    DEFAULT NULL,
    is_read          TINYINT(1)      DEFAULT 0,
    read_at          TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    INDEX idx_user_id     (user_id),
    INDEX idx_type        (type),
    INDEX idx_is_read     (is_read),
    INDEX idx_created_at  (created_at),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 4: CONTENT TABLES (3 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_stories
-- Purpose: Travel blog / story posts
-- Rows Expected: ~500
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_stories (
    story_id         INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    trip_id          INT             DEFAULT NULL COMMENT 'Optional associated trip',
    title            VARCHAR(255)    NOT NULL,
    slug             VARCHAR(255)    DEFAULT NULL,
    content          LONGTEXT        NOT NULL,
    cover_image      VARCHAR(255)    DEFAULT NULL,
    tags             VARCHAR(500)    DEFAULT NULL COMMENT 'Comma-separated tags',
    status           VARCHAR(20)     DEFAULT 'published' COMMENT 'draft/published/hidden',
    is_featured      TINYINT(1)      DEFAULT 0,
    views            INT             DEFAULT 0,
    likes_count      INT             DEFAULT 0,
    comments_count   INT             DEFAULT 0,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (story_id),
    UNIQUE KEY uq_slug         (slug),
    INDEX idx_user_id          (user_id),
    INDEX idx_trip_id          (trip_id),
    INDEX idx_status           (status),
    INDEX idx_is_featured      (is_featured),
    INDEX idx_created_at       (created_at),
    CONSTRAINT fk_stories_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stories_trip
        FOREIGN KEY (trip_id) REFERENCES ft_trips (trip_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_story_likes
-- Purpose: Story engagement (likes)
-- Rows Expected: ~2,500
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_story_likes (
    like_id          INT             NOT NULL AUTO_INCREMENT,
    story_id         INT             NOT NULL,
    user_id          INT             NOT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (like_id),
    UNIQUE KEY uq_story_user   (story_id, user_id),
    INDEX idx_story_id         (story_id),
    INDEX idx_user_id          (user_id),
    CONSTRAINT fk_story_likes_story
        FOREIGN KEY (story_id) REFERENCES ft_stories (story_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_story_likes_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_destinations
-- Purpose: Popular destination directory
-- Rows Expected: ~100
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_destinations (
    destination_id   INT             NOT NULL AUTO_INCREMENT,
    name             VARCHAR(200)    NOT NULL,
    country          VARCHAR(100)    DEFAULT NULL,
    state            VARCHAR(100)    DEFAULT NULL,
    description      TEXT            DEFAULT NULL,
    image            VARCHAR(255)    DEFAULT NULL,
    total_trips      INT             DEFAULT 0,
    avg_budget       INT             DEFAULT NULL,
    best_season      VARCHAR(100)    DEFAULT NULL,
    is_featured      TINYINT(1)      DEFAULT 0,
    sort_order       INT             DEFAULT 0,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (destination_id),
    INDEX idx_country      (country),
    INDEX idx_is_featured  (is_featured),
    INDEX idx_total_trips  (total_trips)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 5: MODERATION TABLES (3 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_reports
-- Purpose: User and trip reports / flagging
-- Rows Expected: ~500
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_reports (
    report_id            INT             NOT NULL AUTO_INCREMENT,
    reporter_id          INT             NOT NULL,
    reported_user_id     INT             DEFAULT NULL,
    reported_trip_id     INT             DEFAULT NULL,
    report_type          VARCHAR(50)     NOT NULL COMMENT 'spam/fake/abuse/inappropriate/fraud/other',
    reason               TEXT            NOT NULL,
    evidence_url         VARCHAR(255)    DEFAULT NULL,
    status               VARCHAR(20)     DEFAULT 'pending' COMMENT 'pending/under_review/resolved/dismissed',
    admin_notes          TEXT            DEFAULT NULL,
    resolved_by          INT             DEFAULT NULL,
    created_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    resolved_at          TIMESTAMP       DEFAULT NULL,
    PRIMARY KEY (report_id),
    INDEX idx_reporter_id        (reporter_id),
    INDEX idx_reported_user_id   (reported_user_id),
    INDEX idx_reported_trip_id   (reported_trip_id),
    INDEX idx_status             (status),
    INDEX idx_created_at         (created_at),
    CONSTRAINT fk_reports_reporter
        FOREIGN KEY (reporter_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reports_reported_user
        FOREIGN KEY (reported_user_id) REFERENCES ft_users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reports_reported_trip
        FOREIGN KEY (reported_trip_id) REFERENCES ft_trips (trip_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_blocked_users
-- Purpose: User block list
-- Rows Expected: ~1,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_blocked_users (
    block_id             INT             NOT NULL AUTO_INCREMENT,
    user_id              INT             NOT NULL COMMENT 'User who blocked',
    blocked_user_id      INT             NOT NULL COMMENT 'User who is blocked',
    reason               VARCHAR(255)    DEFAULT NULL,
    created_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (block_id),
    UNIQUE KEY uq_block          (user_id, blocked_user_id),
    INDEX idx_user_id            (user_id),
    INDEX idx_blocked_user_id    (blocked_user_id),
    CONSTRAINT fk_blocked_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_blocked_target
        FOREIGN KEY (blocked_user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_faqs
-- Purpose: Frequently Asked Questions content
-- Rows Expected: ~20
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_faqs (
    faq_id           INT             NOT NULL AUTO_INCREMENT,
    question         TEXT            NOT NULL,
    answer           TEXT            NOT NULL,
    category         VARCHAR(50)     DEFAULT 'general' COMMENT 'general/account/trips/payments/safety/coins',
    sort_order       INT             DEFAULT 0,
    is_active        TINYINT(1)      DEFAULT 1,
    views            INT             DEFAULT 0,
    helpful_yes      INT             DEFAULT 0,
    helpful_no       INT             DEFAULT 0,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (faq_id),
    INDEX idx_category    (category),
    INDEX idx_is_active   (is_active),
    INDEX idx_sort_order  (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SECTION 6: ADMIN TABLES (1 Table)
-- =============================================================================

-- ===========================================
-- Table: ft_admin_settings
-- Purpose: 22 platform configuration values
-- Rows Expected: 22 (fixed config rows)
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_admin_settings (
    setting_id       INT             NOT NULL AUTO_INCREMENT,
    setting_key      VARCHAR(100)    NOT NULL,
    setting_value    TEXT            DEFAULT NULL,
    setting_type     VARCHAR(20)     DEFAULT 'string' COMMENT 'string/integer/boolean/json',
    category         VARCHAR(50)     DEFAULT 'general' COMMENT 'general/coins/email/security/payment',
    description      VARCHAR(255)    DEFAULT NULL,
    is_public        TINYINT(1)      DEFAULT 0,
    updated_by       INT             DEFAULT NULL,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_id),
    UNIQUE KEY uq_setting_key   (setting_key),
    INDEX idx_category          (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert the 22 default platform settings
INSERT INTO ft_admin_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('site_name',              'Fellow Traveler',      'string',  'general',  'Platform name',                    1),
('site_tagline',           'Travel Together',      'string',  'general',  'Platform tagline',                 1),
('site_email',             'admin@ftraveler.com',  'string',  'general',  'Admin contact email',              0),
('site_phone',             '',                     'string',  'general',  'Support phone number',             1),
('site_currency',          'INR',                  'string',  'general',  'Default currency',                 1),
('maintenance_mode',       '0',                    'boolean', 'general',  'Enable/disable maintenance mode',  0),
('registration_enabled',   '1',                    'boolean', 'general',  'Allow new user registrations',     0),
('max_trip_members',       '20',                   'integer', 'general',  'Max members allowed per trip',     1),
('coin_signup_bonus',      '100',                  'integer', 'coins',    'Coins awarded on signup',          1),
('coin_referral_bonus',    '50',                   'integer', 'coins',    'Coins for each referral',          1),
('coin_trip_create',       '30',                   'integer', 'coins',    'Coins for creating a trip',        1),
('coin_review_write',      '20',                   'integer', 'coins',    'Coins for writing a review',       1),
('coin_profile_complete',  '50',                   'integer', 'coins',    'Coins for completing profile',     1),
('coin_kyc_verified',      '100',                  'integer', 'coins',    'Coins for completing KYC',         1),
('email_welcome',          '1',                    'boolean', 'email',    'Send welcome email on signup',     0),
('email_notifications',    '1',                    'boolean', 'email',    'Send email notifications',         0),
('login_attempts_max',     '5',                    'integer', 'security', 'Max failed login attempts',        0),
('login_lockout_minutes',  '30',                   'integer', 'security', 'Lockout duration in minutes',      0),
('session_lifetime_hours', '24',                   'integer', 'security', 'Session expiry in hours',          0),
('password_min_length',    '8',                    'integer', 'security', 'Minimum password length',          1),
('premium_price_monthly',  '299',                  'integer', 'payment',  'Monthly premium price (INR)',      1),
('premium_price_yearly',   '2499',                 'integer', 'payment',  'Yearly premium price (INR)',       1);


-- =============================================================================
-- SECTION 7: COIN SYSTEM TABLES (7 Tables)
-- =============================================================================

-- ===========================================
-- Table: ft_coin_levels
-- Purpose: 4 user tier / level definitions
-- Rows Expected: 4 (fixed)
-- Note: Created before ft_user_coins (FK dependency)
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_coin_levels (
    level_id         INT             NOT NULL AUTO_INCREMENT,
    level_name       VARCHAR(50)     NOT NULL,
    level_code       VARCHAR(20)     NOT NULL COMMENT 'bronze/silver/gold/platinum',
    min_coins        INT             NOT NULL DEFAULT 0,
    max_coins        INT             DEFAULT NULL COMMENT 'NULL = no upper limit (top tier)',
    badge_image      VARCHAR(255)    DEFAULT NULL,
    color_hex        VARCHAR(7)      DEFAULT NULL,
    perks            TEXT            DEFAULT NULL COMMENT 'JSON array of perks',
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (level_id),
    UNIQUE KEY uq_level_code  (level_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 4 coin levels
INSERT INTO ft_coin_levels (level_name, level_code, min_coins, max_coins, color_hex, perks) VALUES
('Bronze Traveler',   'bronze',   0,    999,  '#CD7F32', '["Basic features", "Standard support"]'),
('Silver Explorer',   'silver',   1000, 4999, '#C0C0C0', '["Priority support", "5% trip discount", "Featured profile"]'),
('Gold Adventurer',   'gold',     5000, 9999, '#FFD700', '["VIP support", "10% discount", "Featured profile", "Early access"]'),
('Platinum Nomad',    'platinum', 10000, NULL, '#E5E4E2', '["Dedicated support", "15% discount", "Top featured", "Beta features", "Exclusive events"]');


-- ===========================================
-- Table: ft_coin_plans
-- Purpose: 27 coin earning action definitions
-- Rows Expected: 27 (fixed)
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_coin_plans (
    plan_id          INT             NOT NULL AUTO_INCREMENT,
    action_name      VARCHAR(100)    NOT NULL,
    action_key       VARCHAR(100)    NOT NULL,
    coins_awarded    INT             NOT NULL DEFAULT 0,
    description      TEXT            DEFAULT NULL,
    max_per_day      INT             DEFAULT NULL COMMENT 'NULL = unlimited',
    max_per_month    INT             DEFAULT NULL,
    max_lifetime     INT             DEFAULT NULL,
    is_active        TINYINT(1)      DEFAULT 1,
    category         VARCHAR(50)     DEFAULT 'engagement' COMMENT 'onboarding/social/trips/content/daily',
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (plan_id),
    UNIQUE KEY uq_action_key  (action_key),
    INDEX idx_is_active       (is_active),
    INDEX idx_category        (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 27 coin earning plans
INSERT INTO ft_coin_plans (action_name, action_key, coins_awarded, description, max_per_day, max_per_month, max_lifetime, category) VALUES
('Account Registration',    'signup',                100,  'Coins for creating an account',                  NULL, NULL, 1,    'onboarding'),
('Email Verification',      'email_verify',          50,   'Verify your email address',                      NULL, NULL, 1,    'onboarding'),
('Phone Verification',      'phone_verify',          50,   'Verify your phone number',                       NULL, NULL, 1,    'onboarding'),
('Profile Completion',      'profile_complete',      50,   'Complete all profile fields',                    NULL, NULL, 1,    'onboarding'),
('Profile Photo Upload',    'profile_photo',         25,   'Upload a profile photo',                         NULL, NULL, 1,    'onboarding'),
('KYC Verification',        'kyc_verified',          100,  'Complete KYC document verification',             NULL, NULL, 1,    'onboarding'),
('Daily Login',             'daily_login',           5,    'Login reward once per day',                      1,    30,   NULL, 'daily'),
('Referral Signup',         'referral_signup',       50,   'Friend signs up using your referral code',       NULL, 10,   NULL, 'social'),
('Send Connection Request', 'connection_sent',       2,    'Send a connection request',                      5,    NULL, NULL, 'social'),
('Accept Connection',       'connection_accepted',   5,    'Accept a connection request',                    5,    NULL, NULL, 'social'),
('Follow a User',           'user_follow',           1,    'Follow another traveler',                        10,   NULL, NULL, 'social'),
('Reach 10 Connections',    'milestone_10_conn',     50,   'Achieve 10 connections milestone',               NULL, NULL, 1,    'social'),
('Reach 50 Connections',    'milestone_50_conn',     100,  'Achieve 50 connections milestone',               NULL, NULL, 1,    'social'),
('Create a Trip',           'trip_create',           30,   'Create and publish a trip',                      2,    NULL, NULL, 'trips'),
('Trip Gets 10 Views',      'trip_10_views',         10,   'Your trip reaches 10 views',                     NULL, NULL, NULL, 'trips'),
('Trip Gets 50 Views',      'trip_50_views',         25,   'Your trip reaches 50 views',                     NULL, NULL, NULL, 'trips'),
('Trip Member Joins',       'trip_member_join',      15,   'A member joins your trip',                       5,    NULL, NULL, 'trips'),
('Bookmark a Trip',         'trip_bookmark',         2,    'Save a trip to bookmarks',                       5,    NULL, NULL, 'trips'),
('Write a Review',          'review_write',          20,   'Write a review for a completed trip',            2,    NULL, NULL, 'content'),
('Review Marked Helpful',   'review_helpful',        5,    'Your review marked as helpful by others',        NULL, NULL, NULL, 'content'),
('Write a Story',           'story_create',          25,   'Publish a travel story/blog post',               1,    NULL, NULL, 'content'),
('Story Gets 10 Likes',     'story_10_likes',        20,   'Your story gets 10 likes',                       NULL, NULL, NULL, 'content'),
('Story Gets 50 Likes',     'story_50_likes',        50,   'Your story gets 50 likes',                       NULL, NULL, NULL, 'content'),
('Upload Trip Photo',       'photo_upload',          5,    'Upload a photo to a trip gallery',               3,    NULL, NULL, 'content'),
('Complete Profile Survey', 'survey_complete',       30,   'Complete the traveler preference survey',        NULL, NULL, 1,    'onboarding'),
('Premium Subscription',    'premium_subscribe',     200,  'Subscribe to premium membership',                NULL, NULL, NULL, 'social'),
('Invite via Share Link',   'share_invite',          3,    'Share platform via referral link',               3,    30,   NULL, 'social');


-- ===========================================
-- Table: ft_user_coins
-- Purpose: User coin wallets / balances
-- Rows Expected: ~10,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_user_coins (
    wallet_id        INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    total_coins      INT             NOT NULL DEFAULT 0 COMMENT 'Current balance',
    coins_earned     INT             NOT NULL DEFAULT 0 COMMENT 'All-time earned',
    coins_spent      INT             NOT NULL DEFAULT 0 COMMENT 'All-time spent',
    level_id         INT             DEFAULT 1,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (wallet_id),
    UNIQUE KEY uq_user_id  (user_id),
    INDEX idx_level_id     (level_id),
    INDEX idx_total_coins  (total_coins),
    CONSTRAINT fk_user_coins_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_coins_level
        FOREIGN KEY (level_id) REFERENCES ft_coin_levels (level_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_coin_transactions
-- Purpose: Full coin transaction history
-- Rows Expected: ~200,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_coin_transactions (
    transaction_id   BIGINT          NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    plan_id          INT             DEFAULT NULL,
    coins            INT             NOT NULL COMMENT 'Positive = earned, Negative = spent',
    type             VARCHAR(20)     NOT NULL COMMENT 'earned/spent/expired/adjusted',
    balance_after    INT             NOT NULL COMMENT 'Wallet balance after this transaction',
    description      VARCHAR(255)    DEFAULT NULL,
    reference_id     INT             DEFAULT NULL,
    reference_type   VARCHAR(50)     DEFAULT NULL COMMENT 'trip/review/story/reward/admin',
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (transaction_id),
    INDEX idx_user_id      (user_id),
    INDEX idx_plan_id      (plan_id),
    INDEX idx_type         (type),
    INDEX idx_created_at   (created_at),
    CONSTRAINT fk_coin_tx_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_coin_tx_plan
        FOREIGN KEY (plan_id) REFERENCES ft_coin_plans (plan_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_coin_rewards
-- Purpose: 10 coin redemption options
-- Rows Expected: 10 (fixed)
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_coin_rewards (
    reward_id        INT             NOT NULL AUTO_INCREMENT,
    reward_name      VARCHAR(100)    NOT NULL,
    reward_description TEXT          DEFAULT NULL,
    coins_required   INT             NOT NULL,
    reward_type      VARCHAR(50)     NOT NULL COMMENT 'discount/premium_days/cashback/feature/physical',
    reward_value     TEXT            DEFAULT NULL COMMENT 'JSON: {"type":"percent","value":10}',
    stock            INT             DEFAULT NULL COMMENT 'NULL = unlimited',
    total_redeemed   INT             DEFAULT 0,
    validity_days    INT             DEFAULT NULL COMMENT 'Days until reward expires after redemption',
    is_active        TINYINT(1)      DEFAULT 1,
    sort_order       INT             DEFAULT 0,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (reward_id),
    INDEX idx_is_active      (is_active),
    INDEX idx_coins_required (coins_required)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 10 coin reward options
INSERT INTO ft_coin_rewards (reward_name, reward_description, coins_required, reward_type, reward_value, validity_days, is_active, sort_order) VALUES
('Trip Discount 5%',    '5% discount on next trip booking',     200,  'discount',     '{"type":"percent","value":5}',    30,  1, 1),
('Trip Discount 10%',   '10% discount on next trip booking',    400,  'discount',     '{"type":"percent","value":10}',   30,  1, 2),
('Trip Discount 15%',   '15% discount on next trip booking',    600,  'discount',     '{"type":"percent","value":15}',   30,  1, 3),
('Premium - 7 Days',    '7 days of Premium membership',         300,  'premium_days', '{"days":7}',                      NULL, 1, 4),
('Premium - 30 Days',   '30 days of Premium membership',        1000, 'premium_days', '{"days":30}',                     NULL, 1, 5),
('Featured Trip - 3 Days', 'Feature your trip for 3 days',      150,  'feature',      '{"type":"trip","days":3}',        NULL, 1, 6),
('Featured Trip - 7 Days', 'Feature your trip for 7 days',      300,  'feature',      '{"type":"trip","days":7}',        NULL, 1, 7),
('Featured Profile - 7 Days', 'Feature your profile for 7 days', 200, 'feature',     '{"type":"profile","days":7}',     NULL, 1, 8),
('Cashback ₹50',        '₹50 cashback to your account',         500,  'cashback',     '{"currency":"INR","value":50}',   30,  1, 9),
('Cashback ₹100',       '₹100 cashback to your account',        900,  'cashback',     '{"currency":"INR","value":100}',  30,  1, 10);


-- ===========================================
-- Table: ft_coin_redemptions
-- Purpose: Coin redemption log / history
-- Rows Expected: ~5,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_coin_redemptions (
    redemption_id    INT             NOT NULL AUTO_INCREMENT,
    user_id          INT             NOT NULL,
    reward_id        INT             NOT NULL,
    coins_spent      INT             NOT NULL,
    status           VARCHAR(20)     DEFAULT 'pending' COMMENT 'pending/processing/completed/cancelled',
    promo_code       VARCHAR(50)     DEFAULT NULL,
    used_at          TIMESTAMP       DEFAULT NULL,
    expires_at       TIMESTAMP       DEFAULT NULL,
    admin_notes      TEXT            DEFAULT NULL,
    processed_by     INT             DEFAULT NULL,
    redeemed_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (redemption_id),
    INDEX idx_user_id    (user_id),
    INDEX idx_reward_id  (reward_id),
    INDEX idx_status     (status),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_redemptions_user
        FOREIGN KEY (user_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_redemptions_reward
        FOREIGN KEY (reward_id) REFERENCES ft_coin_rewards (reward_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===========================================
-- Table: ft_referrals
-- Purpose: Referral program tracking
-- Rows Expected: ~3,000
-- ===========================================
CREATE TABLE IF NOT EXISTS ft_referrals (
    referral_id      INT             NOT NULL AUTO_INCREMENT,
    referrer_id      INT             NOT NULL COMMENT 'User who shared referral code',
    referee_id       INT             NOT NULL COMMENT 'User who used referral code',
    referral_code    VARCHAR(50)     NOT NULL,
    status           VARCHAR(20)     DEFAULT 'pending' COMMENT 'pending/completed/rewarded',
    coins_to_referrer INT            DEFAULT 50,
    coins_to_referee  INT            DEFAULT 50,
    rewarded_at      TIMESTAMP       DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (referral_id),
    UNIQUE KEY uq_referee         (referee_id) COMMENT 'Each user can only be referred once',
    INDEX idx_referrer_id         (referrer_id),
    INDEX idx_referral_code       (referral_code),
    INDEX idx_status              (status),
    CONSTRAINT fk_referrals_referrer
        FOREIGN KEY (referrer_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_referrals_referee
        FOREIGN KEY (referee_id) REFERENCES ft_users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Re-enable foreign key checks
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================================
-- VERIFICATION QUERIES
-- Run these to confirm successful installation
-- =============================================================================

-- Verify table count (should return 25)
-- SELECT COUNT(*) AS total_tables
-- FROM information_schema.tables
-- WHERE table_schema = 'fellow_traveler';

-- List all tables
-- SHOW TABLES;

-- Check admin settings (should return 22 rows)
-- SELECT COUNT(*) FROM ft_admin_settings;

-- Check coin levels (should return 4 rows)
-- SELECT * FROM ft_coin_levels;

-- Check coin plans (should return 27 rows)
-- SELECT COUNT(*) FROM ft_coin_plans;

-- Check coin rewards (should return 10 rows)
-- SELECT COUNT(*) FROM ft_coin_rewards;

-- =============================================================================
-- END OF SCHEMA
-- Fellow Traveler Database Schema v1.0
-- Total Tables: 25
-- =============================================================================
