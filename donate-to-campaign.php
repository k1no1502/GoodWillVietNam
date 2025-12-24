<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$campaign_id = (int)($_GET['campaign_id'] ?? 0);

if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign
$campaign = Database::fetch(
    "SELECT * FROM campaigns WHERE campaign_id = ? AND status = 'active'",
    [$campaign_id]
);

if (!$campaign) {
    setFlashMessage('error', 'Chiến dịch không tồn tại hoặc đã kết thúc.');
    header('Location: campaigns.php');
    exit();
}

// Get campaign items
$items = Database::fetchAll(
    "SELECT * FROM v_campaign_items_progress WHERE campaign_id = ? ORDER BY progress_percentage ASC",
    [$campaign_id]
);

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$success = '';
$error = '';
$completeMessage = "\u{0110}\u{00E3} \u{0111}\u{1EE7} quy\u{00EA}n g\u{00F3}p";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donate_type = $_POST['donate_type'] ?? 'custom'; // campaign_item | custom
    $item_name = sanitize($_POST['item_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = sanitize($_POST['unit'] ?? 'cÃƒÂ¡i');
    $condition_status = sanitize($_POST['condition_status'] ?? 'good');
    $campaign_item_id = (int)($_POST['campaign_item_id'] ?? 0);

    // NÃ¡ÂºÂ¿u chÃ¡Â»Ân vÃ¡ÂºÂ­t phÃ¡ÂºÂ©m chiÃ¡ÂºÂ¿n dÃ¡Â»?ch, lÃ¡ÂºÂ¥y dÃ¡Â»Â¯ liÃ¡Â»?u gÃ¡Â»?c tÃ¡Â»Â« DB Ã„?Ã¡Â»? trÃƒÂ¡nh nhÃ¡ÂºÂ­p sai
    if ($donate_type === 'campaign_item') {
        if ($campaign_item_id <= 0) {
            $error = 'Vui lòng chọn vật phẩm cần quyên góp trong chiến dịch';
        } else {
            $campaignItem = Database::fetch(
                "SELECT item_name, category_id, unit, description 
                 FROM campaign_items 
                 WHERE item_id = ? AND campaign_id = ?",
                [$campaign_item_id, $campaign_id]
            );
            if (!$campaignItem) {
                $error = 'Vật phẩm chiến dịch không tồn tại.';
            } else {
                $item_name = $campaignItem['item_name'];
                $category_id = (int)($campaignItem['category_id'] ?? 0);
                $unit = $campaignItem['unit'] ?: 'cái';
                $description = $campaignItem['description'] ?? '';
            }
        }
    }

    // ChuÃ¡ÂºÂ©n hÃƒÂ³a dÃ¡Â»Â¯ liÃ¡Â»?u trÃƒÂ¡nh lÃ¡Â»?i FK/NOT NULL
    $category_id = $category_id > 0 ? $category_id : null;
    $unit = $unit ?: 'cai';
    
    if (!$error && (empty($item_name) || $quantity <= 0)) {
        $error = 'Vui lÃƒÂ²ng nhÃ¡ÂºÂ­p Ã„?Ã¡ÂºÂ§y Ã„?Ã¡Â»Â§ thÃƒÂ´ng tin.';
    } 
    
    if (!$error) {
        try {
            Database::beginTransaction();
            
            // Handle image upload
            $images = [];
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $uploadDir = 'uploads/donations/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];
                        
                        $uploadResult = uploadFile($file, $uploadDir);
                        if ($uploadResult['success']) {
                            $images[] = $uploadResult['filename'];
                        }
                    }
                }
            }
            
            // Insert donation
            $sql = "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, 
                    condition_status, images, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())";
            
            Database::execute($sql, [
                $_SESSION['user_id'],
                $item_name,
                $description,
                $category_id,
                $quantity,
                $unit,
                $condition_status,
                json_encode($images)
            ]);
            
            $donation_id = Database::lastInsertId();
            
            // Link donation to campaign
            $sql = "INSERT INTO campaign_donations (campaign_id, donation_id, campaign_item_id, quantity_contributed, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            Database::execute($sql, [
                $campaign_id,
                $donation_id,
                $campaign_item_id > 0 ? $campaign_item_id : null,
                $quantity
            ]);

            // Update requested item progress if linked
            if ($campaign_item_id > 0) {
                Database::execute(
                    "UPDATE campaign_items 
                     SET quantity_received = GREATEST(quantity_received + ?, 0)
                     WHERE item_id = ? AND campaign_id = ?",
                    [$quantity, $campaign_item_id, $campaign_id]
                );
            }

            // Sync campaign current_items with sum of received quantities
            Database::execute(
                "UPDATE campaigns c
                 SET current_items = (
                     SELECT COALESCE(SUM(quantity_received), 0)
                     FROM campaign_items
                     WHERE campaign_id = c.campaign_id
                 )
                 WHERE c.campaign_id = ?",
                [$campaign_id]
            );
            
            // Add to inventory
            $estimatedValue = isset($_POST['estimated_value']) ? (float)$_POST['estimated_value'] : 0;
            if ($estimatedValue <= 0) {
                $priceType = 'free';
                $salePrice = 0;
            } elseif ($estimatedValue < 100000) {
                $priceType = 'cheap';
                $salePrice = $estimatedValue;
            } else {
                $priceType = 'normal';
                $salePrice = $estimatedValue;
            }

            Database::execute(
                "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                 condition_status, images, status, price_type, sale_price, is_for_sale, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, TRUE, NOW())",
                [
                    $donation_id,
                    $item_name,
                    $description,
                    $category_id,
                    $quantity,
                    $unit,
                    $condition_status,
                    json_encode($images),
                    $priceType,
                    $salePrice
                ]
            );
            
            Database::commit();
            
            logActivity($_SESSION['user_id'], 'donate_to_campaign', "Donated to campaign #$campaign_id");
            
            $success = 'Quyên góp thành công! Cảm ơn bạn đã đã góp cho chiến dịch.';
            
            // Redirect after 2 seconds
            header("refresh:2;url=campaign-detail.php?id=$campaign_id");
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Donate to campaign error: " . $e->getMessage());
            $error = 'CÃƒÂ³ lÃ¡Â»?i xÃ¡ÂºÂ£y ra. Vui lÃƒÂ²ng thÃ¡Â»Â­ lÃ¡ÂºÂ¡i.';
        }
    }
}

$pageTitle = "Quyên góp cho chiến dịch"; 
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Campaign Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        <?php echo htmlspecialchars($campaign['name']); ?>
                    </h4>
                    <p class="text-muted"><?php echo htmlspecialchars(substr($campaign['description'], 0, 200)); ?>...</p>
                    <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>Xem chi tiết chiến dịch</a>
                </div>
            </div>

            <!-- Donation Form -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-success text-white">
                    <h2 class="card-title mb-0">
                        <i class="bi bi-gift me-2"></i>Quyê góp cho chiến dịch
                    </h2>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                                        <!-- Campaign Items Need -->
                    <?php if (!empty($items)): ?>
                        <div class="alert alert-info">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-info-circle me-2"></i>Chiến dịch cần:
                            </h6>
                            <div class="row">
                                <?php foreach (array_slice($items, 0, 4) as $item): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <i class="bi bi-check2-circle me-1"></i>
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                            </span>
                                            <?php
                                                $remaining = (int)$item['remaining'];
                                                $remainingText = $remaining > 0
                                                    ? $remaining . ' ' . $item['unit']
                                                    : $completeMessage;
                                                $badgeClass = $remaining > 0 ? 'bg-warning text-dark' : 'bg-success';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>" data-complete="<?php echo $remaining > 0 ? '0' : '1'; ?>">
                                                <?php echo htmlspecialchars($remainingText, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php $defaultType = !empty($items) ? 'campaign_item' : 'custom'; ?>
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chọn cách quyên góp</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <?php if (!empty($items)): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="donate_type" id="donateTypeCampaign" value="campaign_item" <?php echo $defaultType === 'campaign_item' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="donateTypeCampaign">Theo vật phẩm chiến dịch cần</label>
                                    </div>
                                <?php endif; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="donate_type" id="donateTypeCustom" value="custom" <?php echo $defaultType === 'custom' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="donateTypeCustom">Quyên góp tự do</label>
                                </div>
                            </div>
                        </div>

                        <div id="campaignItemSection" class="<?php echo $defaultType === 'campaign_item' ? '' : 'd-none'; ?>">
                            <?php if (!empty($items)): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chọn vật phẩm chiến dịch đang cần</label>
                                    <select class="form-select" id="quickSelect" name="campaign_item_id">
                                        <option value="0">-- Chọn vật phẩm --</option>
                                        <?php foreach ($items as $item): ?>
                                            <?php $selectRemaining = (int)$item['remaining']; ?>
                                            <option value="<?php echo $item['item_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                    data-category="<?php echo $item['category_id']; ?>"
                                                    data-unit="<?php echo $item['unit']; ?>"
                                                    data-complete-option="<?php echo $selectRemaining > 0 ? '0' : '1'; ?>">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                                (
                                                <?php if ($selectRemaining > 0): ?>
                                                    <?php echo htmlspecialchars("Cần: {$selectRemaining} {$item['unit']}", ENT_QUOTES, 'UTF-8'); ?>
                                                <?php else: ?>
                                                    <?php echo $completeMessage; ?>
                                                <?php endif; ?>
                                                )
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="customItemSection" class="<?php echo $defaultType === 'custom' ? '' : 'd-none'; ?>">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="item_name" class="form-label">Tên vật phẩm</label>
                                    <input type="text" class="form-control" id="item_name" name="item_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="category_id" class="form-label">Danh mục *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Chọn danh mục</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['category_id']; ?>">
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Mô tả chi tiết</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="unit" class="form-label">Đơn vị</label>
                                    <select class="form-select" id="unit" name="unit" data-default-unit="cai">
                                        <option value="cai">Cái</option>
                                        <option value="bo">Bộ</option>
                                        <option value="kg">Kg</option>
                                        <option value="cuon">Cuộn</option>
                                        <option value="thang">Tháng</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="condition_status" class="form-label">Tình trạng</label>
                                    <select class="form-select" id="condition_status" name="condition_status">
                                        <option value="new">Mới</option>
                                        <option value="like_new">Như mới</option>
                                        <option value="good" selected>Tốt</option>
                                        <option value="fair">Khá</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="images" class="form-label">Hình ảnh (tùy chọn)</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                            </div>
                        </div>

                        <div class="row" id="quantityRow">
                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">Số lượng muốn quyên góp *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-gift me-2"></i>Gửi quyên góp
                            </button>
                            <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div><?php
$additionalScripts = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    function fixEncoding(value) {
        try {
            return decodeURIComponent(escape(value));
        } catch (err) {
            return value;
        }
    }

    const textWalker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
    let currentNode;
    while ((currentNode = textWalker.nextNode())) {
        const original = currentNode.nodeValue;
        const fixed = fixEncoding(original);
        if (fixed !== original) {
            currentNode.nodeValue = fixed;
        }
    }

    const quickSelect = document.getElementById('quickSelect');
    const itemName = document.getElementById('item_name');
    const categorySelect = document.getElementById('category_id');
    const unitInput = document.getElementById('unit');
    const campaignSection = document.getElementById('campaignItemSection');
    const customSection = document.getElementById('customItemSection');
    const typeRadios = document.querySelectorAll('input[name=\"donate_type\"]');

    function fillFromOption(option) {
        if (!option || option.value === '0') {
            itemName.value = '';
            categorySelect.value = '';
            unitInput.value = unitInput.dataset.defaultUnit || 'cai';
            return;
        }
        itemName.value = option.dataset.name || '';
        categorySelect.value = option.dataset.category || '';
        unitInput.value = option.dataset.unit || unitInput.dataset.defaultUnit || 'cai';
    }

    function toggleType(type) {
        const isCampaign = type === 'campaign_item';
        if (campaignSection) campaignSection.classList.toggle('d-none', !isCampaign);
        if (customSection) customSection.classList.toggle('d-none', isCampaign);

        if (quickSelect) {
            quickSelect.disabled = !isCampaign;
            if (isCampaign) {
                if (!quickSelect.value || quickSelect.value === '0') {
                    const opts = quickSelect.querySelectorAll('option[value]');
                    if (opts.length > 1) {
                        quickSelect.value = opts[1].value;
                    }
                }
                itemName.readOnly = true;
                categorySelect.disabled = true;
                fillFromOption(quickSelect.selectedOptions[0]);
            } else {
                quickSelect.value = '0';
                itemName.readOnly = false;
                categorySelect.disabled = false;
                itemName.value = '';
                categorySelect.value = '';
                unitInput.value = unitInput.dataset.defaultUnit || 'cai';
            }
        }
    }

    if (quickSelect) {
        quickSelect.addEventListener('change', function() {
            fillFromOption(quickSelect.selectedOptions[0]);
        });
    }

    typeRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleType(this.value);
        });
    });

    const defaultType = Array.from(typeRadios).find(r => r.checked)?.value || 'custom';
    toggleType(defaultType);
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
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
</script>
";

include 'includes/footer.php';
?>

















