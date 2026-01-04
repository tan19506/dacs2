<?php 
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

// 1. CẤU HÌNH PHÂN TRANG & TÌM KIẾM
$limit = 10; 
$current_page = max(1, (int)($_GET['page'] ?? 1));
$search_keyword = trim($_GET['search'] ?? '');

// Xây dựng điều kiện tìm kiếm an toàn
$where_sql = "";
$params = [];
$types = "";

if ($search_keyword !== '') {
    $where_sql = " WHERE name LIKE ? ";
    $params[] = "%$search_keyword%";
    $types .= "s";
}

// 2. TÍNH TOÁN TỔNG SỐ TRANG
$count_query = "SELECT COUNT(*) as total FROM authors" . $where_sql;
$stmt_c = $conn->prepare($count_query);
if ($types) $stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$total_authors = $stmt_c->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_authors / $limit);
$start = ($current_page - 1) * $limit;

// 3. TRUY VẤN DANH SÁCH TÁC GIẢ (Sử dụng JOIN để tối ưu hiệu suất)
$sql = "SELECT a.id, a.name, COUNT(ba.book_id) as book_count 
        FROM authors a
        LEFT JOIN book_author ba ON a.id = ba.author_id
        $where_sql
        GROUP BY a.id 
        ORDER BY a.name ASC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$final_types = $types . "ii";
$final_params = array_merge($params, [$limit, $start]);
$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$authors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../layouts/header.php'; 
?>

<div class="container py-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold text-dark mb-0">
                <i class="bi bi-people-fill text-primary me-2"></i>Quản lý Tác giả
            </h2>
            <p class="text-muted mb-0">Tổng cộng có <?= $total_authors ?> tác giả trong hệ thống</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="add.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Thêm tác giả mới
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group border rounded-pill overflow-hidden bg-light">
                        <span class="input-group-text bg-transparent border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-0 bg-transparent py-2" 
                               placeholder="Tìm kiếm tác giả theo tên..." value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-dark rounded-pill">Tìm kiếm</button>
                </div>
            </form>
        </div>
    </div>

    <?php display_session_message(); ?>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase">
                    <tr>
                        <th class="ps-4 py-3" style="font-size: 0.8rem; width: 80px;">ID</th>
                        <th class="py-3" style="font-size: 0.8rem;">Họ và Tên</th>
                        <th class="py-3 text-center" style="font-size: 0.8rem;">Số lượng sách</th>
                        <th class="py-3 text-end pe-4" style="font-size: 0.8rem;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($authors)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Không tìm thấy tác giả nào phù hợp.</td></tr>
                    <?php else: ?>
                        <?php foreach ($authors as $author): ?>
                        <tr>
                            <td class="ps-4 text-muted small">#<?= $author['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($author['name']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill <?= $author['book_count'] > 0 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> px-3">
                                    <?= $author['book_count'] ?> tác phẩm
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    <a href="edit.php?id=<?= $author['id'] ?>" class="btn btn-white btn-sm border" title="Chỉnh sửa">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $author['id'] ?>" class="btn btn-white btn-sm border" 
                                       onclick="return confirm('Xóa tác giả này có thể làm ảnh hưởng đến dữ liệu sách liên quan. Bạn chắc chắn chứ?')" title="Xóa">
                                        <i class="bi bi-trash text-danger"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center pagination-sm">
            <?php 
            $url_params = !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '';
            ?>
            <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link rounded-circle me-2" href="?page=<?= $current_page - 1 ?><?= $url_params ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                    <a class="page-link rounded-circle mx-1" href="?page=<?= $i ?><?= $url_params ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link rounded-circle ms-2" href="?page=<?= $current_page + 1 ?><?= $url_params ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<style>
    .page-link { width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; color: #555; border: none; }
    .page-item.active .page-link { background-color: #0d6efd; color: white; }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
</style>

<?php include '../layouts/footer.php'; ?>