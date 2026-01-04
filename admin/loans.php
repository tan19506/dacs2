<?php
require_once '../functions.php';
require_once '../connect.php';

// Bảo mật: Chỉ Admin mới được truy cập
require_admin(); 

$message = display_session_message();

try {
    // Truy vấn lấy toàn bộ danh sách mượn sách
    // Kết hợp JOIN để lấy tên sách và tên người mượn
    $sql = "
        SELECT 
            l.id, l.loan_date, l.due_date, l.return_date, l.status,
            b.title AS book_title, b.cover AS book_cover,
            u.username AS borrower_name, u.email AS borrower_email
        FROM loans l
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.user_id = u.id
        ORDER BY 
            CASE 
                WHEN l.status = 'Borrowed' AND l.due_date < CURRENT_DATE THEN 1 -- Ưu tiên quá hạn lên đầu
                WHEN l.status = 'Borrowed' THEN 2
                ELSE 3 
            END, 
            l.loan_date DESC
    ";
    $stmt = $pdo->query($sql);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Lỗi truy vấn: ' . $e->getMessage() . '</div>';
    $loans = [];
}

include '../layouts/header.php';
?>

<div class="container-fluid py-5 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Quản lý Giao dịch Mượn/Trả</h2>
            <p class="text-muted">Theo dõi và xác nhận việc lưu thông sách trong hệ thống</p>
        </div>
        <div class="d-flex gap-2">
             <button class="btn btn-outline-secondary rounded-pill shadow-sm" onclick="window.location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Làm mới
            </button>
        </div>
    </div>

    <?= $message ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary uppercase small">
                    <tr>
                        <th class="ps-4">Thông tin Sách</th>
                        <th>Độc giả</th>
                        <th>Thời hạn</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Chưa có giao dịch nào được ghi nhận.</td></tr>
                    <?php else: ?>
                        <?php foreach ($loans as $loan): 
                            $is_overdue = ($loan['status'] === 'Borrowed' && strtotime($loan['due_date']) < time());
                            $status_class = $loan['status'] === 'Returned' ? 'bg-success-subtle text-success' : ($is_overdue ? 'bg-danger text-white pulse-danger' : 'bg-warning-subtle text-warning-emphasis');
                        ?>
                        <tr class="<?= $is_overdue ? 'table-danger-light' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($loan['book_cover'] ?: '../assets/no-cover.png') ?>" 
                                         class="rounded shadow-sm me-3" style="width: 45px; height: 65px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-primary mb-0"><?= htmlspecialchars($loan['book_title']) ?></div>
                                        <small class="text-muted">Mã đơn: #<?= $loan['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($loan['borrower_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($loan['borrower_email']) ?></div>
                            </td>
                            <td>
                                <div class="small">Ngày mượn: <strong><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></strong></div>
                                <div class="small">Hạn trả: <strong class="<?= $is_overdue ? 'text-danger' : '' ?>"><?= date('d/m/Y', strtotime($loan['due_date'])) ?></strong></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 <?= $status_class ?>">
                                    <?= $loan['status'] === 'Returned' ? 'Đã trả' : ($is_overdue ? 'QUÁ HẠN' : 'Đang mượn') ?>
                                </span>
                                <?php if($loan['status'] === 'Returned'): ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">Trả ngày: <?= date('d/m/Y', strtotime($loan['return_date'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($loan['status'] === 'Borrowed'): ?>
                                    <a href="../loans/return.php?loan_id=<?= $loan['id'] ?>" 
                                       class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm"
                                       onclick="return confirm('Xác nhận độc giả đã trả sách?')">
                                        <i class="bi bi-arrow-return-left me-1"></i> Thu hồi sách
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>Hoàn tất</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .table-danger-light { background-color: #fff5f5 !important; }
    .pulse-danger { animation: pulse 2s infinite; border: 1px solid #dc3545; }
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .bg-gradient-primary { background: linear-gradient(45deg, #4e73df, #224abe); }
</style>

<script>
    // Tự động đóng thông báo sau 4 giây
    setTimeout(() => {
        let alert = document.querySelector('.alert');
        if (alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 4000);
</script>

<?php include '../layouts/footer.php'; ?>