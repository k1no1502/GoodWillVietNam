<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "FAQ";
include 'includes/header.php';
?>

<section class="bg-gradient-primary text-white py-5 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">Câu hỏi thường gặp</h1>
                <p class="lead mb-0">Giải đáp nhanh về quyên góp, mua sắm thiện nguyện, chiến dịch và tài khoản.</p>
            </div>
            <div class="col-lg-5 text-center">
                <i class="bi bi-question-circle-fill display-1"></i>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="accordion" id="faqAccordionMain">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqDonate">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqDonateBody">
                                        Quyên góp vật phẩm thế nào?
                                    </button>
                                </h2>
                                <div id="faqDonateBody" class="accordion-collapse collapse show" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Mở trang <a href="donate.php">Quyên góp</a>, nhập thông tin vật phẩm, tối đa 5 ảnh, chọn danh mục và thời gian nhận. Đơn sẽ chờ admin duyệt trước khi vào kho.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqShop">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqShopBody">
                                        Mua hàng thiện nguyện hoạt động ra sao?
                                    </button>
                                </h2>
                                <div id="faqShopBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Xem <a href="items.php">Vật phẩm</a> (lọc danh mục/loại giá), thêm vào giỏ, checkout với địa chỉ nhận. Hệ thống trừ kho, đánh dấu sold khi hết, tạo đơn; theo dõi tại <a href="my-orders.php">Đơn của tôi</a>.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqCampaign">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCampaignBody">
                                        Chiến dịch và tình nguyện viên?
                                    </button>
                                </h2>
                                <div id="faqCampaignBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Vào <a href="campaigns.php">Chiến dịch</a> hoặc trang chi tiết để xem nhu cầu, tiến độ. Bạn có thể đăng ký tình nguyện viên và đóng góp vật phẩm cần thiết trực tiếp từ danh sách.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqAccount">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqAccountBody">
                                        Vấn đề đăng nhập / tài khoản?
                                    </button>
                                </h2>
                                <div id="faqAccountBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Dùng <a href="forgot-password.php">Quên mật khẩu</a> để đặt lại. Nếu vẫn lỗi, liên hệ <a href="contact.php">Liên hệ</a>. Đăng ký tài khoản mới tại <a href="register.php">Đăng ký</a>.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqStatus">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqStatusBody">
                                        Theo dõi tiến trình đơn/đóng góp ở đâu?
                                    </button>
                                </h2>
                                <div id="faqStatusBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Đơn hàng: xem <a href="order-tracking.php">Theo dõi đơn</a> hoặc <a href="my-orders.php">Đơn của tôi</a>. Quyên góp: xem <a href="donation-tracking.php">Timeline xử lý</a> và <a href="my-donations.php">Lịch sử quyên góp</a>.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqSupport">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqSupportBody">
                                        Không tìm thấy câu trả lời?
                                    </button>
                                </h2>
                                <div id="faqSupportBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Xem thêm tại <a href="help.php">Trợ giúp</a> hoặc liên hệ trực tiếp: support@goodwillvietnam.com, hotline (+84) 1900 0000.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-2">Kênh hỗ trợ</h5>
                        <p class="text-muted mb-3">Gặp lỗi kỹ thuật hoặc cần hợp tác, hãy liên hệ đội ngũ Goodwill Vietnam.</p>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-envelope-fill text-primary me-2"></i> support@goodwillvietnam.com</li>
                            <li class="mb-2"><i class="bi bi-telephone-fill text-success me-2"></i> (+84) 1900 0000</li>
                            <li class="mb-2"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Hà Nội, Việt Nam</li>
                        </ul>
                        <a href="contact.php" class="btn btn-outline-primary w-100 mt-3">Đi đến trang Liên hệ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
