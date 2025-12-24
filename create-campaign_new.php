<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$success = '';
$error = '';

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$pageTitle = "Tạo chiến dịch";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $target_items = (int)($_POST['target_items'] ?? 0);
    
    // Validate
    if (empty($name) || empty($description) || empty($start_date) || empty($end_date)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif ($target_items <= 0) {
        $error = 'Mục tiêu vật phẩm phải lớn hơn 0.';
    } elseif (strtotime($start_date) < time()) {
        $error = 'Ngày bắt đầu phải từ hôm nay trở đi.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = 'Ngày kết thúc phải sau ngày bắt đầu.';
    } else {
        try {
            Database::beginTransaction();
            
            // Handle image upload
            $imagePath = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/campaigns/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $uploadResult = uploadFile($_FILES['image'], $uploadDir);
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['filename'];
                }
            }
            
            // Insert campaign
            $sql = "INSERT INTO campaigns (name, description, image, start_date, end_date, target_items, 
                    status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, NOW())";
            Database::execute($sql, [
                $name,
                $description,
                $imagePath,
                $start_date,
                $end_date,
                $target_items,
                $_SESSION['user_id']
            ]);
            
            $campaign_id = Database::lastInsertId();
            
            // Insert campaign items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['name']) && !empty($item['quantity'])) {
                        $sql = "INSERT INTO campaign_items (campaign_id, item_name, category_id, quantity_needed, description) 
                                VALUES (?, ?, ?, ?, ?)";
                        Database::execute($sql, [
                            $campaign_id,
                            sanitize($item['name']),
                            (int)($item['category'] ?? 0),
                            (int)$item['quantity'],
                            sanitize($item['description'] ?? '')
                        ]);
                    }
                }
            }
            
            Database::commit();
            
            $success = 'Chiến dịch đã được tạo thành công! Đang chờ phê duyệt từ quản trị viên.';
            
            // Clear form
            $_POST = [];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Create campaign error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi tạo chiến dịch. Vui lòng thử lại.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-success text-white">
                    <h2 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Tạo chiến dịch mới
                    </h2>
                    <p class="mb-0 mt-2">Tạo chiến dịch thiện nguyện để kêu gọi sự hỗ trợ từ cộng đồng</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <!-- Basic Information -->
                        <div class="mb-4">
                            <h5 class="text-success mb-3">Thông tin cơ bản</h5>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Tên chiến dịch *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Ví dụ: Hỗ trợ trẻ em vùng cao"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập tên chiến dịch.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Mô tả chi tiết *</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="4" 
                                          placeholder="Mô tả mục đích, đối tượng hưởng lợi, và cách thức thực hiện chiến dịch..."
                                          required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="invalid-feedback">
                                    Vui lòng nhập mô tả chi tiết.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Hình ảnh chiến dịch</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="image" 
                                       name="image" 
                                       accept="image/*">
                                <div class="form-text">Chọn hình ảnh đại diện cho chiến dịch (JPG, PNG, GIF)</div>
                            </div>
                        </div>

                        <!-- Campaign Details -->
                        <div class="mb-4">
                            <h5 class="text-success mb-3">Chi tiết chiến dịch</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Ngày bắt đầu *</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           required>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn ngày bắt đầu.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Ngày kết thúc *</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           required>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn ngày kết thúc.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="target_items" class="form-label">Mục tiêu số lượng vật phẩm *</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="target_items" 
                                       name="target_items" 
                                       value="<?php echo htmlspecialchars($_POST['target_items'] ?? ''); ?>"
                                       min="1" 
                                       placeholder="Ví dụ: 100"
                                       required>
                                <div class="invalid-feedback">
                                    Mục tiêu phải lớn hơn 0.
                                </div>
                            </div>
                        </div>

                        <!-- Required Items -->
                        <div class="mb-4">
                            <h5 class="text-success mb-3">Vật phẩm cần thiết</h5>
                            <p class="text-muted">Liệt kê các vật phẩm cụ thể mà chiến dịch cần</p>
                            
                            <div id="items-container">
                                <div class="item-row mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Tên vật phẩm</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="items[0][name]" 
                                                   placeholder="Ví dụ: Áo ấm">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Danh mục</label>
                                            <select class="form-select" name="items[0][category]">
                                                <option value="">Chọn danh mục</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['category_id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Số lượng</label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="items[0][quantity]" 
                                                   min="1" 
                                                   placeholder="10">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">Mô tả</label>
                                            <textarea class="form-control" 
                                                      name="items[0][description]" 
                                                      rows="2" 
                                                      placeholder="Mô tả chi tiết về vật phẩm..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-success" id="add-item">
                                <i class="bi bi-plus-circle me-1"></i>Thêm vật phẩm
                            </button>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Tạo chiến dịch
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Add/Remove items
let itemIndex = 1;

document.getElementById('add-item').addEventListener('click', function() {
    const container = document.getElementById('items-container');
    const newItem = document.createElement('div');
    newItem.className = 'item-row mb-3 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Tên vật phẩm</label>
                <input type="text" class="form-control" name="items[${itemIndex}][name]" placeholder="Ví dụ: Áo ấm">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Danh mục</label>
                <select class="form-select" name="items[${itemIndex}][category]">
                    <option value="">Chọn danh mục</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Số lượng</label>
                <input type="number" class="form-control" name="items[${itemIndex}][quantity]" min="1" placeholder="10">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" name="items[${itemIndex}][description]" rows="2" placeholder="Mô tả chi tiết về vật phẩm..."></textarea>
            </div>
        </div>
    `;
    
    container.appendChild(newItem);
    itemIndex++;
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        e.target.closest('.item-row').remove();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
