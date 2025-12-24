-- Store precise shipping geodata from Google Places (or other providers)
-- Compatible with MySQL < 8 using information_schema checks
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_place_id');
SET @sql := IF(@col_missing, 'ALTER TABLE orders ADD COLUMN shipping_place_id VARCHAR(128) NULL AFTER shipping_address', 'SELECT ''column shipping_place_id exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_lat');
SET @sql := IF(@col_missing, 'ALTER TABLE orders ADD COLUMN shipping_lat DECIMAL(10,7) NULL AFTER shipping_place_id', 'SELECT ''column shipping_lat exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_lng');
SET @sql := IF(@col_missing, 'ALTER TABLE orders ADD COLUMN shipping_lng DECIMAL(10,7) NULL AFTER shipping_lat', 'SELECT ''column shipping_lng exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
