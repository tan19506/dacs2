<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php';
include __DIR__ . '/../layouts/header.php';

if (get_user_role() === 'admin') {
    set_session_message("Admin quản lý mượn trả tại đây.", "info");
    header('Location: ../admin/loans.php');
    exit;
}

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $sql = "SELECT l.*, b.title, b.cover 
            FROM loans l 
            JOIN books b ON l.book_id = b.id 
            WHERE l.user_id = :user_id 
            ORDER BY l.loan_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $my_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi: " . $e->getMessage());
}
?>

<div class="container my-5">
    <h2 class="fw-bold mb-4"><i class="bi bi-clock-history me-2"></i>Lịch sử mượn sách của tôi</h2>
    
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Tên sách</th>
                        <th>Ngày mượn</th>
                        <th>Hạn trả</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_loans as $loan): 
                        // CHUẨN HÓA LOGIC TRẠNG THÁI
                        // Chuyển về viết thường để so sánh chính xác nếu DB lưu 'Returned' hoặc 'returned'
                        $status = strtolower($loan['status']);
                        $is_overdue = (strtotime($loan['due_date']) < time() && $status !== 'Returned');
                    ?>
                    <tr class="<?= $is_overdue ? 'table-light-danger' : '' ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="<?= htmlspecialchars($loan['cover'] ?: '../assets/no-cover.png') ?>" 
                                     style="width: 45px; height: 65px; object-fit: cover;" 
                                     class="rounded shadow-sm me-3">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($loan['title']) ?></div>
                                    <small class="text-muted">Mã đơn: #<?= $loan['id'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></td>
                        <td>
                            <div class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                <?= date('d/m/Y', strtotime($loan['due_date'])) ?>
                            </div>
                            <?php if ($is_overdue): ?>
                                <small class="text-danger" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle"></i> Đã quá hạn</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status === 'returned'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">
                                    <i class="bi bi-check-circle me-1"></i> Đã trả sách
                                </span>
                            <?php elseif ($is_overdue): ?>
                                <span class="badge bg-danger rounded-pill px-3">Quá hạn</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3">
                                    Đang mượn
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($loan['status'] == 'borrowed'): ?>
                                <a href="process_return.php?id=<?= $loan['id'] ?>" 
                                class="btn btn-sm btn-primary rounded-pill px-3"
                                onclick="return confirm('Bạn xác nhận muốn trả cuốn sách này?')">
                                    <i class="bi bi-arrow-left-right me-1"></i> Trả sách
                                </a>
                            <?php elseif ($loan['status'] == 'returning'): ?>
                                <span class="badge bg-info text-dark rounded-pill">Đang xử lý trả...</span>
                            <?php else: ?>
                                <span class="text-muted small">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($my_loans)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Bạn chưa có giao dịch mượn sách nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .table-light-danger { background-color: #fff8f8; }
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>