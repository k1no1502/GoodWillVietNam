-- Order tracking events: geo points for journey map
CREATE TABLE IF NOT EXISTS `order_tracking_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `status_code` VARCHAR(64) NOT NULL DEFAULT '',
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `note` TEXT NULL,
  `location_address` VARCHAR(255) NOT NULL DEFAULT '',
  `lat` DECIMAL(10,7) NULL,
  `lng` DECIMAL(10,7) NULL,
  `occurred_at` DATETIME NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_order_tracking_order_time` (`order_id`, `occurred_at`),
  KEY `idx_order_tracking_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

