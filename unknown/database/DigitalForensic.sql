-- DLDS Digital Forensics - Normalized production schema
-- Safe to import into an existing database that does not already contain conflicting tables.

CREATE DATABASE IF NOT EXISTS `DigitalForensics`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `DigitalForensics`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`),
  CONSTRAINT `password_reset_tokens_email_foreign`
    FOREIGN KEY (`email`) REFERENCES `users` (`email`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `severity_levels` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `severity_levels_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alert_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `process_catalog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `process_catalog_process_name_unique` (`process_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dlds_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_time` DATETIME NULL DEFAULT NULL,
  `event_type_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `pid` INT NOT NULL DEFAULT 0,
  `process_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `src_ip` VARCHAR(45) NULL DEFAULT NULL,
  `src_port` INT NOT NULL DEFAULT 0,
  `dst_ip` VARCHAR(45) NULL DEFAULT NULL,
  `dst_port` INT NOT NULL DEFAULT 0,
  `bytes_sent` BIGINT NOT NULL DEFAULT 0,
  `alert_type_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `severity_id` TINYINT UNSIGNED NOT NULL,
  `description` TEXT NULL,
  `event_hash` VARCHAR(64) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dlds_events_event_hash_unique` (`event_hash`),
  KEY `dlds_events_event_time_index` (`event_time`),
  KEY `dlds_events_event_type_id_index` (`event_type_id`),
  KEY `dlds_events_process_id_index` (`process_id`),
  KEY `dlds_events_alert_type_id_index` (`alert_type_id`),
  KEY `dlds_events_severity_id_index` (`severity_id`),
  KEY `dlds_events_src_ip_index` (`src_ip`),
  KEY `dlds_events_dst_ip_index` (`dst_ip`),
  CONSTRAINT `dlds_events_event_type_id_foreign`
    FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `dlds_events_process_id_foreign`
    FOREIGN KEY (`process_id`) REFERENCES `process_catalog` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `dlds_events_alert_type_id_foreign`
    FOREIGN KEY (`alert_type_id`) REFERENCES `alert_types` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `dlds_events_severity_id_foreign`
    FOREIGN KEY (`severity_id`) REFERENCES `severity_levels` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `severity_levels` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'LOW', NOW(), NOW()),
(2, 'MEDIUM', NOW(), NOW()),
(3, 'HIGH', NOW(), NOW()),
(4, 'CRITICAL', NOW(), NOW())
ON DUPLICATE KEY UPDATE
`name` = VALUES(`name`),
`updated_at` = VALUES(`updated_at`);

INSERT INTO `event_types` (`name`, `created_at`, `updated_at`) VALUES
('network', NOW(), NOW()),
('file', NOW(), NOW()),
('process', NOW(), NOW()),
('dns', NOW(), NOW()),
('http', NOW(), NOW()),
('tls', NOW(), NOW()),
('alert', NOW(), NOW()),
('test', NOW(), NOW())
ON DUPLICATE KEY UPDATE
`updated_at` = VALUES(`updated_at`);

INSERT INTO `alert_types` (`name`, `created_at`, `updated_at`) VALUES
('Data Leak', NOW(), NOW()),
('Suspicious Connection', NOW(), NOW()),
('Malware Activity', NOW(), NOW()),
('Unauthorized Access', NOW(), NOW())
ON DUPLICATE KEY UPDATE
`updated_at` = VALUES(`updated_at`);

INSERT INTO `process_catalog` (`process_name`, `created_at`, `updated_at`) VALUES
('chrome.exe', NOW(), NOW()),
('firefox.exe', NOW(), NOW()),
('powershell.exe', NOW(), NOW()),
('cmd.exe', NOW(), NOW()),
('python.exe', NOW(), NOW())
ON DUPLICATE KEY UPDATE
`updated_at` = VALUES(`updated_at`);
