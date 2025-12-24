-- Final_DB.sql
-- Single-file full schema, seed, views, triggers for Goodwill Vietnam

SET NAMES utf8mb4;

-- Create/select database first to avoid "No database selected"
CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

-- Reset existing objects
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS campaign_volunteers;
DROP TABLE IF EXISTS campaign_donations;
DROP TABLE IF EXISTS campaign_items;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS backups;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- Roles
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(128) NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    role_id INT DEFAULT 2,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donations
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'item',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    estimated_value DECIMAL(10,2),
    images JSON,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    pickup_address TEXT,
    pickup_date DATE,
    pickup_time TIME,
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_donations_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_donations_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory
CREATE TABLE inventory (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'item',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    price_type ENUM('free', 'cheap', 'normal') DEFAULT 'free',
    sale_price DECIMAL(10,2) DEFAULT 0,
    estimated_value DECIMAL(10,2),
    actual_value DECIMAL(10,2),
    images JSON,
    location VARCHAR(100),
    status ENUM('available', 'reserved', 'sold', 'damaged', 'disposed') DEFAULT 'available',
    is_for_sale BOOLEAN DEFAULT TRUE,
    reserved_by INT NULL,
    reserved_until TIMESTAMP NULL,
    sold_to INT NULL,
    sold_at TIMESTAMP NULL,
    sold_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_donation FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_reserved_by FOREIGN KEY (reserved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_sold_to FOREIGN KEY (sold_to) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beneficiaries
CREATE TABLE beneficiaries (
    beneficiary_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    organization_type ENUM('individual', 'ngo', 'charity', 'school', 'hospital', 'other') DEFAULT 'individual',
    description TEXT,
    verification_documents JSON,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_beneficiaries_verified_by FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions
CREATE TABLE transactions (
    trans_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT,
    beneficiary_id INT,
    type ENUM('donation', 'purchase', 'reservation', 'cancellation') NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'free') DEFAULT 'free',
    payment_reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(beneficiary_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns
CREATE TABLE campaigns (
    campaign_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_amount DECIMAL(12,2),
    current_amount DECIMAL(12,2) DEFAULT 0,
    target_items INT,
    current_items INT DEFAULT 0,
    status ENUM('draft', 'pending', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaigns_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign requested items
CREATE TABLE campaign_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    category_id INT,
    quantity_needed INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'item',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_items_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign donations link
CREATE TABLE campaign_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    campaign_item_id INT,
    quantity_contributed INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_donations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_donations_donation FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_donations_item FOREIGN KEY (campaign_item_id) REFERENCES campaign_items(item_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign volunteers
CREATE TABLE campaign_volunteers (
    volunteer_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT,
    skills TEXT,
    availability TEXT,
    role VARCHAR(100),
    approved_by INT,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    hours_contributed INT DEFAULT 0,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaign_volunteers_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_volunteers_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_volunteers_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_user (campaign_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE notifications (
    notify_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    sent_by INT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    category ENUM('system', 'campaign', 'donation', 'order', 'general') DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_sender FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin notification definitions for scheduling/history
CREATE TABLE admin_notifications (
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
    CONSTRAINT fk_admin_notifications_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback
CREATE TABLE feedback (
    fb_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200),
    content TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'read', 'replied', 'closed') DEFAULT 'pending',
    admin_reply TEXT,
    replied_by INT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_feedback_replied_by FOREIGN KEY (replied_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff
CREATE TABLE staff (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE,
    position VARCHAR(100),
    department VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    hire_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    assigned_area VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backups
CREATE TABLE backups (
    backup_id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    backup_type ENUM('full', 'incremental', 'manual') DEFAULT 'manual',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backups_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart
CREATE TABLE cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    user_id INT NOT NULL,
    shipping_name VARCHAR(100),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_method ENUM('pickup', 'delivery') DEFAULT 'pickup',
    shipping_note TEXT,
    payment_method ENUM('cod', 'bank_transfer', 'credit_card', 'free') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_items INT DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(255),
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2),
    price_type ENUM('free', 'cheap', 'normal'),
    subtotal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_item FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order status history
CREATE TABLE order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_history FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_remember_token ON users(remember_token);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_donations_user ON donations(user_id);
CREATE INDEX idx_donations_status ON donations(status);
CREATE INDEX idx_donations_created ON donations(created_at);
CREATE INDEX idx_inventory_status ON inventory(status);
CREATE INDEX idx_inventory_category ON inventory(category_id);
CREATE INDEX idx_inventory_price_type ON inventory(price_type);
CREATE INDEX idx_inventory_for_sale ON inventory(is_for_sale);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaign_items_campaign ON campaign_items(campaign_id);
CREATE INDEX idx_campaign_donations_campaign ON campaign_donations(campaign_id);
CREATE INDEX idx_campaign_volunteers_campaign ON campaign_volunteers(campaign_id);
CREATE INDEX idx_campaign_volunteers_user ON campaign_volunteers(user_id);
CREATE INDEX idx_campaign_volunteers_status ON campaign_volunteers(status);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_item ON order_items(item_id);
CREATE INDEX idx_cart_user ON cart(user_id);

-- Seeds: roles
INSERT INTO roles (role_id, role_name, description, permissions) VALUES
    (1, 'admin', 'System administrator', '{"all": true}'),
    (2, 'user', 'Registered user', '{"donate": true, "browse": true, "order": true}'),
    (3, 'guest', 'Guest', '{"browse": true}')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description),
    permissions = VALUES(permissions);

-- Seeds: categories
INSERT INTO categories (category_id, name, description, icon, sort_order) VALUES
    (1, 'Clothes', 'Clothing items', 'bi-tshirt', 1),
    (2, 'Electronics', 'Phones and laptops', 'bi-laptop', 2),
    (3, 'Books', 'Books and documents', 'bi-book', 3),
    (4, 'Home', 'Household items', 'bi-house', 4),
    (5, 'Toys', 'Toys for kids', 'bi-toy', 5),
    (6, 'Food', 'Food and staples', 'bi-basket', 6),
    (7, 'Health', 'Medical supplies', 'bi-heart-pulse', 7),
    (8, 'Other', 'Miscellaneous', 'bi-box', 8)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    icon = VALUES(icon),
    sort_order = VALUES(sort_order);

-- Seeds: admin users
INSERT INTO users (user_id, name, email, password, role_id, status, email_verified)
SELECT 1, 'Administrator', 'admin@goodwillvietnam.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       1, 'active', TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@goodwillvietnam.com');

INSERT INTO users (user_id, name, email, password, role_id, status, email_verified)
SELECT 2, 'Admin2', 'admin2@goodwillvietnam.com',
       '$2y$10$eImiTXuWVxfM37uY4JANjQ==',
       1, 'active', TRUE
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin2@goodwillvietnam.com');

-- Seeds: system settings
INSERT INTO system_settings (setting_key, setting_value, description, type) VALUES
    ('site_name', 'Goodwill Vietnam', 'Site title', 'string'),
    ('site_description', 'Nen tang thien nguyen ket noi cong dong', 'Site description', 'string'),
    ('contact_email', 'info@goodwillvietnam.com', 'Contact email', 'string'),
    ('contact_phone', '+84 123 456 789', 'Contact phone', 'string'),
    ('max_file_size', '5242880', 'Max upload bytes', 'number'),
    ('allowed_file_types', '["jpg","jpeg","png","gif"]', 'Allowed upload types', 'json'),
    ('items_per_page', '12', 'Items per page', 'number'),
    ('enable_registration', 'true', 'Allow new registrations', 'boolean'),
    ('maintenance_mode', 'false', 'Maintenance mode', 'boolean'),
    ('enable_shop', 'true', 'Enable shop features', 'boolean'),
    ('cheap_price_threshold', '100000', 'Cheap price threshold (VND)', 'number'),
    ('free_shipping_threshold', '500000', 'Free shipping threshold (VND)', 'number'),
    ('order_prefix', 'GW', 'Order number prefix', 'string'),
    ('enable_campaigns', 'true', 'Enable campaigns module', 'boolean'),
    ('campaign_approval_required', 'true', 'Require campaign approval', 'boolean')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
    description = VALUES(description),
    type = VALUES(type);

-- Views
CREATE OR REPLACE VIEW v_statistics AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE status = 'active') AS total_users,
    (SELECT COUNT(*) FROM donations WHERE status != 'cancelled') AS total_donations,
    (SELECT COUNT(*) FROM inventory WHERE status = 'available') AS total_items,
    (SELECT COUNT(*) FROM campaigns WHERE status = 'active') AS active_campaigns,
    (SELECT COUNT(*) FROM transactions WHERE type = 'donation' AND status = 'completed') AS completed_donations,
    (SELECT SUM(amount) FROM transactions WHERE type = 'donation' AND status = 'completed') AS total_donation_value;

CREATE OR REPLACE VIEW v_donation_details AS
SELECT 
    d.*,
    u.name AS donor_name,
    u.email AS donor_email,
    u.phone AS donor_phone,
    c.name AS category_name,
    CASE 
        WHEN d.status = 'pending' THEN 'Cho duyet'
        WHEN d.status = 'approved' THEN 'Da duyet'
        WHEN d.status = 'rejected' THEN 'Tu choi'
        WHEN d.status = 'cancelled' THEN 'Da huy'
    END AS status_text
FROM donations d
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON d.category_id = c.category_id;

CREATE OR REPLACE VIEW v_inventory_items AS
SELECT 
    i.*,
    d.item_name AS donation_name,
    d.description AS donation_description,
    u.name AS donor_name,
    c.name AS category_name,
    CASE 
        WHEN i.status = 'available' THEN 'Co san'
        WHEN i.status = 'reserved' THEN 'Da giu'
        WHEN i.status = 'sold' THEN 'Da ban'
        WHEN i.status = 'damaged' THEN 'Hu hong'
        WHEN i.status = 'disposed' THEN 'Da xu ly'
    END AS status_text
FROM inventory i
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON i.category_id = c.category_id;

CREATE OR REPLACE VIEW v_saleable_items AS
SELECT 
    i.*,
    c.name AS category_name,
    c.icon AS category_icon,
    d.item_name AS donation_name,
    u.name AS donor_name,
    CASE 
        WHEN i.price_type = 'free' THEN 'Mien phi'
        WHEN i.price_type = 'cheap' THEN 'Gia re'
        WHEN i.price_type = 'normal' THEN 'Gia thuong'
    END AS price_type_text,
    CASE 
        WHEN i.status = 'available' THEN 'Co san'
        WHEN i.status = 'reserved' THEN 'Da giu'
        WHEN i.status = 'sold' THEN 'Da ban'
    END AS status_text
FROM inventory i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
WHERE i.is_for_sale = TRUE AND i.status IN ('available', 'reserved');

CREATE OR REPLACE VIEW v_order_details AS
SELECT 
    o.*,
    u.name AS customer_name,
    u.email AS customer_email,
    u.phone AS customer_phone,
    COUNT(oi.order_item_id) AS total_items_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
GROUP BY o.order_id;

CREATE OR REPLACE VIEW v_campaign_details AS
SELECT 
    c.*,
    u.name AS creator_name,
    u.email AS creator_email,
    COUNT(DISTINCT cv.volunteer_id) AS volunteer_count,
    COUNT(DISTINCT cd.donation_id) AS donation_count,
    SUM(ci.quantity_needed) AS total_items_needed,
    SUM(ci.quantity_received) AS total_items_received,
    CASE 
        WHEN c.status = 'draft' THEN 'Nhap'
        WHEN c.status = 'pending' THEN 'Cho duyet'
        WHEN c.status = 'active' THEN 'Dang hoat dong'
        WHEN c.status = 'paused' THEN 'Tam dung'
        WHEN c.status = 'completed' THEN 'Hoan thanh'
        WHEN c.status = 'cancelled' THEN 'Da huy'
    END AS status_text,
    DATEDIFF(c.end_date, CURDATE()) AS days_remaining,
    CASE 
        WHEN SUM(ci.quantity_needed) > 0 
        THEN ROUND((SUM(ci.quantity_received) / SUM(ci.quantity_needed)) * 100, 2)
        ELSE 0 
    END AS completion_percentage
FROM campaigns c
LEFT JOIN users u ON c.created_by = u.user_id
LEFT JOIN campaign_volunteers cv ON c.campaign_id = cv.campaign_id AND cv.status = 'approved'
LEFT JOIN campaign_donations cd ON c.campaign_id = cd.campaign_id
LEFT JOIN campaign_items ci ON c.campaign_id = ci.campaign_id
GROUP BY c.campaign_id;

CREATE OR REPLACE VIEW v_campaign_items_progress AS
SELECT 
    ci.*,
    c.name AS campaign_name,
    c.status AS campaign_status,
    cat.name AS category_name,
    ci.quantity_received AS received,
    ci.quantity_needed AS needed,
    (ci.quantity_needed - ci.quantity_received) AS remaining,
    CASE 
        WHEN ci.quantity_needed > 0 
        THEN ROUND((ci.quantity_received / ci.quantity_needed) * 100, 2)
        ELSE 0 
    END AS progress_percentage,
    CASE 
        WHEN ci.quantity_received >= ci.quantity_needed THEN 'Du'
        WHEN ci.quantity_received > 0 THEN 'Dang thieu'
        ELSE 'Chua co'
    END AS status_text
FROM campaign_items ci
LEFT JOIN campaigns c ON ci.campaign_id = c.campaign_id
LEFT JOIN categories cat ON ci.category_id = cat.category_id;

-- Triggers
DELIMITER $$
DROP TRIGGER IF EXISTS after_donation_approved$$
CREATE TRIGGER after_donation_approved
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
        IF NOT EXISTS (SELECT 1 FROM inventory WHERE donation_id = NEW.donation_id) THEN
            INSERT INTO inventory (
                donation_id, name, description, category_id, quantity, unit,
                condition_status, estimated_value, actual_value, images,
                status, price_type, sale_price, is_for_sale, created_at
            ) VALUES (
                NEW.donation_id,
                NEW.item_name,
                NEW.description,
                NEW.category_id,
                NEW.quantity,
                NEW.unit,
                NEW.condition_status,
                NEW.estimated_value,
                NEW.estimated_value,
                NEW.images,
                'available',
                'free',
                0,
                TRUE,
                NOW()
            );
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS before_order_insert$$
CREATE TRIGGER before_order_insert 
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    DECLARE order_prefix VARCHAR(10);
    
    SELECT setting_value INTO order_prefix 
    FROM system_settings 
    WHERE setting_key = 'order_prefix' 
    LIMIT 1;
    
    IF order_prefix IS NULL THEN
        SET order_prefix = 'GW';
    END IF;
    
    SET next_id = COALESCE((SELECT MAX(order_id) + 1 FROM orders), 1);
    SET NEW.order_number = COALESCE(NEW.order_number, CONCAT(order_prefix, DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(next_id, 4, '0')));
END$$

DROP TRIGGER IF EXISTS update_order_status_history$$
CREATE TRIGGER update_order_status_history 
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO order_status_history (order_id, old_status, new_status, note)
        VALUES (NEW.order_id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$
DELIMITER ;

COMMIT;
