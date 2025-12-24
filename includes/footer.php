    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
                    </h5>
                    <p class="text-muted">
                        Kết nối những tấm lòng nhân ái, tạo nên những điều kỳ diệu cho cộng đồng.
                    </p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="https://www.facebook.com/vuphong.levan.3/" class="text-white fs-4"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white fs-4"><i class="bi bi-twitter"></i></a>
                        <a href="https://www.instagram.com/_vduongg2818_" class="text-white fs-4"><i class="bi bi-instagram"></i></a>
                        <a href="https://www.youtube.com/watch?v=kLfu72cva-4" class="text-white fs-4"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Liên kết</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Trang chủ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>donate.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Quyên góp
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>shop.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Shop
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>campaigns.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Chiến dịch
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Hỗ trợ</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>about.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Giới thiệu
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>contact.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Liên hệ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>help.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Trợ giúp
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>faq.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>FAQ
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Liên hệ</h6>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt me-2"></i>328 Ngo Quyen, Son Tra, Da Nang, Việt Nam
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope me-2"></i>info@goodwillvietnam.com
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>+84 123 456 789
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock me-2"></i>T2-T6: 8:00 - 17:00
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 bg-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> Goodwill Vietnam. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>privacy.php" class="text-muted text-decoration-none me-3">
                        Chính sách bảo mật
                    </a>
                    <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>terms.php" class="text-muted text-decoration-none">
                        Điều khoản sử dụng
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>assets/js/main.js"></script>
    
    <!-- Additional scripts if needed -->
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
</body>
</html>
