<?php

require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

include __DIR__ . '/../layouts/header.php'; 

// Kiểm tra kết nối PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('<div class="alert alert-danger container mt-5">Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu (PDO).</div>');
}

// Kiểm tra và lấy ID sách
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    set_session_message('Không tìm thấy ID sách hợp lệ để xem chi tiết.', 'danger');
    header('Location: /index.php'); 
    exit;
}

$book_id = $_GET['id'];

// --- 1. Truy vấn chi tiết sách ---
$sql = "SELECT b.*, c.name AS category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.id = :book_id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 2. Lấy Tác giả của cuốn sách ---
$author_names = [];
if ($book) {
    $stmt_authors = $pdo->prepare("SELECT a.name FROM authors a 
                                    JOIN book_author ba ON a.id = ba.author_id 
                                    WHERE ba.book_id = :book_id");
    $stmt_authors->execute(['book_id' => $book['id']]);
    $author_names = $stmt_authors->fetchAll(PDO::FETCH_COLUMN, 0);
}
$authors_list = implode(', ', array_map('htmlspecialchars', $author_names));

// --- 3. Kiểm tra xem người dùng đã mượn sách này chưa ---
$has_borrowed = false;
$loan_id = null;
if (is_logged_in() && $book) {
    $user_id = $_SESSION['user_id'];

    // 3a. Thử tìm trong bảng `loans` (chuẩn)
    $sql_check_loan = "SELECT id FROM loans WHERE book_id = :book_id AND user_id = :user_id AND LOWER(status) = 'borrowed'";
    $stmt_check = $pdo->prepare($sql_check_loan);
    $stmt_check->execute(['book_id' => $book['id'], 'user_id' => $user_id]);

    if ($loan_data = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
        $has_borrowed = true;
        $loan_id = $loan_data['id'];
    } else {
        // 3b. Nếu không thấy, một số phần của project lưu ở bảng `borrowings` — thử tìm ở đó
        $sql_check_b = "SELECT id FROM borrowings WHERE book_id = :book_id AND user_id = :user_id AND LOWER(status) = 'borrowed'";
        $stmt_check_b = $pdo->prepare($sql_check_b);
        $stmt_check_b->execute(['book_id' => $book['id'], 'user_id' => $user_id]);
        if ($brow = $stmt_check_b->fetch(PDO::FETCH_ASSOC)) {
            $has_borrowed = true;
            $loan_id = $brow['id'];
        }
    }
}

?>

<div class="container my-5">
    <?= display_session_message(); // Hiển thị thông báo sau khi chuyển hướng ?>

    <?php if (!$book): ?>
        <div class="alert alert-warning text-center p-5">
            <h1 class="display-6"><i class="bi-exclamation-triangle-fill me-2"></i> Không tìm thấy sách</h1>
            <p class="lead">ID sách bạn yêu cầu không tồn tại trong thư viện.</p>
            <a href="/index.php" class="btn btn-primary mt-3">Quay lại Trang chủ</a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Cột Bìa sách -->
            <div class="col-md-4 mb-4 mb-md-0">
                <img src="<?= htmlspecialchars($book['cover'] ? $book['cover'] : 'https://placehold.co/400x600/007bff/ffffff?text=Book+Cover') ?>" 
                     class="img-fluid rounded-3 shadow-lg" alt="<?= htmlspecialchars($book['title']) ?>">
            </div>

            <!-- Cột Chi tiết -->
            <div class="col-md-8">
                <h1 class="display-5 fw-bold text-primary"><?= htmlspecialchars($book['title']) ?></h1>
                
                <div class="mb-4">
                    <p class="lead text-muted"><i class="bi-person-fill me-2"></i> <strong>Tác giả:</strong> <?= $authors_list ?: 'Đang cập nhật' ?></p>
                    <p class="text-muted"><i class="bi-tag-fill me-2"></i> <strong>Thể loại:</strong> <?= htmlspecialchars($book['category_name'] ?? 'Chung') ?></p>
                    <p class="text-muted"><i class="bi-calendar-fill me-2"></i> <strong>Năm xuất bản:</strong> <?= htmlspecialchars($book['year']) ?></p>

                    <?php 
                        $is_available = $book['quantity'] > 0;
                        $status_text = $is_available ? "Còn hàng ({$book['quantity']} cuốn)" : "Đã hết hàng";
                        $status_class = $is_available ? "text-success" : "text-danger";
                        $status_icon = $is_available ? "bi-check-circle-fill" : "bi-x-circle-fill";
                    ?>
                    <h3 class="mt-4">
                        <span class="badge rounded-pill bg-light <?= $status_class ?> border border-3 <?= $status_class ?> p-3">
                            <i class="<?= $status_icon ?> me-2"></i> <?= $status_text ?>
                        </span>
                    </h3>
                </div>

                <!-- Mô tả -->
                <h2 class="h4 mt-5 mb-3 text-secondary">Tóm tắt nội dung</h2>
                <div class="bg-light p-4 rounded-3 shadow-sm" style="white-space: pre-wrap;">
                    <?= nl2br(htmlspecialchars($book['description'] ?? 'Không có mô tả chi tiết cho cuốn sách này.')) ?>
                </div>

                <!-- Nút Hành động Người dùng -->
                <div class="mt-4 pt-3 border-top">
                    <a href="/index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi-arrow-left me-1"></i> Quay lại
                    </a>
                    
                    <?php if (!is_logged_in()): ?>
                         <a href="/users/login.php" class="btn btn-primary">
                            <i class="bi-box-arrow-in-right me-1"></i> Đăng nhập để mượn sách
                        </a>
                    <?php elseif ($has_borrowed): ?>
                        <a href="/loans/return.php?loan_id=<?= $loan_id ?>" class="btn btn-warning">
                            <i class="bi-arrow-return-left me-1"></i> Trả Sách Của Tôi
                        </a>
                        <span class="text-success ms-3 fw-bold"><i class="bi-check-circle-fill me-1"></i> Bạn đã mượn sách này!</span>
                    <?php elseif (is_logged_in() && $is_available): ?>
                         <a href="/Books/borrow.php?book_id=<?= $book_id = $_GET['id'] ?>" class="btn btn-success">
                            <i class="bi-cart-plus-fill me-1"></i> Mượn Sách Này
                        </a>
                    <?php else: ?>
                        <button class="btn btn-danger" disabled>
                            <i class="bi-x-circle-fill me-1"></i> Hết Sách
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>