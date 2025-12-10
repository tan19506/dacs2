<?php 

require_once '../functions.php';
require_once '../connect.php'; 
require_admin(); // YÊU CẦU QUYỀN ADMIN

// Lấy thông báo flash message TẠI ĐÂY để đảm bảo biến $message luôn được định nghĩa
$message = display_session_message();

include '../layouts/header.php'; 

// -----------------------------------------------------------
// 1. CẤU HÌNH PHÂN TRANG VÀ LOGIC TÌM KIẾM
// -----------------------------------------------------------
$limit = 10; // Số tác giả hiển thị trên mỗi trang
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;

$search_keyword = trim($_GET['search'] ?? '');
$search_param = '%' . $search_keyword . '%';

// Xây dựng điều kiện WHERE
$where_clause = "";
$bind_params = [];
$bind_types = "";

if (!empty($search_keyword)) {
    $where_clause = " WHERE name LIKE ?";
    $bind_params = [$search_param];
    $bind_types = "s";
}

// Thực hiện truy vấn COUNT
$count_sql = "SELECT COUNT(id) AS total FROM authors" . $where_clause;

if (!empty($bind_params)) {
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param($bind_types, ...$bind_params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
} else {
    $result_count = $conn->query($count_sql);
}

$total_authors = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_authors / $limit);

// Điều chỉnh trang hiện tại
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$start = ($current_page - 1) * $limit; 
if ($start < 0) $start = 0; // Đảm bảo start không âm

// -----------------------------------------------------------
// 2. TRUY VẤN DỮ LIỆU TÁC GIẢ CHO TRANG HIỆN TẠI
// -----------------------------------------------------------
$sql = "
    SELECT 
        id, 
        name,
        (SELECT COUNT(*) FROM book_author WHERE author_id = authors.id) AS book_count 
    FROM authors
    " . $where_clause . "
    ORDER BY name ASC
    LIMIT ? OFFSET ?
";

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
$authors = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-5">
    <h2 class="mb-4 text-primary">
        <i class="bi-person-badge-fill me-2"></i> Quản Lý Tác Giả (Tổng: <?= $total_authors ?>)
    </h2>
    <?= $message ?>
    
    <!-- KHỐI TÌM KIẾM -->
    <form method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" class="form-control" placeholder="Tìm kiếm theo tên tác giả..." name="search" value="<?= htmlspecialchars($search_keyword) ?>">
            <button class="btn btn-outline-primary" type="submit">
                <i class="bi-search"></i> Tìm Kiếm
            </button>
            <?php if (!empty($search_keyword)): ?>
                <a href="list.php" class="btn btn-outline-danger">Xóa Tìm Kiếm</a>
            <?php endif; ?>
        </div>
    </form>
    <!-- KẾT THÚC KHỐI TÌM KIẾM -->


    <div class="d-flex justify-content-end mb-3">
        <a href="add.php" class="btn btn-success">
            <i class="bi-plus-circle"></i> Thêm Tác Giả Mới
        </a>
    </div>

    <?php if (empty($authors)): ?>
        <div class="alert alert-info text-center">
            <?php if (!empty($search_keyword)): ?>
                Không tìm thấy tác giả nào với từ khóa "<?= htmlspecialchars($search_keyword) ?>".
            <?php else: ?>
                Chưa có tác giả nào được thêm vào hệ thống.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Đang hiển thị tác giả từ **<?= $start + 1 ?>** đến **<?= $start + count($authors) ?>**</p>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 50%;">Tên Tác Giả</th>
                        <th style="width: 20%;">Số lượng Sách</th>
                        <th style="width: 20%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authors as $author): ?>
                    <tr>
                        <td><?= $author['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($author['name']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= $author['book_count'] ?></span>
                        </td>
                        <td>
                            <a href="edit.php?id=<?= $author['id'] ?>" class="btn btn-sm btn-warning mb-1" title="Sửa">
                                <i class="bi-pencil-square"></i> Sửa
                            </a>
                            <a href="delete.php?id=<?= $author['id'] ?>" 
                               onclick="return confirm('Bạn có chắc muốn xóa tác giả \'<?= htmlspecialchars($author['name']) ?>\' không? Cảnh báo: Việc này có thể ảnh hưởng đến dữ liệu sách.')" 
                               class="btn btn-sm btn-danger mb-1" title="Xóa">
                                <i class="bi-trash"></i> Xóa
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