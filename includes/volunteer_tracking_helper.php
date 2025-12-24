<?php
if (!function_exists('ensureVolunteerTrackingSchema')) {
    function ensureVolunteerTrackingSchema() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            Database::execute("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log('ensureVolunteerTrackingSchema campaign_tasks failed: ' . $e->getMessage());
        }

        try {
            Database::execute("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log('ensureVolunteerTrackingSchema campaign_task_assignments failed: ' . $e->getMessage());
        }

        try {
            Database::execute("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log('ensureVolunteerTrackingSchema volunteer_hours_logs failed: ' . $e->getMessage());
        }

        try {
            Database::execute("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log('ensureVolunteerTrackingSchema campaign_milestones failed: ' . $e->getMessage());
        }
    }
}

ensureVolunteerTrackingSchema();

if (!function_exists('getCampaignOverviewStats')) {
    function getCampaignOverviewStats($campaignId) {
        $campaignId = (int)$campaignId;
        if ($campaignId <= 0) {
            return [
                'total_volunteers' => 0,
                'active_volunteers' => 0,
                'tasks_completed' => 0,
                'tasks_remaining' => 0,
                'total_minutes' => 0,
                'avg_minutes_per_volunteer' => 0,
                'activities_count' => 0
            ];
        }

        $totalVolunteersRow = Database::fetch(
            "SELECT COUNT(*) AS total FROM campaign_volunteers WHERE campaign_id = ?",
            [$campaignId]
        );
        $activeVolunteersRow = Database::fetch(
            "SELECT COUNT(*) AS total FROM campaign_volunteers WHERE campaign_id = ? AND status = 'approved'",
            [$campaignId]
        );

        $tasksCompletedRow = null;
        $tasksRemainingRow = null;
        $activitiesRow = null;
        try {
            $tasksCompletedRow = Database::fetch(
                "SELECT COUNT(*) AS total FROM campaign_tasks WHERE campaign_id = ? AND status = 'completed'",
                [$campaignId]
            );
            $tasksRemainingRow = Database::fetch(
                "SELECT COUNT(*) AS total FROM campaign_tasks WHERE campaign_id = ? AND status <> 'completed' AND status <> 'cancelled'",
                [$campaignId]
            );
            $activitiesRow = Database::fetch(
                "SELECT COUNT(*) AS total FROM campaign_milestones WHERE campaign_id = ?",
                [$campaignId]
            );
        } catch (Exception $e) {
            error_log('getCampaignOverviewStats tasks/milestones failed: ' . $e->getMessage());
        }

        $minutesRow = null;
        try {
            $minutesRow = Database::fetch(
                "SELECT COALESCE(SUM(minutes), 0) AS total FROM volunteer_hours_logs WHERE campaign_id = ? AND status = 'approved'",
                [$campaignId]
            );
        } catch (Exception $e) {
            error_log('getCampaignOverviewStats hours failed: ' . $e->getMessage());
        }

        $totalVolunteers = (int)($totalVolunteersRow['total'] ?? 0);
        $activeVolunteers = (int)($activeVolunteersRow['total'] ?? 0);
        $totalMinutes = (int)($minutesRow['total'] ?? 0);
        $avgMinutes = $activeVolunteers > 0 ? (int)round($totalMinutes / $activeVolunteers) : 0;

        return [
            'total_volunteers' => $totalVolunteers,
            'active_volunteers' => $activeVolunteers,
            'tasks_completed' => (int)($tasksCompletedRow['total'] ?? 0),
            'tasks_remaining' => (int)($tasksRemainingRow['total'] ?? 0),
            'total_minutes' => $totalMinutes,
            'avg_minutes_per_volunteer' => $avgMinutes,
            'activities_count' => (int)($activitiesRow['total'] ?? 0)
        ];
    }
}

if (!function_exists('getUserCampaignContribution')) {
    function getUserCampaignContribution($campaignId, $userId) {
        $campaignId = (int)$campaignId;
        $userId = (int)$userId;
        if ($campaignId <= 0 || $userId <= 0) {
            return [
                'minutes' => 0,
                'tasks_completed' => 0,
                'tasks_total' => 0
            ];
        }

        $minutesRow = null;
        try {
            $minutesRow = Database::fetch(
                "SELECT COALESCE(SUM(minutes), 0) AS total FROM volunteer_hours_logs WHERE campaign_id = ? AND user_id = ? AND status = 'approved'",
                [$campaignId, $userId]
            );
        } catch (Exception $e) {
            error_log('getUserCampaignContribution hours failed: ' . $e->getMessage());
        }

        $tasksCompletedRow = null;
        $tasksTotalRow = null;
        try {
            $tasksCompletedRow = Database::fetch(
                "SELECT COUNT(*) AS total
                 FROM campaign_task_assignments a
                 JOIN campaign_tasks t ON t.task_id = a.task_id
                 WHERE t.campaign_id = ? AND a.user_id = ? AND a.status = 'completed'",
                [$campaignId, $userId]
            );
            $tasksTotalRow = Database::fetch(
                "SELECT COUNT(*) AS total
                 FROM campaign_task_assignments a
                 JOIN campaign_tasks t ON t.task_id = a.task_id
                 WHERE t.campaign_id = ? AND a.user_id = ? AND a.status <> 'removed'",
                [$campaignId, $userId]
            );
        } catch (Exception $e) {
            error_log('getUserCampaignContribution tasks failed: ' . $e->getMessage());
        }

        return [
            'minutes' => (int)($minutesRow['total'] ?? 0),
            'tasks_completed' => (int)($tasksCompletedRow['total'] ?? 0),
            'tasks_total' => (int)($tasksTotalRow['total'] ?? 0)
        ];
    }
}
?>

