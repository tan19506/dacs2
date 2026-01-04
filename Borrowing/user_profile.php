<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';

require_admin();

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    set_session_message('ID người dùng không hợp lệ.', 'danger');
    header('Location: list.php');
    exit;
}

try {
    // 1. Lấy thông tin chi tiết người dùng
    $stmt_user = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = :id");
    $stmt_user->execute(['id' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        set_session_message('Không tìm thấy người dùng.', 'danger');
        header('Location: list.php');
        exit;
    }

    // 2. Lấy lịch sử mượn trả
    $sql_borrowings = "
        SELECT b.*, t.title AS book_title 
        FROM borrowings b
        JOIN books t ON b.book_id = t.id
        WHERE b.user_id = :user_id
        ORDER BY b.borrow_date DESC
    ";
    $stmt = $pdo->prepare($sql_borrowings);
    $stmt->execute(['user_id' => $user_id]);
    $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Tính toán thống kê
    $stats = [
        'total' => count($borrowings),
        'pending' => 0,
        'overdue' => 0
    ];
    foreach ($borrowings as $b) {
        if ($b['status'] === 'borrowed') $stats['pending']++;
        if ($b['status'] === 'overdue' || ($b['status'] === 'borrowed' && strtotime($b['due_date']) < time())) {
            $stats['overdue']++;
        }
    }

} catch (PDOException $e) {
    die("Lỗi CSDL: " . $e->getMessage());
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="container py-5">
    <div class="row mb-4 align-items-end">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="list.php">Người dùng</a></li>
                    <li class="breadcrumb-item active">Hồ sơ mượn sách</li>
                </ol>
            </nav>
            <h2 class="fw-bold"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($user['username']) ?></h2>
            <p class="text-muted mb-0">Email: <?= htmlspecialchars($user['email']) ?> | Thành viên từ: <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
        </div>
        <div class="col-auto">
            <a href="list.php" class="btn btn-outline-secondary rounded-pill shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Quay lại
            </a>
        </div>
    </div>

    
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary text-white p-3 rounded-4">
                <small class="opacity-75">Tổng lượt mượn</small>
                <h3 class="fw-bold mb-0"><?= $stats['total'] ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-warning text-dark p-3 rounded-4">
                <small class="opacity-75">Đang cầm sách</small>
                <h3 class="fw-bold mb-0"><?= $stats['pending'] ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger text-white p-3 rounded-4">
                <small class="opacity-75">Vi phạm quá hạn</small>
                <h3 class="fw-bold mb-0"><?= $stats['overdue'] ?></h3>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow rounded-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Chi tiết lịch sử mượn trả</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Sách</th>
                        <th>Ngày mượn</th>
                        <th>Hạn trả</th>
                        <th>Thực tế</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($borrowings)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có lịch sử mượn.</td></tr>
                    <?php else: ?>
                        <?php foreach ($borrowings as $b): 
                            $is_late = ($b['status'] === 'borrowed' && strtotime($b['due_date']) < time());
                        ?>
                        <tr class="<?= $is_late ? 'table-danger' : '' ?>">
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($b['book_title']) ?></div>
                                <small class="text-muted">ID: #<?= $b['id'] ?></small>
                            </td>
                            <td><?= date('d/m/Y', strtotime($b['borrow_date'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($b['due_date'])) ?></td>
                            <td><?= $b['return_date'] ? date('d/m/Y', strtotime($b['return_date'])) : '<span class="badge bg-light text-dark">--</span>' ?></td>
                            <td>
                                <?php if ($is_late): ?>
                                    <span class="badge bg-danger rounded-pill">Quá hạn</span>
                                <?php elseif ($b['status'] === 'borrowed'): ?>
                                    <span class="badge bg-warning text-dark rounded-pill">Đang mượn</span>
                                <?php else: ?>
                                    <span class="badge bg-success rounded-pill">Đã trả</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($b['status'] === 'borrowed'): ?>
                                    <a href="../loans/return.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-dark rounded-pill shadow-sm" onclick="return confirm('Xác nhận trả cuốn sách này?')">
                                        Trả sách
                                    </a>
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

<?php include __DIR__ . '/../layouts/footer.php'; ?>