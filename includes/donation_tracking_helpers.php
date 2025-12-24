<?php
if (!function_exists('getDonationTrackingTemplates')) {
    /**
     * Mẫu các bước theo dõi mặc định cho quyên góp.
     *
     * @return array<string, array<string, mixed>>
     */
    function getDonationTrackingTemplates(): array
    {
        return [
            'submitted' => [
                'label'          => 'Đã gửi yêu cầu',
                'description'    => 'Người dùng đã tạo đơn quyên góp và gửi lên hệ thống.',
                'order'          => 1,
                'default_status' => 'completed'
            ],
            'review' => [
                'label'          => 'Đang chờ duyệt',
                'description'    => 'Đơn quyên góp đang được ban quản trị xem xét.',
                'order'          => 2,
                'default_status' => 'pending'
            ],
            'approved' => [
                'label'          => 'Đã duyệt & nhập kho',
                'description'    => 'Vật phẩm đã được tiếp nhận và cập nhật vào kho.',
                'order'          => 3,
                'default_status' => 'pending'
            ],
            'distributed' => [
                'label'          => 'Đã phân phối',
                'description'    => 'Vật phẩm đã được phân phối tới chiến dịch hoặc người nhận phù hợp.',
                'order'          => 4,
                'default_status' => 'pending'
            ]
        ];
    }
}

if (!function_exists('ensureDonationTrackingTable')) {
    /**
     * Đảm bảo bảng lưu hành trình quyên góp tồn tại.
     */
    function ensureDonationTrackingTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS donation_tracking_steps (
                id INT PRIMARY KEY AUTO_INCREMENT,
                donation_id INT NOT NULL,
                step_key VARCHAR(50) NOT NULL,
                step_label VARCHAR(150) NOT NULL,
                description TEXT,
                step_order INT DEFAULT 0,
                step_status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
                event_time DATETIME NULL,
                note TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_donation_step (donation_id, step_key),
                FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        Database::execute($sql);
        $ensured = true;
    }
}

if (!function_exists('getDonationTrackingMap')) {
    /**
     * Lấy dữ liệu hành trình cho danh sách donation_id.
     *
     * @param array<int> $donationIds
     * @return array<int, array<string, array<string, mixed>>>
     */
    function getDonationTrackingMap(array $donationIds): array
    {
        if (empty($donationIds)) {
            return [];
        }

        ensureDonationTrackingTable();

        $placeholders = implode(',', array_fill(0, count($donationIds), '?'));
        $rows = Database::fetchAll(
            "SELECT * FROM donation_tracking_steps WHERE donation_id IN ($placeholders) ORDER BY step_order ASC, event_time ASC",
            $donationIds
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['donation_id']][$row['step_key']] = $row;
        }

        return $map;
    }
}
