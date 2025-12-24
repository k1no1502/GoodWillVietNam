<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Liên hệ";
include 'includes/header.php';
?>

<section class="bg-gradient-primary text-white py-5 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">Liên hệ Goodwill Vietnam</h1>
                <p class="lead mb-0">Kết nối để đồng hành cùng cộng đồng: đóng góp, hợp tác tổ chức, truyền thông hay hỗ trợ kỹ thuật.</p>
            </div>
            <div class="col-lg-5 text-center">
                <i class="bi bi-chat-dots-fill display-1"></i>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Thông tin liên hệ</h3>
                        <div class="d-flex align-items-start mb-3">
                            <span class="badge bg-success me-3"><i class="bi bi-geo-alt-fill"></i></span>
                            <div>
                                <div class="fw-semibold">Văn phòng</div>
                                <div class="text-muted">Hà Nội, Việt Nam</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <span class="badge bg-primary me-3"><i class="bi bi-envelope-fill"></i></span>
                            <div>
                                <div class="fw-semibold">Email</div>
                                <div class="text-muted">support@goodwillvietnam.com</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <span class="badge bg-warning text-dark me-3"><i class="bi bi-telephone-fill"></i></span>
                            <div>
                                <div class="fw-semibold">Hotline</div>
                                <div class="text-muted">(+84) 1900 0000</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <span class="badge bg-dark me-3"><i class="bi bi-clock-fill"></i></span>
                            <div>
                                <div class="fw-semibold">Giờ làm việc</div>
                                <div class="text-muted">Thứ 2 - Thứ 6, 08:30 - 17:30</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Gửi lời nhắn</h3>
                        <form>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Họ tên</label>
                                <input type="text" class="form-control" placeholder="Nguyễn Văn A">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" placeholder="ban@email.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nội dung</label>
                                <textarea class="form-control" rows="4" placeholder="Bạn muốn trao đổi điều gì?"></textarea>
                            </div>
                            <button type="button" class="btn btn-primary w-100" disabled>Gửi (đang cập nhật)</button>
                        </form>
                        <p class="text-muted small mb-0 mt-2">Hiện form đang bật chế độ xem; vui lòng liên hệ qua email/điện thoại.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
