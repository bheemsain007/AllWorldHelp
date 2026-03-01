-- =============================================================================
-- Fellow Traveler Platform - Performance Indexes
-- Task T1: indexes.sql
-- Version: 1.0
-- Date: February 2026
-- Description: Additional performance indexes for Fellow Traveler database
-- Note: Core indexes are already defined in fellow_traveler_schema.sql
--       Run this AFTER the schema SQL has been executed successfully.
-- =============================================================================

USE fellow_traveler;

-- =============================================================================
-- ft_users - User search & filtering indexes
-- =============================================================================
-- Composite: search by city + status (user discovery)
CREATE INDEX idx_users_city_status
    ON ft_users (city, status);

-- Composite: country + city for location-based filtering
CREATE INDEX idx_users_location
    ON ft_users (country, city);

-- Premium users filter
CREATE INDEX idx_users_premium
    ON ft_users (is_premium, status);

-- KYC status for admin verification queues
CREATE INDEX idx_users_kyc
    ON ft_users (kyc_status);

-- Last login for inactive user detection
CREATE INDEX idx_users_last_login
    ON ft_users (last_login);


-- =============================================================================
-- ft_trips - Trip search & discovery indexes
-- =============================================================================
-- Composite: destination + status (main search query)
CREATE INDEX idx_trips_dest_status
    ON ft_trips (destination, status);

-- Date range queries (find upcoming trips)
CREATE INDEX idx_trips_dates
    ON ft_trips (start_date, end_date);

-- Budget range filtering
CREATE INDEX idx_trips_budget
    ON ft_trips (budget_min, budget_max);

-- Composite: type + status (category filtering)
CREATE INDEX idx_trips_type_status
    ON ft_trips (trip_type, status);

-- Gender preference filter
CREATE INDEX idx_trips_gender
    ON ft_trips (gender_preference);

-- Free trips filter
CREATE INDEX idx_trips_free
    ON ft_trips (is_free, status);

-- Views count for trending/popular trips
CREATE INDEX idx_trips_views
    ON ft_trips (views DESC);

-- Composite: user + status (my trips page)
CREATE INDEX idx_trips_user_status
    ON ft_trips (user_id, status);


-- =============================================================================
-- ft_sessions - Session management indexes
-- =============================================================================
-- Active session lookup by user
CREATE INDEX idx_sessions_user_active
    ON ft_sessions (user_id, is_active);

-- Cleanup expired sessions
CREATE INDEX idx_sessions_expires
    ON ft_sessions (expires_at, is_active);


-- =============================================================================
-- ft_activity_logs - Audit log query indexes
-- =============================================================================
-- Admin audit: user + date range
CREATE INDEX idx_logs_user_date
    ON ft_activity_logs (user_id, created_at);

-- Admin audit: action + date
CREATE INDEX idx_logs_action_date
    ON ft_activity_logs (action, created_at);


-- =============================================================================
-- ft_connections - Social graph traversal indexes
-- =============================================================================
-- Find all connections of a user (outbound)
CREATE INDEX idx_conn_sender_status
    ON ft_connections (sender_id, status);

-- Find all connections of a user (inbound)
CREATE INDEX idx_conn_receiver_status
    ON ft_connections (receiver_id, status);

-- Pending requests count for a user
CREATE INDEX idx_conn_receiver_pending
    ON ft_connections (receiver_id, status, created_at);


-- =============================================================================
-- ft_followers - Follow system indexes
-- =============================================================================
-- Who follows this user? (follower count)
CREATE INDEX idx_followers_user_date
    ON ft_followers (user_id, created_at);

-- Who does this user follow? (following count)
CREATE INDEX idx_following_user_date
    ON ft_followers (follower_user_id, created_at);


-- =============================================================================
-- ft_messages - Chat inbox / unread count indexes
-- =============================================================================
-- User inbox (all received messages)
CREATE INDEX idx_messages_receiver_read
    ON ft_messages (receiver_id, is_read, created_at);

-- Conversation thread between two users
CREATE INDEX idx_messages_conversation
    ON ft_messages (sender_id, receiver_id, created_at);

-- Unread count per user
CREATE INDEX idx_messages_unread
    ON ft_messages (receiver_id, is_read);


-- =============================================================================
-- ft_reviews - Trip review queries
-- =============================================================================
-- Trip average rating calculation
CREATE INDEX idx_reviews_trip_rating
    ON ft_reviews (trip_id, rating, status);

-- User's review history
CREATE INDEX idx_reviews_user_date
    ON ft_reviews (reviewer_id, created_at);


-- =============================================================================
-- ft_notifications - Notification center queries
-- =============================================================================
-- User's unread notification count
CREATE INDEX idx_notif_user_unread
    ON ft_notifications (user_id, is_read, created_at);

-- Notification type filtering
CREATE INDEX idx_notif_user_type
    ON ft_notifications (user_id, type);


-- =============================================================================
-- ft_stories - Story feed and search
-- =============================================================================
-- Public story feed ordered by date
CREATE INDEX idx_stories_status_date
    ON ft_stories (status, created_at);

-- User's own stories
CREATE INDEX idx_stories_user_status
    ON ft_stories (user_id, status);

-- Most viewed stories
CREATE INDEX idx_stories_views
    ON ft_stories (views DESC, status);


-- =============================================================================
-- ft_coin_transactions - Coin history queries
-- =============================================================================
-- User transaction history (wallet page)
CREATE INDEX idx_coin_tx_user_date
    ON ft_coin_transactions (user_id, created_at);

-- Transactions by type (earned vs spent)
CREATE INDEX idx_coin_tx_user_type
    ON ft_coin_transactions (user_id, type, created_at);


-- =============================================================================
-- ft_coin_redemptions - Redemption management
-- =============================================================================
-- User redemption history
CREATE INDEX idx_redemptions_user_date
    ON ft_coin_redemptions (user_id, created_at);

-- Admin pending redemptions queue
CREATE INDEX idx_redemptions_status_date
    ON ft_coin_redemptions (status, created_at);


-- =============================================================================
-- ft_reports - Admin moderation queue
-- =============================================================================
-- Pending reports by date
CREATE INDEX idx_reports_status_date
    ON ft_reports (status, created_at);


-- =============================================================================
-- ft_bookmarks - User saved trips
-- =============================================================================
-- User's saved trips sorted by date
CREATE INDEX idx_bookmarks_user_date
    ON ft_bookmarks (user_id, created_at);


-- =============================================================================
-- ft_kyc_documents - Admin verification queue
-- =============================================================================
-- Admin KYC review queue
CREATE INDEX idx_kyc_status_date
    ON ft_kyc_documents (status, submitted_at);


-- =============================================================================
-- ft_referrals - Referral tracking
-- =============================================================================
-- Referrer's referral count
CREATE INDEX idx_referrals_referrer_status
    ON ft_referrals (referrer_id, status);


-- =============================================================================
-- VERIFY INDEXES
-- Run after execution to confirm index creation
-- =============================================================================
-- SHOW INDEX FROM ft_users;
-- SHOW INDEX FROM ft_trips;
-- SHOW INDEX FROM ft_messages;

-- =============================================================================
-- END OF INDEXES
-- Total additional indexes: ~35
-- =============================================================================
