-- OpenSIPS Database Tables Creation Script
-- This script creates only the database tables needed for the admin panel
-- Run with: mysql -u opensips -p opensips < scripts/create-opensips-tables.sql

-- Domain table (with setid column for dispatcher grouping)
CREATE TABLE IF NOT EXISTS `domain` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `domain` VARCHAR(64) NOT NULL DEFAULT '',
    `setid` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `attrs` VARCHAR(255) DEFAULT NULL,
    `accept_subdomain` TINYINT(1) NOT NULL DEFAULT 0,
    `last_modified` DATETIME NOT NULL DEFAULT '1900-01-01 00:00:01',
    PRIMARY KEY (`id`),
    UNIQUE KEY `domain_domain_idx` (`domain`),
    KEY `idx_domain_setid` (`setid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispatcher table
CREATE TABLE IF NOT EXISTS `dispatcher` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setid` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `destination` VARCHAR(192) NOT NULL DEFAULT '',
    `socket` VARCHAR(128) DEFAULT NULL,
    `state` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `probe_mode` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `weight` INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `priority` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `attrs` VARCHAR(128) DEFAULT NULL,
    `description` VARCHAR(64) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `setid_idx` (`setid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- endpoint_locations table (for endpoint registration tracking)
CREATE TABLE IF NOT EXISTS `endpoint_locations` (
    `aor` VARCHAR(255) NOT NULL,
    `contact_ip` VARCHAR(45) NOT NULL,
    `contact_port` VARCHAR(10) NOT NULL,
    `contact_uri` VARCHAR(255) NOT NULL,
    `expires` DATETIME NOT NULL,
    PRIMARY KEY (`aor`),
    KEY `idx_endpoint_locations_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
