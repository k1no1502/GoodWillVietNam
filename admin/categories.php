<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $action = $_POST['action'];
    
    try {
        if ($action === 'create') {
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) {
                throw new Exception('Tên danh mục không được để trống.');
            }
            
            Database::execute(
                "INSERT INTO categories (name, description, parent_id, sort_order, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$name, $description, $parent_id, $sort_order, $status]
            );
            setFlashMessage('success', 'Đã tạo danh mục mới.');
            logActivity($_SESSION['user_id'], 'create_category', "Created category: $name");
            
        } elseif ($action === 'update') {
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) {
                throw new Exception('Tên danh mục không được để trống.');
            }
            
            Database::execute(
                "UPDATE categories SET name = ?, description = ?, parent_id = ?, sort_order = ?, status = ?, updated_at = NOW() 
                 WHERE category_id = ?",
                [$name, $description, $parent_id, $sort_order, $status, $category_id]
            );
            setFlashMessage('success', 'Đã cập nhật danh mục.');
            logActivity($_SESSION['user_id'], 'update_category', "Updated category #$category_id");
            
        } elseif ($action === 'delete') {
            // Check if category has children or items
            $hasChildren = Database::fetch("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?", [$category_id])['count'];
            $hasItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE category_id = ?", [$category_id])['count'];
            
            if ($hasChildren > 0) {
                throw new Exception('Không thể xóa danh mục có danh mục con.');
            }
            if ($hasItems > 0) {
                throw new Exception('Không thể xóa danh mục đang có vật phẩm.');
            }
            
            Database::execute("DELETE FROM categories WHERE category_id = ?", [$category_id]);
            setFlashMessage('success', 'Đã xóa danh mục.');
            logActivity($_SESSION['user_id'], 'delete_category', "Deleted category #$category_id");
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
    }
    
    header('Location: categories.php');
    exit();
}

// Get all categories
$categories = Database::fetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.category_id) as children_count,
           (SELECT COUNT(*) FROM inventory WHERE category_id = c.category_id) as items_count
    FROM categories c
    ORDER BY c.sort_order, c.name
");

// Get parent categories for dropdown
$parentCategories = Database::fetchAll("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    ORDER BY sort_order, name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">    <style>
        .categories-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .categories-actions form {
            margin: 0;
        }
        .categories-action-btn {
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
        .categories-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .categories-action-btn:hover {
            transform: translateY(-1px);
        }
        .categories-action-btn.edit { background-color: #0d6efd; }
        .categories-action-btn.delete { background-color: #dc3545; }
        .categories-action-btn i { pointer-events: none; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-tags me-2"></i>Quản lý danh mục</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="bi bi-plus-circle me-1"></i>Thêm danh mục
                    </button>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Categories table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên danh mục</th>
                                        <th>Mô tả</th>
                                        <th>Danh mục cha</th>
                                        <th>Thứ tự</th>
                                        <th>Số vật phẩm</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có danh mục nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $category['category_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    <?php if ($category['children_count'] > 0): ?>
                                                        <br><small class="text-info">
                                                            <i class="bi bi-folder"></i> <?php echo $category['children_count']; ?> danh mục con
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)); ?>...</td>
                                                <td>
                                                    <?php 
                                                    if ($category['parent_id']): 
                                                        $parent = Database::fetch("SELECT name FROM categories WHERE category_id = ?", [$category['parent_id']]);
                                                        echo htmlspecialchars($parent['name'] ?? 'N/A');
                                                    else:
                                                        echo '<span class="text-muted">Danh mục gốc</span>';
                                                    endif;
                                                    ?>
                                                </td>
                                                <td><?php echo $category['sort_order']; ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $category['items_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $category['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                                                                        <div class="categories-actions">
                                                        <button type="button" 
                                                                class="categories-action-btn edit" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo ['category_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if (['items_count'] == 0 && ['children_count'] == 0): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này?');">
                                                                <input type="hidden" name="category_id" value="<?php echo ['category_id']; ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <button type="submit" class="categories-action-btn delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $category['category_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa danh mục</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                                                <input type="hidden" name="action" value="update">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên danh mục *</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           name="name" 
                                                                           value="<?php echo htmlspecialchars($category['name']); ?>" 
                                                                           required>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mô tả</label>
                                                                    <textarea class="form-control" 
                                                                              name="description" 
                                                                              rows="3"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Danh mục cha</label>
                                                                    <select class="form-select" name="parent_id">
                                                                        <option value="">Không có (Danh mục gốc)</option>
                                                                        <?php foreach ($parentCategories as $parent): ?>
                                                                            <?php if ($parent['category_id'] != $category['category_id']): ?>
                                                                                <option value="<?php echo $parent['category_id']; ?>" 
                                                                                        <?php echo $category['parent_id'] == $parent['category_id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($parent['name']); ?>
                                                                                </option>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Thứ tự</label>
                                                                        <input type="number" 
                                                                               class="form-control" 
                                                                               name="sort_order" 
                                                                               value="<?php echo $category['sort_order']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Trạng thái</label>
                                                                        <select class="form-select" name="status">
                                                                            <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                                                            <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn btn-primary">Cập nhật</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Thêm danh mục mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên danh mục *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Danh mục cha</label>
                            <select class="form-select" name="parent_id">
                                <option value="">Không có (Danh mục gốc)</option>
                                <?php foreach ($parentCategories as $parent): ?>
                                    <option value="<?php echo $parent['category_id']; ?>">
                                        <?php echo htmlspecialchars($parent['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" class="form-control" name="sort_order" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Tạo mới</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



