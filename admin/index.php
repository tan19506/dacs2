<?php

require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php';

// BƯỚC QUAN TRỌNG: Kiểm tra và yêu cầu quyền Admin
// Đây là file index của Admin, phải đảm bảo người dùng có vai trò 'admin'
require_admin();

include __DIR__ . '/../layouts/header.php';

// Kiểm tra kết nối PDO để tránh lỗi trên giao diện
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Nếu kết nối PDO thất bại (như lỗi bạn gặp trước đó)
    // Hiển thị thông báo thân thiện hơn trên giao diện
    $pdo_error = '<div class="alert alert-danger container mt-5">Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu (PDO). Vui lòng kiểm tra file connect.php và trạng thái MySQL.</div>';
} else {
    $pdo_error = '';
}

// Hàm lấy số lượng thống kê nhanh
function get_count($pdo, $table, $where = '') {
    try {
        // Thực hiện truy vấn với điều kiện WHERE nếu có
        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE " . $where;
        }
        $stmt = $pdo->query($sql);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        // Ghi lại lỗi CSDL và trả về thông báo lỗi trên giao diện
        error_log("Lỗi thống kê DB: " . $e->getMessage());
        return 'Lỗi DB';
    }
}

// Lấy số liệu thống kê nếu kết nối thành công
$total_books = $pdo ? get_count($pdo, 'books') : 0;
$total_users = $pdo ? get_count($pdo, 'users') : 0;
// Đếm số hồ sơ đang ở trạng thái 'borrowed'
$current_loans = $pdo ? get_count($pdo, 'loans', "status = 'borrowed'") : 0;
?>

<div class="container my-5">
    <?= $pdo_error; ?> 
    <?= display_session_message(); // Hiển thị thông báo sau khi chuyển hướng ?>

    <div class="jumbotron bg-white p-5 rounded-4 shadow-lg text-center">
        <h1 class="display-4 text-primary fw-bold">
            <i class="bi-person-gear me-2"></i> Bảng Điều Khiển Quản Trị
        </h1>
        <p class="lead mt-4 mb-5">Chào mừng, <span class="fw-bold text-success"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>. Đây là khu vực quản lý tài nguyên thư viện.</p>
        
        <hr class="my-4">

        <!-- THỐNG KÊ NHANH -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card bg-info text-white shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi-book me-2"></i> Tổng Số Sách</h5>
                        <p class="display-6 fw-bold"><?= htmlspecialchars($total_books) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi-arrow-left-right me-2"></i> Đang Mượn</h5>
                        <p class="display-6 fw-bold"><?= htmlspecialchars($current_loans) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-secondary text-white shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi-people me-2"></i> Tổng Người Dùng</h5>
                        <p class="display-6 fw-bold"><?= htmlspecialchars($total_users) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CÁC CHỨC NĂNG QUẢN LÝ -->
        <div class="row g-4">
            
            <!-- Quản lý Sách -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <i class="bi-book-fill text-info" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3 fw-bold">Quản Lý Sách</h5>
                        <p class="card-text text-muted">Thêm, sửa, xóa, và xem danh sách các cuốn sách.</p>
                        <a href="/Books/list.php" class="btn btn-info mt-2 text-white fw-bold rounded-pill">
                            <i class="bi-arrow-right-circle-fill"></i> Xem Ngay
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quản lý Tác Giả -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <i class="bi-people-fill text-warning" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3 fw-bold">Quản Lý Tác Giả</h5>
                        <p class="card-text text-muted">Cập nhật thông tin về tác giả và người đóng góp.</p>
                        <a href="/Authors/list.php" class="btn btn-warning mt-2 text-dark fw-bold rounded-pill">
                            <i class="bi-arrow-right-circle-fill"></i> Xem Ngay
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quản lý Danh Mục -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <i class="bi-folder-fill text-danger" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3 fw-bold">Quản Lý Danh Mục</h5>
                        <p class="card-text text-muted">Thêm, sửa, xóa các danh mục sách.</p>
                        <a href="/Categories/list.php" class="btn btn-danger mt-2 text-white fw-bold rounded-pill">
                            <i class="bi-arrow-right-circle-fill"></i> Xem Ngay
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- MỤC MỚI: Quản lý Người dùng -->
            <div class="col-md-6 mt-4">
                <div class="card h-100 border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <i class="bi-person-badge-fill text-primary" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3 fw-bold">Quản Lý Người Dùng</h5>
                        <p class="card-text text-muted">Xem danh sách, chỉnh sửa thông tin và cấp/thu hồi quyền người dùng.</p>
                        <a href="/Users/list.php" class="btn btn-primary mt-2 text-white fw-bold rounded-pill">
                            <i class="bi-arrow-right-circle-fill"></i> Xem Ngay
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>