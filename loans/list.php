<?php
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

$message = display_session_message();

try {
    // Tự động cập nhật trạng thái quá hạn trong CSDL trước khi hiển thị (Tùy chọn)
    // Hoặc xử lý logic hiển thị như dưới đây:
    
    $sql = "
        SELECT 
            l.id, l.loan_date, l.due_date, l.return_date, l.status,
            b.title AS book_title,
            u.username AS borrower_username, u.email AS borrower_email
        FROM loans l
        JOIN books b ON l.book_id = b.id
        JOIN users u ON l.user_id = u.id
        ORDER BY 
            CASE 
                WHEN l.return_date IS NULL AND l.due_date < CURRENT_DATE THEN 1 -- Ưu tiên quá hạn thực tế
                WHEN l.status = 'borrowed' THEN 2
                ELSE 3
            END,
            l.loan_date DESC
    ";
    $stmt = $pdo->query($sql);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Lỗi: ' . $e->getMessage() . '</div>';
    $loans = [];
}

include '../layouts/header.php'; 
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Giao dịch Mượn/Trả</h2>
            <p class="text-muted small">Quản lý việc lưu thông sách trong thư viện</p>
        </div>
        <a href="borrow.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="bi bi-plus-lg me-2"></i>Cho mượn sách
        </a>
    </div>

    <?= $message ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Thông tin mượn</th>
                        <th>Độc giả</th>
                        <th>Thời hạn</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): 
                        $today = strtotime(date('Y-m-d'));
                        $due_date = strtotime($loan['due_date']);
                        $is_returned = !empty($loan['return_date']);
                        $real_overdue = (!$is_returned && $due_date < $today);
                    ?>
                    <tr class="<?= $real_overdue ? 'table-light-danger' : '' ?>">
                        <td class="ps-4">
                            <div class="fw-bold text-primary"><?= htmlspecialchars($loan['book_title']) ?></div>
                            <small class="text-muted">Mã đơn: #<?= $loan['id'] ?></small>
                        </td>
                        <td>
                            <div class="small fw-bold"><?= htmlspecialchars($loan['borrower_username']) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($loan['borrower_email']) ?></div>
                        </td>
                        <td>
                            <div class="small">Mượn: <?= date('d/m/Y', strtotime($loan['loan_date'])) ?></div>
                            <div class="small fw-bold <?= $real_overdue ? 'text-danger' : 'text-success' ?>">
                                Hạn: <?= date('d/m/Y', $due_date) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($is_returned): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Đã trả</span>
                                <div class="text-muted small" style="font-size: 0.7rem;">Ngày trả: <?= date('d/m/Y', strtotime($loan['return_date'])) ?></div>
                            <?php elseif ($real_overdue): ?>
                                <span class="badge bg-danger rounded-pill px-3 shadow-sm pulse-danger">Quá hạn</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3">Đang mượn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if (!$is_returned): ?>
                                <a href="return.php?loan_id=<?= $loan['id'] ?>" 
                                   class="btn btn-sm btn-dark rounded-pill px-3"
                                   onclick="return confirm('Xác nhận độc giả đã trả sách?')">
                                    Thu hồi sách
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>Hoàn tất</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .table-light-danger { background-color: #fff5f5; }
    .pulse-danger { animation: pulse 2s infinite; }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tìm thông báo
    const alertBox = document.getElementById('session-alert');
    
    if (alertBox) {
        // Sau 3 giây (3000ms) sẽ bắt đầu ẩn
        setTimeout(function() {
            // Sử dụng hiệu ứng mờ dần của Bootstrap
            alertBox.classList.remove('show');
            alertBox.classList.add('fade');
            
            // Sau khi mờ thì xóa hẳn khỏi giao diện
            setTimeout(() => {
                alertBox.remove();
            }, 600);
        }, 3000);
    }
});
</script>
<?php include '../layouts/footer.php'; ?>