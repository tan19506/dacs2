<?php

require_once '../functions.php';
require_once '../connect.php'; 
require_admin(); // YÊU CẦU QUYỀN ADMIN ĐỂ TRUY CẬP TRANG QUẢN LÝ NÀY

// Lấy thông báo session (từ return.php hoặc borrow.php)
$message = display_session_message();

// -----------------------------------------------------------
// BƯỚC 2: TRUY VẤN DỮ LIỆU CÁC GIAO DỊCH MƯỢN
// -----------------------------------------------------------
try {
    if ($pdo === null) {
        throw new PDOException("Kết nối CSDL không có sẵn");
    }
    // Truy vấn tất cả các giao dịch mượn, sắp xếp theo trạng thái và ngày mượn mới nhất
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
                WHEN LOWER(l.status) = 'overdue' THEN 1
                WHEN LOWER(l.status) = 'borrowed' THEN 2
                ELSE 3
            END,
            l.loan_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Lỗi CSDL khi tải danh sách giao dịch: ' . $e->getMessage() . '</div>';
    $loans = [];
}

// -----------------------------------------------------------
// BƯỚC 3: HIỂN THỊ GIAO DIỆN
// -----------------------------------------------------------
include '../layouts/header.php'; 
?>

<div class="container my-5">
    <h1 class="mb-4 display-6 fw-bold text-success">
        <i class="bi-arrow-left-right me-2"></i> Quản Lý Giao Dịch Mượn/Trả
    </h1>
    <?= $message ?>

    <!-- NÚT TẠO PHIẾU MƯỢN MỚI (CHỨC NĂNG MƯỢN) -->
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h5 class="text-muted">Tổng cộng <?= count($loans) ?> giao dịch</h5>
        <a href="../Loans/borrow.php" class="btn btn-success btn-lg rounded-pill shadow-sm">
            <i class="bi-plus-circle-fill me-2"></i> Tạo Phiếu Mượn Mới
        </a>
    </div>

    <?php if (empty($loans)): ?>
        <div class="alert alert-info text-center shadow p-4">
            <i class="bi-info-circle-fill me-2"></i> Hiện tại không có giao dịch mượn/trả nào được ghi nhận.
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white shadow-lg rounded-3">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Tên Sách</th>
                        <th>Người Mượn</th>
                        <th>Ngày Mượn</th>
                        <th>Ngày Đến Hạn</th>
                        <th>Trạng Thái</th>
                        <th class="text-center">Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <?php 
                            $status_lower = strtolower($loan['status'] ?? '');
                            $is_active = in_array($status_lower, ['borrowed', 'overdue']);
                            $is_overdue = $status_lower === 'overdue';

                            // Xác định màu sắc cho trạng thái
                            $status_class = '';
                            $status_text = $loan['status'];
                            if ($is_overdue) {
                                $status_class = 'bg-danger text-white';
                                $status_text = 'QUÁ HẠN!';
                            } elseif ($status_lower === 'borrowed') {
                                $status_class = 'bg-warning text-dark';
                                $status_text = 'Đang Mượn';
                            } else {
                                $status_class = 'bg-success text-white';
                                $status_text = 'Đã Trả';
                            }
                        ?>
                        <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                            <td class="fw-bold">#<?= $loan['id'] ?></td>
                            <td><?= htmlspecialchars($loan['book_title']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($loan['borrower_username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($loan['borrower_email']) ?></small>
                            </td>
                            <td><?= format_date($loan['loan_date']) ?></td>
                            <td class="<?= $is_overdue ? 'fw-bold text-danger' : '' ?>">
                                <?= format_date($loan['due_date']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $status_class ?> p-2 rounded-pill">
                                    <?= $status_text ?>
                                </span>
                                <?php if (strtolower($loan['status']) === 'returned'): ?>
                                    <br><small class="text-muted">Trả ngày: <?= format_date($loan['return_date']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <!-- NÚT TRẢ SÁCH (CHỈ CHO GIAO DỊCH ĐANG MƯỢN HOẶC QUÁ HẠN) -->
                                <?php if ($is_active): ?>
                                    <a href="return.php?loan_id=<?= $loan['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm" title="Xác nhận sách đã được trả">
                                        <i class="bi-journal-arrow-down me-1"></i> Trả sách
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill" disabled>
                                        Hoàn tất
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../layouts/footer.php'; ?>