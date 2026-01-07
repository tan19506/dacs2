<?php
require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Admin
require_admin();

include __DIR__ . '/../layouts/header.php';

// Kiểm tra kết nối PDO
$pdo_error = '';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo_error = '<div class="alert alert-danger container mt-5 shadow-sm">Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra lại file connect.php.</div>';
}

// Hàm lấy số lượng thống kê an toàn
function get_count($pdo, $table, $where = '', $params = []) {
    if (!$pdo) return 0;
    try {
        $sql = "SELECT COUNT(*) FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE " . $where;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Lỗi thống kê ($table): " . $e->getMessage());
        return 0;
    }
}

// Lấy số liệu thống kê
$total_books  = get_count($pdo, 'books');
$total_users  = get_count($pdo, 'users');
$current_loans = get_count($pdo, 'loans', "status = ?", ['Borrowed']);
?>

<style>
    .admin-card { transition: all 0.3s ease; border: none !important; }
    .admin-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .stat-icon { opacity: 0.3; position: absolute; right: 20px; bottom: 10px; font-size: 4rem; }
</style>

<div class="container my-5">
    <?= $pdo_error; ?> 
    <?= display_session_message(); ?>

    <div class="bg-white p-5 rounded-4 shadow-lg">
        <div class="text-center mb-5">
            <h1 class="display-5 text-primary fw-bold">
                <i class="bi bi-person-badge me-2"></i> Bảng Điều Khiển Quản Trị
            </h1>
            <p class="lead text-muted">Chào mừng trở lại, <span class="fw-bold text-success"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>.</p>
        </div>
        
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card bg-primary text-white admin-card h-100 position-relative overflow-hidden">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase small opacity-75">Tổng Số Sách</h5>
                        <p class="display-5 fw-bold mb-0"><?= number_format($total_books) ?></p>
                        <i class="bi bi-book stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark admin-card h-100 position-relative overflow-hidden">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase small opacity-75">Sách Đang Mượn</h5>
                        <p class="display-5 fw-bold mb-0"><?= number_format($current_loans) ?></p>
                        <i class="bi bi-arrow-left-right stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white admin-card h-100 position-relative overflow-hidden">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase small opacity-75">Thành Viên</h5>
                        <p class="display-5 fw-bold mb-0"><?= number_format($total_users) ?></p>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5 opacity-25">

        <div class="row g-4">
            <?php
            $modules = [
                ['title' => 'Quản Lý Sách', 'icon' => 'bi-journal-bookmark-fill', 'color' => 'text-primary', 'link' => '/Books/list.php', 'desc' => 'Kho tài liệu.'],
                ['title' => 'Tác Giả', 'icon' => 'bi-pen-fill', 'color' => 'text-warning', 'link' => '/Authors/list.php', 'desc' => 'Thông tin người viết.'],
                ['title' => 'Danh Mục', 'icon' => 'bi-tags-fill', 'color' => 'text-danger', 'link' => '/Categories/list.php', 'desc' => 'Phân loại chủ đề.'],
                ['title' => 'Người Dùng', 'icon' => 'bi-people-fill', 'color' => 'text-info', 'link' => '/Users/list.php', 'desc' => 'Thành viên & Quyền.'],
                ['title' => 'Mượn & Trả', 'icon' => 'bi-clock-history', 'color' => 'text-success', 'link' => '/Loans/list.php', 'desc' => 'Lịch sử giao dịch.']
            ];

            foreach ($modules as $mod): ?>
            <div class="col-md-4 col-lg-2.4" style="flex: 0 0 auto; width: 20%;">
                <a href="<?= $mod['link'] ?>" class="text-decoration-none text-dark">
                    <div class="card h-100 admin-card text-center py-4 bg-light shadow-sm">
                        <div class="card-body">
                            <i class="bi <?= $mod['icon'] ?> <?= $mod['color'] ?> mb-3" style="font-size: 2.5rem;"></i>
                            <h6 class="fw-bold mb-1"><?= $mod['title'] ?></h6>
                            <p class="small text-muted mb-0"><?= $mod['desc'] ?></p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>