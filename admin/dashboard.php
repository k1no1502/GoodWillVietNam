<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$pageTitle = 'Dashboard - Quản trị';

// Get statistics
$stats = getStatistics();
$donationTrend = getDonationTrendData();
$categoryDistribution = getCategoryDistributionData();

// Get recent activities
$recentActivities = Database::fetchAll("
    SELECT al.*, u.name as user_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Get recent donations
$recentDonations = Database::fetchAll("
    SELECT d.*, u.name as donor_name, c.name as category_name
    FROM donations d 
    LEFT JOIN users u ON d.user_id = u.user_id 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    ORDER BY d.created_at DESC 
    LIMIT 5
");

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Goodwill Vietnam</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard_export.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download me-1"></i>Xuất Excel
                            </a>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus me-1"></i>Thêm mới
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Tổng người dùng
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['users']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people-fill text-primary fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Quyên góp
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['donations']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-heart-fill text-success fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Vật phẩm
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['items']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-box-seam text-info fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Chiến dịch
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['campaigns']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-trophy-fill text-warning fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Thống kê quyên góp theo tháng</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="donationChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Phân bố danh mục</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Donations -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Hoạt động gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'Hệ thống'); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo formatDate($activity['created_at'], 'H:i d/m'); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quyên góp gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentDonations as $donation): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo htmlspecialchars($donation['item_name']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($donation['donor_name']); ?> - 
                                                    <?php echo htmlspecialchars($donation['category_name'] ?? 'Không xác định'); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $donation['status'] === 'pending' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($donation['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    <script>
        // Donation Chart
        const donationTrend = <?php echo json_encode($donationTrend, JSON_UNESCAPED_UNICODE); ?>;
        const donationLabels = donationTrend.map(item => item.label);
        const donationValues = donationTrend.map(item => item.total);
        const donationCtx = document.getElementById('donationChart').getContext('2d');
        new Chart(donationCtx, {
            type: 'line',
            data: {
                labels: donationLabels,
                datasets: [{
                    label: 'So quyen gop',
                    data: donationValues,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Category Chart
        const categoryData = <?php echo json_encode($categoryDistribution, JSON_UNESCAPED_UNICODE); ?>;
        const categoryLabels = categoryData.map(item => item.label);
        const categoryValues = categoryData.map(item => item.total);
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#8BC34A'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
