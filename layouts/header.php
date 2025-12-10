<?php 

// Đảm bảo functions được include để dùng các hàm kiểm tra đăng nhập
if (!function_exists('start_session_if_not_started')) {
    require_once dirname(__DIR__) . '/functions.php';
}
start_session_if_not_started(); // Luôn khởi động session
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thư Viện Tâm Hồn</title>
    <!-- Bootstrap CSS v5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
          xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons (Sử dụng cho toàn bộ ứng dụng) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* Tùy chỉnh chung */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
        }
        
        /* Navbar Tùy chỉnh */
        .navbar-custom {
            background-color: #ffffff; /* Nền trắng */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); /* Bóng đổ nhẹ */
        }
        .navbar-brand .logo-text {
            font-weight: 700;
            color: #007bff; /* Màu xanh Primary */
        }
        .nav-link {
            font-weight: 500;
            color: #495057 !important;
            transition: color 0.3s;
        }
        .nav-link:hover {
            color: #007bff !important;
        }
        
        /* Nút nổi bật */
        .btn-brand {
            border-radius: 50px;
            font-weight: 600;
            padding: 8px 20px;
        }
        .btn-brand-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        .btn-brand-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        /* Thêm padding cho nội dung chính */
        main {
            min-height: 80vh;
        }
    </style>
</head>
<body>
    <main>
        <nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
            <div class="container">
                <!-- LOGO/BRAND -->
                <a class="navbar-brand" href="/index.php">
                    <i class="bi-book-half text-primary me-2" style="font-size: 1.5rem;"></i>
                    <span class="logo-text">Thư Viện</span>
                </a>

                <!-- TOGGLER cho mobile -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <!-- VỊ TRÍ NAVIGATION LINKS -->
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="/index.php">
                                <i class="bi-house-door-fill me-1"></i> Trang Chủ
                            </a>
                        </li>
                        
                        <?php if (get_user_role() === 'admin'): // Chỉ Admin mới thấy ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/index.php">
                                <i class="bi-person-gear me-1"></i> Dashboard Admin
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/Books/list.php">
                                <i class="bi-database-fill me-1"></i> Tủ Sách
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/Authors/list.php">
                                <i class="bi-people-fill me-1"></i> Tác Giả
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/Categories/list.php">
                                <i class="bi-folder-fill me-1"></i> Danh Mục
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Các liên kết khác có thể được thêm vào đây -->
                    </ul>

                    <!-- AUTH SECTION (ĐĂNG NHẬP/ĐĂNG KÝ/PROFILE) -->
                    <ul class="navbar-nav ms-auto">
                        <?php if (is_logged_in()): ?>
                            <!-- Hiển thị khi đã đăng nhập -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi-person-circle me-1"></i> 
                                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> (<?= ucfirst(get_user_role()) ?>)
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="navbarDropdown">
                                    
                                    <li>
                                        <a class="dropdown-item text-danger" href="/users/logout.php">
                                            <i class="bi-box-arrow-right me-2"></i> Đăng Xuất
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <!-- Hiển thị khi chưa đăng nhập -->
                            <li class="nav-item me-2">
                                <a class="btn btn-outline-primary btn-brand" href="/users/login.php">
                                    <i class="bi-box-arrow-in-right me-1"></i> Đăng Nhập
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-brand btn-brand-primary" href="/users/register.php">
                                    <i class="bi-person-plus-fill me-1"></i> Đăng Ký
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- Thẻ main đóng ở footer.php -->