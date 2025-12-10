<?php

require_once __DIR__ . '/functions.php'; 
require_once __DIR__ . '/connect.php'; 

include __DIR__ . '/layouts/header.php';

// Cấu hình Phân trang
$limit = 10; // Số sách hiển thị mỗi trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$search_query = "%" . $search . "%";

// Hiển thị thông báo (flash messages)
echo display_session_message();

// Xử lý thông báo sau khi đăng ký/đăng xuất
if (isset($_GET['register']) && $_GET['register'] == 'success') {
    echo '<div class="alert alert-success alert-dismissible fade show container mt-3" role="alert">
            <i class="bi-check-circle-fill me-2"></i> Đăng ký thành công! Bạn có thể đăng nhập ngay.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    echo '<div class="alert alert-info alert-dismissible fade show container mt-3" role="alert">
            <i class="bi-info-circle-fill me-2"></i> Bạn đã đăng xuất thành công.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

// --- Xây dựng Truy vấn SQL ---

// 1. Truy vấn COUNT tổng số sách (để phân trang)
$sql_count = "SELECT COUNT(b.id) 
              FROM books b
              LEFT JOIN book_author ba ON b.id = ba.book_id
              LEFT JOIN authors a ON ba.author_id = a.id
              WHERE b.title LIKE ? OR a.name LIKE ?";

$stmt_count = $conn->prepare($sql_count);

// KIỂM TRA LỖI PREPARE TRUY VẤN COUNT
if ($stmt_count === false) {
    // Thường xảy ra khi tên bảng sai, cột sai, hoặc lỗi cú pháp SQL
    die('<div class="alert alert-danger container mt-5">Lỗi chuẩn bị truy vấn COUNT: ' . htmlspecialchars($conn->error) . '</div>');
}

$stmt_count->bind_param("ss", $search_query, $search_query);
$stmt_count->execute();
$total_books = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_books / $limit);
$stmt_count->close();

// 2. Truy vấn dữ liệu sách
$sql_data = "SELECT b.id, b.title, b.year, b.cover, b.quantity, c.name AS category_name
             FROM books b
             LEFT JOIN categories c ON b.category_id = c.id
             WHERE b.title LIKE ? OR b.title IN (
                SELECT DISTINCT b2.title 
                FROM books b2
                JOIN book_author ba ON b2.id = ba.book_id
                JOIN authors a ON ba.author_id = a.id
                WHERE a.name LIKE ?
             )
             ORDER BY b.title ASC
             LIMIT ? OFFSET ?";
             
$stmt_data = $conn->prepare($sql_data);

// KIỂM TRA LỖI PREPARE TRUY VẤN DATA
if ($stmt_data === false) {
    die('<div class="alert alert-danger container mt-5">Lỗi chuẩn bị truy vấn DATA: ' . htmlspecialchars($conn->error) . '</div>');
}

// Khác với truy vấn count, limit và offset là số nguyên
$stmt_data->bind_param("ssii", $search_query, $search_query, $limit, $offset);
$stmt_data->execute();
$books = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();
?>

<div class="container my-5">
    <div class="text-center mb-5 p-4 bg-white rounded-3 shadow-sm">
        <?php if (is_logged_in()): ?>
            <h1 class="display-5 fw-bold <?= get_user_role() === 'admin' ? 'text-danger' : 'text-success' ?>">
                Chào mừng, <?= htmlspecialchars($_SESSION['username'] ?? 'Bạn') ?>!
            </h1>
            <?php if (get_user_role() === 'admin'): ?>
                <p class="lead text-muted">Bạn đang ở chế độ Quản trị viên. <a href="/admin/index.php" class="text-danger fw-bold">Đi đến Dashboard.</a></p>
            <?php else: ?>
                <p class="lead text-muted">Hệ thống đang hiển thị <?= $total_books ?> tài liệu có sẵn trong thư viện của bạn.</p>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="display-5 fw-bold text-primary">
                <i class="bi-collection-fill me-2"></i> Khám phá Thư Viện Sách
            </h1>
            <p class="lead text-muted">Tìm kiếm từ hàng ngàn đầu sách thuộc mọi thể loại. <a href="/users/login.php" class="fw-bold">Đăng nhập</a> để mượn sách.</p>
        <?php endif; ?>
    </div>

    <!-- Thanh tìm kiếm -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <form action="index.php" method="GET" class="input-group input-group-lg shadow-sm">
                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm sách theo tiêu đề hoặc tác giả..." 
                       value="<?= htmlspecialchars($search) ?>" aria-label="Tìm kiếm">
                <button class="btn btn-primary" type="submit">
                    <i class="bi-search"></i> Tìm Kiếm
                </button>
                <?php if ($search): ?>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Kết quả tìm kiếm -->
    <h2 class="mt-5 mb-4 text-center text-md-start">
        <?php if ($search): ?>
            Kết quả cho "<?= htmlspecialchars($search) ?>"
        <?php else: ?>
            Sách Đang Có Sẵn
        <?php endif; ?>
    </h2>

    <?php if (empty($books)): ?>
        <div class="alert alert-warning text-center p-4">
            <i class="bi-exclamation-octagon-fill me-2"></i> Không tìm thấy cuốn sách nào phù hợp với từ khóa của bạn.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
            <?php foreach ($books as $book): ?>
                <?php
                    // Lấy Tác giả của cuốn sách (Sử dụng sub-query)
                    $author_names = [];
                    $stmt_authors = $conn->prepare("SELECT a.name FROM authors a 
                                                    JOIN book_author ba ON a.id = ba.author_id 
                                                    WHERE ba.book_id = ?");

                    if ($stmt_authors === false) {
                        $authors_list = 'Lỗi CSDL';
                    } else {
                        $stmt_authors->bind_param("i", $book['id']);
                        $stmt_authors->execute();
                        $result_authors = $stmt_authors->get_result();
                        while ($author = $result_authors->fetch_assoc()) {
                            $author_names[] = htmlspecialchars($author['name']);
                        }
                        $stmt_authors->close();
                        $authors_list = implode(', ', $author_names);
                    }
                    

                    // Trạng thái sách
                    $is_available = $book['quantity'] > 0;
                    $status_text = $is_available ? "Còn hàng" : "Hết hàng";
                    $status_class = $is_available ? "text-success" : "text-danger";
                    $status_icon = $is_available ? "bi-check-circle-fill" : "bi-x-circle-fill";
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden transition-all-300">
                        <img src="<?= htmlspecialchars($book['cover'] ? ''. $book['cover'] : 'https://placehold.co/400x600/007bff/ffffff?text=Book+Cover') ?>" 
                             class="card-img-top object-fit-cover" alt="<?= htmlspecialchars($book['title']) ?>" style="height: 250px;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold text-truncate"><?= htmlspecialchars($book['title']) ?></h5>
                            <p class="card-text small text-muted mb-1">
                                <i class="bi-person-fill me-1"></i> Tác giả: <?= $authors_list ?: 'Đang cập nhật' ?>
                            </p>
                            <p class="card-text small text-muted mb-2">
                                <i class="bi-tag-fill me-1"></i> Thể loại: <?= htmlspecialchars($book['category_name'] ?? 'Chung') ?>
                            </p>
                            <p class="card-text small mb-3">
                                <span class="<?= $status_class ?> fw-bold">
                                    <i class="<?= $status_icon ?> me-1"></i> <?= $status_text ?> 
                                </span>
                                (<?= (int)$book['quantity'] ?> cuốn)
                            </p>
                            <a href="Books/details.php?id=<?= htmlspecialchars($book['id']) ?>" class="btn btn-outline-primary btn-sm mt-auto rounded-pill">
                                <i class="bi-eye-fill me-1"></i> Chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Phân trang -->
        <nav aria-label="Phân trang sách" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php
                $pagination_base = 'index.php?search=' . urlencode($search) . '&';

                // Lùi lại
                if ($page > 1):
                ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $pagination_base ?>page=<?= $page - 1 ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php
                endif; // Đóng IF cho nút "Previous"

                // Các trang số
                for ($i = 1; $i <= $total_pages; $i++):
                ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $pagination_base ?>page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php
                endfor; // Đóng FOR cho các trang số

                // Tiến lên
                if ($page < $total_pages):
                ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $pagination_base ?>page=<?= $page + 1 ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php
                endif; // Đóng IF cho nút "Next"
                ?>
            </ul>
        </nav>
    <?php endif; // Đóng IF cho if (empty($books)) ?>
</div>

<style>
/* Hiệu ứng hover cho Card */
.transition-all-300 {
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php include __DIR__ . '/layouts/footer.php'; ?>