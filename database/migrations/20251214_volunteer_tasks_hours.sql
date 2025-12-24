-- Volunteer tasks & hours tracking schema (foundation)

CREATE TABLE IF NOT EXISTS campaign_tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    task_type ENUM('on_site', 'online', 'support', 'logistics') DEFAULT 'support',
    required_volunteers INT DEFAULT 1,
    estimated_minutes INT DEFAULT 0,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    status ENUM('open', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_campaign_tasks_campaign (campaign_id),
    KEY idx_campaign_tasks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_task_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('leader', 'member') DEFAULT 'member',
    status ENUM('assigned', 'in_progress', 'completed', 'removed') DEFAULT 'assigned',
    assigned_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_task_assignments_task (task_id),
    KEY idx_task_assignments_user (user_id),
    KEY idx_task_assignments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_hours_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    task_id INT NULL,
    user_id INT NOT NULL,
    check_in DATETIME NULL,
    check_out DATETIME NULL,
    minutes INT DEFAULT 0,
    note TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hours_campaign (campaign_id),
    KEY idx_hours_user (user_id),
    KEY idx_hours_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_milestones (
    milestone_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    due_date DATE NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_milestones_campaign (campaign_id),
    KEY idx_milestones_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

