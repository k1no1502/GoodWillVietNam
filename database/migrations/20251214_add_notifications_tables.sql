-- Migration: Add admin notifications + extended notification metadata
-- Run this against existing goodwill_vietnam database

ALTER TABLE notifications
    ADD COLUMN sent_by INT NULL AFTER user_id,
    ADD COLUMN category ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'general' AFTER type,
    ADD CONSTRAINT fk_notifications_sender FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS admin_notifications (
    admin_notify_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'system',
    severity ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    target_type ENUM('all', 'selected') DEFAULT 'all',
    target_user_ids JSON,
    status ENUM('draft', 'scheduled', 'sent', 'cancelled') DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
