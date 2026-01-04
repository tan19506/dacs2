<?php
require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

include __DIR__ . '/../layouts/header.php'; 

// 1. Kiểm tra ID sách
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    set_session_message('Không tìm thấy thông tin sách yêu cầu.', 'danger');
    redirect('/index.php');
}

try {
    // 2. Truy vấn chi tiết sách và thể loại
    $sql = "SELECT b.*, c.name AS category_name
            FROM books b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.id = :book_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['book_id' => $book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        throw new Exception("Sách không tồn tại.");
    }

    // 3. Lấy danh sách tác giả (Quan hệ n-n qua bảng book_author)
    $stmt_authors = $pdo->prepare("SELECT a.name FROM authors a 
                                    JOIN book_author ba ON a.id = ba.author_id 
                                    WHERE ba.book_id = :book_id");
    $stmt_authors->execute(['book_id' => $book_id]);
    $author_names = $stmt_authors->fetchAll(PDO::FETCH_COLUMN);
    $authors_display = !empty($author_names) ? implode(', ', $author_names) : 'Đang cập nhật';

    // 4. Kiểm tra tình trạng mượn của người dùng hiện tại
    $has_borrowed = false;
    $loan_id = null;
    if (is_logged_in()) {
        $sql_check = "SELECT id FROM loans 
                      WHERE book_id = :book_id AND user_id = :user_id AND status = 'Borrowed'";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute(['book_id' => $book_id, 'user_id' => $_SESSION['user_id']]);
        if ($loan = $stmt_check->fetch()) {
            $has_borrowed = true;
            $loan_id = $loan['id'];
        }
    }

} catch (Exception $e) {
    set_session_message($e->getMessage(), 'danger');
    redirect('/index.php');
}
?>

<div class="container my-5">
    <?= display_session_message(); ?>

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php">Thư viện</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($book['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-lg overflow-hidden rounded-4">
                <?php 
                $cover_path = !empty($book['cover']) ? $book['cover'] : 'https://placehold.co/600x900?text=No+Cover';
                ?>
                <img src="<?= htmlspecialchars($cover_path) ?>" class="img-fluid w-100" alt="Bìa sách">
            </div>
        </div>

        <div class="col-md-8">
            <h1 class="display-4 fw-bold text-dark mb-3"><?= htmlspecialchars($book['title']) ?></h1>
            
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">
                    <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($book['category_name'] ?? 'Chưa phân loại') ?>
                </span>
                <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill">
                    <i class="bi bi-calendar-check me-1"></i> Năm: <?= htmlspecialchars($book['year']) ?>
                </span>
            </div>

            <div class="mb-4">
                <h5 class="text-muted mb-1">Tác giả:</h5>
                <p class="fs-5 fw-semibold text-primary"><?= htmlspecialchars($authors_display) ?></p>
            </div>

            <div class="mb-5">
                <h5 class="text-muted mb-2">Tóm tắt nội dung:</h5>
                <div class="p-3 bg-light rounded-3 border-start border-4 border-primary">
                    <p class="text-dark mb-0" style="line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($book['description'] ?: 'Hiện chưa có nội dung tóm tắt cho cuốn sách này.')) ?>
                    </p>
                </div>
            </div>

            <div class="card border-0 bg-white shadow-sm rounded-4 p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <span class="text-muted d-block">Trạng thái kho:</span>
                        <?php if ($book['quantity'] > 0): ?>
                            <strong class="text-success fs-4"><i class="bi bi-check2-circle"></i> Sẵn sàng cho mượn</strong>
                            <span class="text-muted ms-2">(Còn <?= $book['quantity'] ?> cuốn)</span>
                        <?php else: ?>
                            <strong class="text-danger fs-4"><i class="bi bi-x-circle"></i> Hiện đã hết sách</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <?php if (!is_logged_in()): ?>
                        <a href="/users/login.php" class="btn btn-primary btn-lg px-5 rounded-pill shadow">
                            <i class="bi bi-person-lock me-2"></i> Đăng nhập để mượn
                        </a>
                    <?php elseif ($has_borrowed): ?>
                        <div class="alert alert-success d-flex align-items-center mb-0 w-100 rounded-pill">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div>Bạn đang mượn cuốn sách này. Hãy trả sách trước khi mượn lại.</div>
                        </div>
                    <?php elseif ($book['quantity'] > 0): ?>
                        <a href="/Books/borrow.php?book_id=<?= $book['id'] ?>" 
                           class="btn btn-success btn-lg px-5 rounded-pill shadow-sm">
                            <i class="bi bi-cart-plus me-2"></i> Mượn sách ngay
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg px-5 rounded-pill" disabled>
                            <i class="bi bi-dash-circle me-2"></i> Tạm hết hàng
                        </button>
                    <?php endif; ?>
                    
                    <a href="/index.php" class="btn btn-outline-secondary btn-lg px-4 rounded-pill">
                        <i class="bi bi-house"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>