<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $limit = (int)($_GET['limit'] ?? 6);
    $donations = getRecentDonations($limit);
    
    $html = '';
    
    if (empty($donations)) {
        $html = '<div class="col-12 text-center"><p class="text-muted">Chưa có quyên góp nào.</p></div>';
    } else {
        foreach ($donations as $donation) {
            $images = json_decode($donation['images'] ?? '[]', true);
            $imageUrl = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
            $statusClass = [
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
                'cancelled' => 'secondary'
            ][$donation['status']] ?? 'secondary';
            
            $statusText = [
                'pending' => 'Chờ duyệt',
                'approved' => 'Đã duyệt',
                'rejected' => 'Từ chối',
                'cancelled' => 'Đã hủy'
            ][$donation['status']] ?? 'Không xác định';
            
            $html .= '
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card donation-card h-100">
                        <div class="position-relative">
                            <img src="' . $imageUrl . '" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="' . htmlspecialchars($donation['item_name']) . '"
                                 onerror="this.src=\'uploads/donations/placeholder-default.svg\'">
                            <span class="badge bg-' . $statusClass . ' position-absolute top-0 end-0 m-2">
                                ' . $statusText . '
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title fw-bold">' . htmlspecialchars($donation['item_name']) . '</h6>
                            <p class="card-text text-muted small flex-grow-1">
                                ' . htmlspecialchars(substr($donation['description'] ?? '', 0, 100)) . '...
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-person me-1"></i>' . htmlspecialchars($donation['donor_name']) . '
                                </small>
                                <small class="text-muted">
                                    ' . formatDate($donation['created_at'], 'd/m/Y') . '
                                </small>
                            </div>
                        </div>
                    </div>
                </div>';
        }
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($donations)
    ]);
    
} catch (Exception $e) {
    error_log("Error getting recent donations: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi tải dữ liệu',
        'html' => '<div class="col-12 text-center"><p class="text-muted">Lỗi tải dữ liệu.</p></div>'
    ]);
}
?>
