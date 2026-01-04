<?php 
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

require_admin(); // Chặn truy cập nếu không phải admin
start_session_if_not_started();

include __DIR__ . '/../layouts/header.php'; 

// 1. CẤU HÌNH PHÂN TRANG & TÌM KIẾM
$limit = 10; 
$current_page = max(1, (int)($_GET['page'] ?? 1));
$start = ($current_page - 1) * $limit; 

$search_keyword = trim($_GET['search'] ?? '');
$search_param = '%' . $search_keyword . '%';

// Xây dựng điều kiện WHERE
$where_clause = "";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    $where_clause = " WHERE b.title LIKE ? OR c.name LIKE ? OR a.name LIKE ?";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss";
}

// 2. TÍNH TỔNG SỐ SÁCH (Để phân trang)
$count_sql = "SELECT COUNT(DISTINCT b.id) AS total 
              FROM books b 
              LEFT JOIN categories c ON b.category_id = c.id
              LEFT JOIN book_author ba ON b.id = ba.book_id
              LEFT JOIN authors a ON ba.author_id = a.id" . $where_clause;

$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_books = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_books / $limit);

// 3. TRUY VẤN DỮ LIỆU CHÍNH
$sql = "SELECT b.id, b.title, b.year, b.cover, b.quantity, 
               c.name AS category_name, 
               GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS author_names
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN book_author ba ON b.id = ba.book_id
        LEFT JOIN authors a ON ba.author_id = a.id" 
        . $where_clause . 
        " GROUP BY b.id ORDER BY b.id DESC LIMIT ? OFFSET ?";

$stmt_main = $conn->prepare($sql);
$main_params = array_merge($params, [$limit, $start]);
$main_types = $types . "ii";
$stmt_main->bind_param($main_types, ...$main_params);
$stmt_main->execute();
$books = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>Quản Lý Sách</h2>
        <a href="add.php" class="btn btn-primary shadow-sm rounded-pill">
            <i class="bi bi-plus-circle me-1"></i> Thêm Sách Mới
        </a>
    </div>

    <?= display_session_message(); ?>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" 
                               placeholder="Tìm theo tiêu đề, tác giả, hoặc danh mục..." 
                               name="search" value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-dark" type="submit">Lọc dữ liệu</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($books)): ?>
        <div class="text-center py-5">
            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="mb-3 opacity-50">
            <p class="text-muted fs-5">Không tìm thấy dữ liệu nào phù hợp.</p>
            <a href="list.php" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Bìa</th>
                        <th>Tiêu đề & Thông tin</th>
                        <th>Danh mục</th>
                        <th>Kho</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                    <tr>
                        <td class="ps-4 text-muted">#<?= $book['id'] ?></td>
                        <td>
                            <?php 
                                $cover_img = !empty($book['cover']) ? $book['cover'] : 'https://placehold.co/100x150?text=No+Cover';
                            ?>
                            <img src="<?= htmlspecialchars($cover_img) ?>" 
                                 class="rounded shadow-sm" style="width: 50px; height: 75px; object-fit: cover;">
                        </td>
                        <td>
                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($book['title']) ?></div>
                            <small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($book['author_names'] ?: 'Ẩn danh') ?></small>
                            <div class="small text-muted"><i class="bi bi-calendar3"></i> Xuất bản: <?= $book['year'] ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($book['category_name'] ?: 'Chung') ?></span></td>
                        <td>
                            <?php if ($book['quantity'] > 5): ?>
                                <span class="badge bg-success-subtle text-success"><?= $book['quantity'] ?> cuốn</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger"><?= $book['quantity'] ?> (Sắp hết)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm">
                                <a href="edit.php?id=<?= $book['id'] ?>" class="btn btn-white btn-sm border" title="Sửa">
                                    <i class="bi bi-pencil text-warning"></i>
                                </a>
                                <a href="delete.php?id=<?= $book['id'] ?>" 
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa cuốn sách này?')" 
                                   class="btn btn-white btn-sm border" title="Xóa">
                                    <i class="bi bi-trash text-danger"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_keyword) ?>">Trước</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_keyword) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_keyword) ?>">Sau</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>