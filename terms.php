<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Điều khoản sử dụng";
include 'includes/header.php';
?>

<!-- Terms Hero -->
<section class="py-5 mt-5 bg-light border-bottom">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <p class="text-success fw-semibold mb-2">Goodwill Vietnam</p>
                <h1 class="fw-bold display-5 mb-3">Điều khoản sử dụng</h1>
                <p class="text-muted lead mb-0">
                    Đọc kỹ các điều khoản bên dưới để hiểu cách bạn có thể sử dụng nền tảng,
                    bảo vệ tài khoản và đóng góp an toàn cho cộng đồng.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end text-center mt-4 mt-lg-0">
                <i class="bi bi-file-text text-success display-1"></i>
            </div>
        </div>
    </div>
</section>

<!-- Key Terms -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-check me-2 text-success"></i>Chấp nhận điều khoản</h5>
                        <p class="text-muted mb-0">
                            Khi tạo tài khoản hoặc sử dụng dịch vụ, bạn đồng ý tuân thủ tất cả nội dung trong trang này.
                            Nếu có thay đổi, chúng tôi sẽ cập nhật tại đây và thông báo khi cần thiết.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-person-badge me-2 text-success"></i>Sử dụng tài khoản</h5>
                        <p class="text-muted mb-0">
                            Bạn chịu trách nhiệm bảo mật thông tin đăng nhập và hoạt động của tài khoản. Vui lòng không chia sẻ
                            mật khẩu, không giả mạo người khác, và thông báo ngay cho chúng tôi khi có dấu hiệu bất thường.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-heart me-2 text-success"></i>Nội dung đóng góp</h5>
                        <p class="text-muted mb-0">
                            Mọi quyên góp, chiến dịch, bình luận và dữ liệu bạn cung cấp cần chính xác, hợp pháp
                            và tôn trọng cộng đồng. Chúng tôi có quyền từ chối hoặc xóa nội dung vi phạm.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-lock me-2 text-success"></i>An toàn và bảo mật</h5>
                        <p class="text-muted mb-0">
                            Chúng tôi áp dụng các biện pháp kỹ thuật và tổ chức để bảo vệ dữ liệu người dùng, nhưng bạn
                            cần chủ động giữ an toàn thiết bị và thông tin đăng nhập khi truy cập.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rules List -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="fw-bold mb-4">Nguyên tắc sử dụng</h2>
                <div class="list-group shadow-sm">
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">1. Hành vi được chấp nhận</h6>
                        <p class="text-muted mb-0">
                            Không sử dụng nền tảng cho mục đích bất hợp pháp, lừa đảo, phân biệt đối xử hoặc vi phạm bản quyền.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">2. Quy trình quyên góp và bán hàng</h6>
                        <p class="text-muted mb-0">
                            Mọi giao dịch cần tuân thủ hướng dẫn công khai, minh bạch về số tiền, vật phẩm và trạng thái giao hàng.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">3. Quyền tạm dừng dịch vụ</h6>
                        <p class="text-muted mb-0">
                            Chúng tôi có thể tạm dừng hoặc khóa tài khoản nếu phát hiện vi phạm điều khoản, có dấu hiệu gian lận
                            hoặc ảnh hưởng xấu đến cộng đồng.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">4. Giới hạn trách nhiệm</h6>
                        <p class="text-muted mb-0">
                            Chúng tôi nỗ lực duy trì hệ thống ổn định nhưng không chịu trách nhiệm cho tổn thất gián tiếp
                            phát sinh từ việc sử dụng dịch vụ.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">5. Cập nhật điều khoản</h6>
                        <p class="text-muted mb-0">
                            Điều khoản có thể thay đổi để phù hợp quy định mới. Tiếp tục sử dụng sau khi cập nhật đồng nghĩa
                            bạn đồng ý với nội dung mới.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Hỗ trợ và liên hệ</h5>
                        <p class="text-muted">
                            Nếu bạn có câu hỏi về điều khoản, vui lòng liên hệ để được giải đáp và hỗ trợ.
                        </p>
                        <ul class="list-unstyled text-muted mb-0">
                            <li class="mb-2"><i class="bi bi-envelope me-2 text-success"></i>support@goodwillvietnam.com</li>
                            <li class="mb-2"><i class="bi bi-phone me-2 text-success"></i>+84 123 456 789</li>
                            <li class="mb-0"><i class="bi bi-chat-dots me-2 text-success"></i>Trang liên hệ trên hệ thống</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
