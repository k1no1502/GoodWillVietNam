<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($user_id > 0) {
        try {
            if ($action === 'update_status') {
                $status = $_POST['status'];
                $allowedStatuses = ['active', 'inactive', 'banned'];

                if (!in_array($status, $allowedStatuses, true)) {
                    throw new Exception('Trạng thái không hợp lệ.');
                }

                Database::execute(
                    "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?",
                    [$status, $user_id]
                );
                setFlashMessage('success', 'Đã cập nhật trạng thái người dùng.');
                logActivity($_SESSION['user_id'], 'update_user_status', "Updated user #$user_id status to $status");
                
            } elseif ($action === 'update_role') {
                $role_id = (int)$_POST['role_id'];
                Database::execute(
                    "UPDATE users SET role_id = ?, updated_at = NOW() WHERE user_id = ?",
                    [$role_id, $user_id]
                );
                setFlashMessage('success', 'Đã cập nhật vai trò người dùng.');
                logActivity($_SESSION['user_id'], 'update_user_role', "Updated user #$user_id role to $role_id");
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get filters
$status = $_GET['status'] ?? '';
$role_id = (int)($_GET['role'] ?? 0);
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND u.status = ?";
    $params[] = $status;
}

if ($role_id > 0) {
    $where .= " AND u.role_id = ?";
    $params[] = $role_id;
}

if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get roles
$roles = Database::fetchAll("SELECT * FROM roles ORDER BY role_id");

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM users u WHERE $where";
$totalUsers = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalUsers / $per_page);

// Get users
$sql = "SELECT u.*, r.role_name 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE $where
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$users = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM users")['count'],
    'active' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
    'inactive' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")['count'],
    'banned' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'banned'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">    <style>
        .users-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .users-action-btn {
            width: 44px;
            height: 38px;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .users-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .users-action-btn:hover {
            transform: translateY(-1px);
        }
        .users-action-btn.edit { background-color: #0d6efd; }
        .users-action-btn i { pointer-events: none; }
        .modal-action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
        }
        .modal-action-btn {
            width: 48px;
            height: 40px;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .modal-action-btn:hover {
            transform: translateY(-1px);
        }
        .modal-action-btn.cancel { background-color: #6c757d; }
        .modal-action-btn.role { background-color: #0d6efd; }
        .modal-action-btn.role { box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.18); }
        .modal-action-btn.status { background-color: #f1b600; color: #fff; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-people me-2"></i>Quản lý người dùng</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Tổng người dùng</h6>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Đang hoạt động</h6>
                                <h3><?php echo number_format($stats['active']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Không hoạt động</h6>
                                <h3><?php echo number_format($stats['inactive']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6>Đã khóa</h6>
                                <h3><?php echo number_format($stats['banned']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên, email, SĐT...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                    <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>Đã khóa</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vai trò</label>
                                <select class="form-select" name="role">
                                    <option value="">Tất cả</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>" 
                                                <?php echo $role_id == $role['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người dùng</th>
                                        <th>Email</th>
                                        <th>SĐT</th>
                                        <th>Vai trò</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày đăng ký</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có người dùng nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <?php if ($user['avatar']): ?>
                                                        <br><img src="../uploads/avatars/<?php echo $user['avatar']; ?>" 
                                                                 class="rounded-circle" 
                                                                 width="30" 
                                                                 height="30" 
                                                                 alt="Avatar">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'active' => ['class' => 'success', 'text' => 'Hoạt động'],
                                                        'inactive' => ['class' => 'warning', 'text' => 'Không hoạt động'],
                                                        'banned' => ['class' => 'danger', 'text' => 'Đã khóa']
                                                    ];
                                                    $st = $statusMap[$user['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at']); ?></td>
                                                <td>
                                                    <div class="users-actions">
                                                        <button type="button" 
                                                                class="users-action-btn edit" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $user['user_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $user['user_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa người dùng</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Vai trò *</label>
                                                                    <select class="form-select" name="role_id" required>
                                                                        <?php foreach ($roles as $role): ?>
                                                                            <option value="<?php echo $role['role_id']; ?>" 
                                                                                    <?php echo $user['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Trạng thái *</label>
                                                                    <select class="form-select" name="status" required>
                                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                                                        <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Đã khóa</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" name="action" value="update_role" class="modal-action-btn role" title="Cáº­p nháº­t vai trá»">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                    <button type="submit" name="action" value="update_status" class="modal-action-btn status" title="Cáº­p nháº­t tráº¡ng thÃ¡i">
                                                                        <i class="bi bi-eye"></i>
                                                                    </button>
                                                                </div>
                                                            </div></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
