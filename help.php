<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Trợ giúp";
include 'includes/header.php';
?>

<section class="bg-gradient-primary text-white py-5 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">Trung tâm trợ giúp</h1>
                <p class="lead mb-0">Hướng dẫn nhanh về quyên góp, mua hàng thiện nguyện và quản lý tài khoản.</p>
            </div>
            <div class="col-lg-5 text-center">
                <i class="bi bi-life-preserver display-1"></i>
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
                        <h3 class="fw-bold mb-3">Câu hỏi thường gặp</h3>
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq1">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                        Làm sao để gửi quyên góp?
                                    </button>
                                </h2>
                                <div id="faqCollapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Vào trang <a href="donate.php">Quyên góp</a>, điền thông tin vật phẩm, tối đa 5 ảnh và chọn thời gian nhận. Đơn sẽ chờ admin duyệt trước khi vào kho.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                        Mua hàng trên shop hoạt động thế nào?
                                    </button>
                                </h2>
                                <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Xem <a href="items.php">Vật phẩm</a>, thêm vào giỏ, checkout với địa chỉ nhận. Hệ thống trừ kho và tạo đơn; theo dõi tại <a href="my-orders.php">Đơn của tôi</a>.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                        Tôi muốn tham gia tình nguyện?
                                    </button>
                                </h2>
                                <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Chọn chiến dịch tại <a href="campaigns.php">Chiến dịch</a> hoặc chi tiết chiến dịch, đăng ký tình nguyện viên và để lại kỹ năng/thời gian.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq4">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4">
                                        Không nhận được email / thông báo?
                                    </button>
                                </h2>
                                <div id="faqCollapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Kiểm tra spam hoặc liên hệ <a href="contact.php">Liên hệ</a>. Hệ thống hiện chưa bật gửi mail tự động, team sẽ hỗ trợ thủ công.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Liên hệ hỗ trợ</h3>
                        <p class="text-muted">Nếu câu hỏi chưa có ở trên, hãy liên hệ đội ngũ Goodwill Vietnam.</p>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="bi bi-envelope-fill text-primary me-2"></i> support@goodwillvietnam.com</li>
                            <li class="mb-2"><i class="bi bi-telephone-fill text-success me-2"></i> (+84) 1900 0000</li>
                            <li class="mb-2"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Hà Nội, Việt Nam</li>
                        </ul>
                        <a href="contact.php" class="btn btn-outline-primary">Đi đến trang Liên hệ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
