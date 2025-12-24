<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Giới thiệu";
include 'includes/header.php';
?>

<!-- About Hero -->
<section class="bg-gradient-primary text-white py-5 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Về Goodwill Vietnam</h1>
                <p class="lead">Kết nối những tấm lòng nhân ái, tạo nên những điều kỳ diệu cho cộng đồng.</p>
            </div>
            <div class="col-lg-6 text-center">
                <i class="bi bi-heart-fill display-1"></i>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-bullseye text-primary display-3 mb-3"></i>
                                <h3 class="fw-bold mb-3">Sứ mệnh</h3>
                                <p class="text-muted">
                                    Xây dựng cầu nối giữa những người có nhu cầu với những tấm lòng hảo tâm, 
                                    tạo dựng một cộng đồng chia sẻ, tương trợ lẫn nhau.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center p-4">
                                <i class="bi bi-eye text-success display-3 mb-3"></i>
                                <h3 class="fw-bold mb-3">Tầm nhìn</h3>
                                <p class="text-muted">
                                    Trở thành nền tảng thiện nguyện hàng đầu Việt Nam, nơi mọi người có thể 
                                    dễ dàng chia sẻ và nhận được sự giúp đỡ.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Story -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-5 fw-bold text-center mb-5">Câu chuyện của chúng tôi</h2>
                
                <p class="lead mb-4">
                    Goodwill Vietnam ra đời từ ý tưởng đơn giản: "Làm sao để kết nối những người muốn giúp đỡ 
                    với những người cần được giúp đỡ một cách hiệu quả nhất?"
                </p>
                
                <p class="mb-4">
                    Chúng tôi nhận thấy rằng có rất nhiều người có những vật dụng còn tốt nhưng không dùng đến, 
                    trong khi đó lại có nhiều người đang rất cần những món đồ đó. Từ đó, Goodwill Vietnam 
                    được hình thành với mục đích tạo ra một nền tảng kết nối minh bạch, dễ dàng và hiệu quả.
                </p>
                
                <p class="mb-4">
                    Với hệ thống quản lý chặt chẽ, mọi quyên góp đều được kiểm duyệt kỹ lưỡng trước khi đến tay 
                    người nhận. Chúng tôi tin rằng việc thiện nguyện không chỉ là cho đi, mà còn là chia sẻ 
                    yêu thương và trách nhiệm với cộng đồng.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Values -->
<section class="py-5">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Giá trị cốt lõi</h2>
        
        <div class="row g-4">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="bi bi-shield-check fs-1"></i>
                    </div>
                    <h5 class="fw-bold">Minh bạch</h5>
                    <p class="text-muted">Mọi hoạt động đều được công khai và có thể kiểm chứng</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="bi bi-people fs-1"></i>
                    </div>
                    <h5 class="fw-bold">Cộng đồng</h5>
                    <p class="text-muted">Xây dựng một cộng đồng chia sẻ và tương trợ lẫn nhau</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center">
                    <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="bi bi-heart fs-1"></i>
                    </div>
                    <h5 class="fw-bold">Trách nhiệm</h5>
                    <p class="text-muted">Cam kết với sự phát triển bền vững của xã hội</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center">
                    <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="bi bi-lightning fs-1"></i>
                    </div>
                    <h5 class="fw-bold">Hiệu quả</h5>
                    <p class="text-muted">Tối ưu hóa quy trình để đạt kết quả tốt nhất</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="py-5 bg-success text-white">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Con số ấn tượng</h2>
        
        <div class="row text-center">
            <?php
            $stats = getStatistics();
            ?>
            <div class="col-md-3 mb-4">
                <h2 class="display-3 fw-bold"><?php echo number_format($stats['users']); ?>+</h2>
                <p class="lead">Người dùng</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-3 fw-bold"><?php echo number_format($stats['donations']); ?>+</h2>
                <p class="lead">Quyên góp</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-3 fw-bold"><?php echo number_format($stats['items']); ?>+</h2>
                <p class="lead">Vật phẩm</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-3 fw-bold"><?php echo number_format($stats['campaigns']); ?>+</h2>
                <p class="lead">Chiến dịch</p>
            </div>
        </div>
    </div>
</section>

<!-- Team (Optional) -->
<section class="py-5">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Đội ngũ của chúng tôi</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <i class="bi bi-person-circle text-primary display-1 mb-3"></i>
                        <h5 class="fw-bold">Nguyễn Văn A</h5>
                        <p class="text-muted mb-0">Founder & CEO</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <i class="bi bi-person-circle text-success display-1 mb-3"></i>
                        <h5 class="fw-bold">Trần Thị B</h5>
                        <p class="text-muted mb-0">Operations Manager</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <i class="bi bi-person-circle text-warning display-1 mb-3"></i>
                        <h5 class="fw-bold">Lê Văn C</h5>
                        <p class="text-muted mb-0">Community Manager</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="display-5 fw-bold mb-4">Cùng tham gia với chúng tôi</h2>
                <p class="lead mb-4">
                    Hãy trở thành một phần của cộng đồng Goodwill Vietnam. 
                    Mỗi đóng góp của bạn đều có ý nghĩa!
                </p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="donate.php" class="btn btn-success btn-lg">
                        <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                    </a>
                    <a href="campaigns.php" class="btn btn-outline-success btn-lg">
                        <i class="bi bi-trophy me-2"></i>Xem chiến dịch
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
