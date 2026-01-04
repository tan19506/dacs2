<?php 
// Đảm bảo functions được nạp
require_once __DIR__ . '/../functions.php';
start_session_if_not_started(); 
?>
<!DOCTYPE html>
<html lang="vi" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thư Viện Tâm Hồn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background: #fff; border-bottom: 1px solid #eee; }
        .logo-text { font-weight: 700; color: #0d6efd; letter-spacing: -0.5px; }
        .nav-link { font-size: 0.95rem; transition: all 0.2s; }
        .dropdown-menu { border-radius: 12px; padding: 10px; }
        .btn-brand { border-radius: 8px; font-weight: 600; }
        /* Đảm bảo footer luôn ở dưới cùng */
        body { display: flex; flex-column: column; min-height: 100vh; }
        main { flex: 1 0 auto; }
    </style>
</head>
<body class="d-flex flex-column h-100">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light navbar-custom py-3 shadow-sm sticky-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="/index.php">
                    <i class="bi bi-bookmarks-fill text-primary me-2 fs-3"></i>
                    <span class="logo-text">TÂM HỒN</span>
                </a>

                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link px-3" href="/index.php"><i class="bi bi-house me-1"></i> Trang Chủ</a>
                        </li>
                        
                        <?php if (get_user_role() === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle px-3" href="#" id="adminMenu" data-bs-toggle="dropdown">
                                <i class="bi bi-shield-lock me-1"></i> Quản Trị
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item" href="/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/Books/list.php"><i class="bi bi-book me-2"></i>Quản lý Sách</a></li>
                                <li><a class="dropdown-item" href="/Authors/list.php"><i class="bi bi-person-badge me-2"></i>Quản lý Tác giả</a></li>
                                <li><a class="dropdown-item" href="/Categories/list.php"><i class="bi bi-tags me-2"></i>Quản lý Danh mục</a></li>
                                <li><a class="dropdown-item" href="/Users/list.php"><i class="bi bi-people me-2"></i>Quản lý Độc giả</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <?php if (is_logged_in()): ?>
                            <div class="dropdown">
                                <button class="btn btn-light dropdown-toggle rounded-pill px-3 shadow-sm" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                    <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow mt-2">
                                    <li><a class="dropdown-item" href="/users/profile.php"><i class="bi bi-person me-2"></i>Hồ sơ của tôi</a></li>
                                    <li><a class="dropdown-item" href="/users/my_books.php"><i class="bi bi-journal-bookmark me-2"></i>Sách đang mượn</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/users/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="/users/login.php" class="btn btn-outline-primary btn-brand px-4">Đăng Nhập</a>
                            <a href="/users/register.php" class="btn btn-primary btn-brand px-4 shadow-sm">Đăng Ký</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="flex-shrink-0">