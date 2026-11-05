-- ============================================================================
-- Thai Amulet Marketplace & Real-Time Auction Database Schema
-- Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `amulet_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `amulet_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Amulets Table
CREATE TABLE IF NOT EXISTS `amulets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `monk_name` VARCHAR(150) NOT NULL,
    `temple_name` VARCHAR(150) NOT NULL,
    `year_made` VARCHAR(20) DEFAULT NULL,
    `type` ENUM('fixed', 'auction') NOT NULL DEFAULT 'fixed',
    `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_amulets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Amulet Stats (Module 3 Radar Chart Values 1-10)
CREATE TABLE IF NOT EXISTS `amulet_stats` (
    `amulet_id` INT UNSIGNED PRIMARY KEY,
    `protection` TINYINT UNSIGNED NOT NULL DEFAULT 5, -- พุทธคุณ
    `popularity` TINYINT UNSIGNED NOT NULL DEFAULT 5, -- เมตตามหานิยม
    `charm` TINYINT UNSIGNED NOT NULL DEFAULT 5,      -- มหาเสน่ห์
    `evasion` TINYINT UNSIGNED NOT NULL DEFAULT 5,    -- แคล้วคลาด
    `invincibility` TINYINT UNSIGNED NOT NULL DEFAULT 5, -- คงกระพันชาตรี
    `wealth` TINYINT UNSIGNED NOT NULL DEFAULT 5,     -- โชคลาภ
    CONSTRAINT `fk_stats_amulet` FOREIGN KEY (`amulet_id`) REFERENCES `amulets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Auctions Table (Module 2)
CREATE TABLE IF NOT EXISTS `auctions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `amulet_id` INT UNSIGNED NOT NULL UNIQUE,
    `start_price` DECIMAL(12, 2) NOT NULL,
    `current_price` DECIMAL(12, 2) NOT NULL,
    `min_bid_increment` DECIMAL(10, 2) NOT NULL DEFAULT 100.00,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `status` ENUM('active', 'ended', 'cancelled') NOT NULL DEFAULT 'active',
    `winner_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_auctions_amulet` FOREIGN KEY (`amulet_id`) REFERENCES `amulets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_auctions_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bids History Table
CREATE TABLE IF NOT EXISTS `bids` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `auction_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `bid_amount` DECIMAL(12, 2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auction_created` (`auction_id`, `created_at`),
    CONSTRAINT `fk_bids_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bids_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `slip_image` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending_verification', 'preparing', 'shipped', 'cancelled') NOT NULL DEFAULT 'pending_verification',
    `tracking_number` VARCHAR(50) DEFAULT NULL,
    `shipping_address` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `amulet_id` INT UNSIGNED NOT NULL,
    `price` DECIMAL(12, 2) NOT NULL,
    CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_amulet` FOREIGN KEY (`amulet_id`) REFERENCES `amulets` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

