-- Star Citizen Team Up - MySQL Database Schema
-- This schema is designed for MySQL/MariaDB

-- Create database (if needed)
-- CREATE DATABASE IF NOT EXISTS starcitizen_teamup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE starcitizen_teamup;

-- Activity Types Table
CREATE TABLE IF NOT EXISTS `starcitizen_teamup_activity_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  INDEX `idx_display_order` (`display_order`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default activity types
INSERT INTO `starcitizen_teamup_activity_types` (`name`, `display_order`) VALUES
('Bounty Hunting', 1),
('Mining', 2),
('Salvaging', 3),
('Trading', 4),
('Mercenary', 5),
('Investigations', 6),
('Search and Rescue', 7),
('Piracy', 8),
('PVP', 9),
('Exploration', 10),
('Xenothreat', 11),
('Nine Tails Lockdown', 12),
('Other', 99)
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Groups Table
CREATE TABLE IF NOT EXISTS `starcitizen_teamup_groups` (
  `id` CHAR(36) PRIMARY KEY,
  `creator_handle` VARCHAR(50) NOT NULL,
  `activity_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ship` VARCHAR(50),
  `discord_invite` VARCHAR(255),
  `max_players` INT NOT NULL,
  `status` ENUM('open', 'full', 'closed') NOT NULL DEFAULT 'open',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Constraints
  CONSTRAINT `chk_creator_handle` CHECK (
    CHAR_LENGTH(creator_handle) >= 3 AND
    CHAR_LENGTH(creator_handle) <= 50 AND
    creator_handle REGEXP '^[a-zA-Z0-9_-]+$'
  ),
  CONSTRAINT `chk_title` CHECK (
    CHAR_LENGTH(title) >= 3 AND CHAR_LENGTH(title) <= 100
  ),
  CONSTRAINT `chk_description` CHECK (
    description IS NULL OR CHAR_LENGTH(description) <= 500
  ),
  CONSTRAINT `chk_ship` CHECK (
    ship IS NULL OR (CHAR_LENGTH(ship) >= 2 AND CHAR_LENGTH(ship) <= 50)
  ),
  CONSTRAINT `chk_discord_invite` CHECK (
    discord_invite IS NULL OR CHAR_LENGTH(discord_invite) <= 255
  ),
  CONSTRAINT `chk_max_players` CHECK (
    max_players >= 2 AND max_players <= 50
  ),

  -- Indexes
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_expires_at` (`expires_at`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_ship` (`ship`),
  INDEX `idx_status_expires` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members Table
CREATE TABLE IF NOT EXISTS `starcitizen_teamup_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` CHAR(36) NOT NULL,
  `player_handle` VARCHAR(50) NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Foreign key with cascade delete
  CONSTRAINT `fk_members_group`
    FOREIGN KEY (`group_id`)
    REFERENCES `starcitizen_teamup_groups`(`id`)
    ON DELETE CASCADE,

  -- Prevent duplicate joins
  UNIQUE KEY `unique_group_player` (`group_id`, `player_handle`),

  -- Constraints
  CONSTRAINT `chk_player_handle` CHECK (
    CHAR_LENGTH(player_handle) >= 3 AND
    CHAR_LENGTH(player_handle) <= 50 AND
    player_handle REGEXP '^[a-zA-Z0-9_-]+$'
  ),

  -- Indexes
  INDEX `idx_group_id` (`group_id`),
  INDEX `idx_player_handle` (`player_handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(50) NOT NULL,
  `identifier` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  INDEX `idx_action_identifier` (`action`, `identifier`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up old rate limit entries (optional, can be run periodically)
-- DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Example: Create a stored procedure to clean expired groups (optional)
-- This deletes expired groups based on their status and expiry time:
-- - Full groups: expire after 10 minutes
-- - Non-full groups: expire after 2 hours
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS clean_expired_groups()
BEGIN
  DELETE FROM starcitizen_teamup_groups
  WHERE status IN ('open', 'full')
  AND expires_at < NOW();
END //
DELIMITER ;

-- You can call this procedure periodically or from a cron job
-- CALL clean_expired_groups();

-- Feedback Table
CREATE TABLE IF NOT EXISTS `starcitizen_teamup_feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rating` TINYINT NOT NULL,
  `message` TEXT,
  `user_ip` VARCHAR(45),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Constraints
  CONSTRAINT `chk_feedback_rating` CHECK (rating >= 1 AND rating <= 5),
  CONSTRAINT `chk_feedback_message` CHECK (
    message IS NULL OR CHAR_LENGTH(message) <= 1000
  ),

  -- Indexes
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Ratings Table
CREATE TABLE IF NOT EXISTS `starcitizen_teamup_user_ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_handle` VARCHAR(50) NOT NULL,
  `rating` TINYINT NOT NULL,
  `rated_by_ip` VARCHAR(45) NOT NULL,
  `rated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Constraints
  CONSTRAINT `chk_rating_value` CHECK (rating IN (-1, 1)),
  CONSTRAINT `chk_rating_handle` CHECK (
    CHAR_LENGTH(player_handle) >= 3 AND
    CHAR_LENGTH(player_handle) <= 50 AND
    player_handle REGEXP '^[a-zA-Z0-9_-]+$'
  ),

  -- Indexes
  INDEX `idx_player_handle` (`player_handle`),
  INDEX `idx_rated_by_ip` (`rated_by_ip`),
  INDEX `idx_rated_at` (`rated_at`),
  UNIQUE KEY `unique_ip_handle_daily` (`rated_by_ip`, `player_handle`, `rated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
