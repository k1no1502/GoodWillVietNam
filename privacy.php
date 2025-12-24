<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Chính sách bảo mật";
include 'includes/header.php';
?>

<!-- Privacy Hero -->
<section class="py-5 mt-5 bg-light border-bottom">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <p class="text-success fw-semibold mb-2">Goodwill Vietnam</p>
                <h1 class="fw-bold display-5 mb-3">Chính sách bảo mật</h1>
                <p class="text-muted lead mb-0">
                    Chính sách này giải thích cách chúng tôi thu thập, sử dụng, bảo vệ và chia sẻ
                    dữ liệu cá nhân của bạn khi sử dụng hệ thống Goodwill Vietnam.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end text-center mt-4 mt-lg-0">
                <i class="bi bi-shield-lock text-success display-1"></i>
            </div>
        </div>
    </div>
</section>

<!-- Data Overview -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-collection me-2 text-success"></i>Dữ liệu thu thập</h5>
                        <p class="text-muted mb-0">
                            Chúng tôi thu thập thông tin đăng ký (tên, email, số điện thoại), thông tin quyên góp,
                            lịch sử giao dịch và các tương tác trên nền tảng để vận hành dịch vụ.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-gear me-2 text-success"></i>Mục đích sử dụng</h5>
                        <p class="text-muted mb-0">
                            Dữ liệu được dùng để xác thực tài khoản, xử lý giao dịch, hỗ trợ khách hàng,
                            nâng cao bảo mật và cải thiện trải nghiệm người dùng.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-lock-fill me-2 text-success"></i>Bảo vệ dữ liệu</h5>
                        <p class="text-muted mb-0">
                            Chúng tôi áp dụng mã hóa, phân quyền truy cập và giám sát hệ thống để hạn chế truy cập trái phép.
                            Người dùng cần tự bảo vệ thiết bị và mật khẩu của mình.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-people-fill me-2 text-success"></i>Chia sẻ thông tin</h5>
                        <p class="text-muted mb-0">
                            Chúng tôi không bán dữ liệu cho bên thứ ba. Thông tin chỉ được chia sẻ khi có sự đồng ý của bạn
                            hoặc theo yêu cầu pháp lý hợp lệ.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rights and Control -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="fw-bold mb-4">Quyền của người dùng</h2>
                <div class="list-group shadow-sm">
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">1. Truy cập và chỉnh sửa</h6>
                        <p class="text-muted mb-0">
                            Bạn có thể xem và cập nhật thông tin cá nhân tại trang hồ sơ hoặc liên hệ hỗ trợ để được giúp đỡ.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">2. Rút lại đồng ý</h6>
                        <p class="text-muted mb-0">
                            Bạn có thể thay đổi tùy chọn nhận thông báo, thu hồi quyền truy cập ứng dụng hoặc xóa tài khoản khi cần.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">3. Lưu trữ dữ liệu</h6>
                        <p class="text-muted mb-0">
                            Dữ liệu được lưu trữ trong thời gian cần thiết cho mục đích dịch vụ và theo quy định pháp luật liên quan.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">4. Cookie và theo dõi</h6>
                        <p class="text-muted mb-0">
                            Hệ thống sử dụng cookie/lưu trữ cục bộ để lưu trạng thái đăng nhập và nâng cao trải nghiệm.
                            Bạn có thể cấu hình trình duyệt để chặn cookie nếu muốn.
                        </p>
                    </div>
                    <div class="list-group-item py-3">
                        <h6 class="fw-semibold mb-1">5. Xử lý sự cố bảo mật</h6>
                        <p class="text-muted mb-0">
                            Khi có sự cố bảo mật, chúng tôi sẽ thông báo các bên ảnh hưởng và phối hợp khắc phục theo quy trình nội bộ.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Liên hệ bảo mật</h5>
                        <p class="text-muted">
                            Nếu bạn phát hiện hành vi nghi ngờ hoặc muốn thực hiện các quyền bảo mật, vui lòng liên hệ:
                        </p>
                        <ul class="list-unstyled text-muted mb-0">
                            <li class="mb-2"><i class="bi bi-envelope me-2 text-success"></i>privacy@goodwillvietnam.com</li>
                            <li class="mb-2"><i class="bi bi-phone me-2 text-success"></i>+84 123 456 789</li>
                            <li class="mb-0"><i class="bi bi-shield-lock me-2 text-success"></i>Bộ phận bảo mật hệ thống</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
