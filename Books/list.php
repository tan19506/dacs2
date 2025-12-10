<?php 

require_once '../functions.php';
require_admin(); // YÊU CẦU QUYỀN ADMIN ĐỂ XEM DANH SÁCH QUẢN LÝ

include '../layouts/header.php'; 
include '../connect.php'; 

// Xử lý thông báo thành công
$message = '';
if (isset($_GET['success']) && $_GET['success'] == 'add') {
    $message = '<div class="alert alert-success">Thêm sách thành công!</div>';
} elseif (isset($_GET['success']) && $_GET['success'] == 'edit') {
    $message = '<div class="alert alert-success">Cập nhật sách thành công!</div>';
} elseif (isset($_GET['success']) && $_GET['success'] == 'delete') {
    $message = '<div class="alert alert-warning">Đã xóa sách thành công!</div>';
}

// -----------------------------------------------------------
// 1. CẤU HÌNH PHÂN TRANG VÀ LOGIC TÌM KIẾM
// -----------------------------------------------------------
$limit = 10; // Số sách hiển thị trên mỗi trang Admin
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;
$start = ($current_page - 1) * $limit; 

$search_keyword = trim($_GET['search'] ?? '');
$search_param = '%' . $search_keyword . '%';

// Biến cho Truy vấn TỔNG SỐ SÁCH
$count_sql = "
    SELECT COUNT(DISTINCT b.id) AS total
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN book_author ba ON b.id = ba.book_id
    LEFT JOIN authors a ON ba.author_id = a.id
";

$where_clause = "";
$bind_params = [];
$bind_types = "";

if (!empty($search_keyword)) {
    // Điều kiện tìm kiếm (Tìm theo Title, Category, Author)
    $where_clause = "
        WHERE b.title LIKE ? 
        OR c.name LIKE ?
        OR EXISTS (
            SELECT 1 FROM book_author ba2
            JOIN authors a2 ON ba2.author_id = a2.id
            WHERE ba2.book_id = b.id AND a2.name LIKE ?
        )
    ";
    $bind_params = [$search_param, $search_param, $search_param];
    $bind_types = "sss";
}

// Thực hiện truy vấn COUNT
$count_sql .= $where_clause;
if (!empty($bind_params)) {
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param($bind_types, ...$bind_params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
} else {
    $result_count = $conn->query($count_sql);
}

$total_books = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_books / $limit);

// Điều chỉnh trang hiện tại
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $start = ($current_page - 1) * $limit;
}

// -----------------------------------------------------------
// 2. TRUY VẤN DỮ LIỆU SÁCH CHO TRANG HIỆN TẠI (CÓ PHÂN TRANG)
// -----------------------------------------------------------
$sql = "
    SELECT
        b.id,
        b.title,
        b.year,
        b.cover,
        b.quantity,  /* LẤY THÊM CỘT SỐ LƯỢNG */
        c.name AS category_name, 
        GROUP_CONCAT(a.name SEPARATOR ', ') AS author_names
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN book_author ba ON b.id = ba.book_id
    LEFT JOIN authors a ON ba.author_id = a.id
";

$sql .= $where_clause;

// Thêm GROUP BY, ORDER BY và LIMIT/OFFSET
$sql .= " GROUP BY b.id ORDER BY b.title ASC LIMIT ? OFFSET ?";

// Chuẩn bị tham số cho truy vấn chính
$main_bind_params = $bind_params; // Bắt đầu bằng tham số tìm kiếm (nếu có)
$main_bind_params[] = $limit;
$main_bind_params[] = $start;
$main_bind_types = $bind_types . "ii"; // Thêm 'ii' cho LIMIT và OFFSET

// Thực hiện truy vấn chính
$stmt = $conn->prepare($sql);
$stmt->bind_param($main_bind_types, ...$main_bind_params);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-5">
    <h2 class="mb-4">Quản Lý Sách (Tổng: <?= $total_books ?>)</h2>
    <?= $message ?>
    
    <!-- KHỐI TÌM KIẾM -->
    <form method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" class="form-control" placeholder="Tìm theo tiêu đề, tác giả, hoặc danh mục..." name="search" value="<?= htmlspecialchars($search_keyword) ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi-search"></i> Tìm Kiếm
            </button>
            <?php if (!empty($search_keyword)): ?>
                <a href="list.php" class="btn btn-outline-danger">Xóa Tìm Kiếm</a>
            <?php endif; ?>
        </div>
        <?php if (empty($search_keyword) && $current_page > 1): ?>
            <input type="hidden" name="page" value="<?= $current_page ?>">
        <?php endif; ?>
    </form>
    <!-- KẾT THÚC KHỐI TÌM KIẾM -->


    <div class="d-flex justify-content-end mb-3">
        <a href="add.php" class="btn btn-primary">
            <i class="bi-plus-circle"></i> Thêm Sách Mới
        </a>
    </div>

    <?php if (empty($books)): ?>
        <div class="alert alert-info text-center">
            <?php if (!empty($search_keyword)): ?>
                Không tìm thấy cuốn sách nào với từ khóa "<?= htmlspecialchars($search_keyword) ?>".
            <?php else: ?>
                Chưa có cuốn sách nào được thêm vào hệ thống.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Đang hiển thị sách từ **<?= $start + 1 ?>** đến **<?= $start + count($books) ?>**</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 10%;">Ảnh bìa</th>
                        <th style="width: 25%;">Tiêu đề</th>
                        <th style="width: 15%;">Tác giả</th>
                        <th style="width: 10%;">Danh mục</th>
                        <th style="width: 5%;">Năm XB</th>
                        <th style="width: 10%;">Số lượng</th> <!-- CỘT MỚI -->
                        <th style="width: 10%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($books as $book): 
                    ?>
                    <tr>
                        <td><?= $book['id'] ?></td>
                        <td>
                            <?php if ($book['cover']): ?>
                                <img src="..<?= htmlspecialchars($book['cover']) ?>" 
                                     alt="<?= htmlspecialchars($book['title']) ?>" 
                                     style="width: 70px; height: 100px; object-fit: cover; border-radius: 5px;">
                            <?php else: ?>
                                (Không có ảnh)
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($book['title']) ?></td>
                        <td>
                            <?= htmlspecialchars($book['author_names'] ?? 'Đang cập nhật') ?> 
                        </td>
                        <td>
                            <?= htmlspecialchars($book['category_name'] ?? 'Chưa phân loại') ?> 
                        </td>
                        <td><?= $book['year'] ?></td>
                        <td>
                            <span class="badge bg-info text-dark"><?= $book['quantity'] ?></span> <!-- HIỂN THỊ SỐ LƯỢNG -->
                        </td>
                        <td>
                            <a href="edit.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-warning mb-1" title="Sửa">
                                <i class="bi-pencil-square"></i>
                            </a>
                            <a href="delete.php?id=<?= $book['id'] ?>" 
                               onclick="return confirm('Bạn có chắc muốn xóa cuốn sách này không?')" 
                               class="btn btn-sm btn-danger mb-1" title="Xóa">
                                <i class="bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- HIỂN THỊ PHÂN TRANG -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                
                <!-- Nút Previous -->
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">
                        Trước
                    </a>
                </li>
                
                <?php 
                // Hiển thị tối đa 5 nút trang (ví dụ)
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                for ($i = $start_page; $i <= $end_page; $i++): 
                    $page_url = "?page=" . $i;
                    if (!empty($search_keyword)) {
                        $page_url .= "&search=" . urlencode($search_keyword);
                    }
                ?>
                    <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $page_url ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- Nút Next -->
                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">
                        Sau
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <!-- KẾT THÚC PHÂN TRANG -->

    <?php endif; ?>
</div>

<?php include '../layouts/footer.php'; ?>