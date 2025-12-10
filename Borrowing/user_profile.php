<?php

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';

// BƯỚC 1: XÁC THỰC VÀ LẤY ID NGƯỜI DÙNG
// Chỉ Admin mới có quyền xem hồ sơ của người khác
require_admin();

// Lấy ID người dùng từ tham số GET trên URL (Ví dụ: user_profile.php?id=5)
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Kiểm tra tính hợp lệ của ID
if (!$user_id) {
    set_session_message('ID người dùng không hợp lệ.', 'danger');
    // Chuyển hướng về trang danh sách người dùng
    header('Location: /Users/list.php');
    exit;
}

// ----------------------------------------------------------------------
// BƯỚC 2: TRUY VẤN TÊN NGƯỜI DÙNG VÀ DANH SÁCH MƯỢN SÁCH
// ----------------------------------------------------------------------

$username = 'Người dùng không tìm thấy';
$borrowings = [];

try {
    // Check if database connection is established
    if ($pdo === null) {
        throw new PDOException('Database connection failed');
    }
    
    // 2.1. Lấy Tên Người Dùng
    $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmt_user->execute(['id' => $user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
 
    if ($user_data) {
        $username = $user_data['username'];
    } else {
        set_session_message('Không tìm thấy người dùng có ID: ' . $user_id, 'danger');
        header('Location: /Users/list.php');
        exit;
    }

    // 2.2. Lấy Danh Sách Mượn Sách Của Người Dùng
    // JOIN với bảng books để lấy tiêu đề sách (title)
    $sql_borrowings = "
        SELECT
            b.id AS borrowing_id,
            t.title AS book_title,
            b.borrow_date,
            b.due_date,
            b.return_date,
            b.status
        FROM borrowings b
        JOIN books t ON b.book_id = t.id
        WHERE b.user_id = :user_id
        ORDER BY b.borrow_date DESC
    ";

    $stmt_borrowings = $pdo->prepare($sql_borrowings);
    $stmt_borrowings->execute(['user_id' => $user_id]);
    $borrowings = $stmt_borrowings->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    set_session_message('Lỗi truy vấn CSDL: ' . $e->getMessage(), 'danger');
    // Chuyển hướng về trang danh sách khi có lỗi CSDL
    header('Location: /Users/list.php');
    exit;
}

$page_title = "Hồ Sơ Mượn Sách của " . htmlspecialchars($username);

// ----------------------------------------------------------------------
// BƯỚC 3: HIỂN THỊ GIAO DIỆN
// ----------------------------------------------------------------------

include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <h1 class="text-primary mb-3">Hồ Sơ Mượn Sách</h1>
    <h3 class="mb-4">
        Người dùng: <span class="badge bg-dark p-2 rounded-pill"><?= htmlspecialchars($username) ?> (ID: <?= $user_id ?>)</span>
    </h3>

    <!-- Hiển thị thông báo (thành công, lỗi) -->
    <?= display_session_message() ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <!-- Quay lại Dashboard -->
        <a href="/Users/list.php" class="btn btn-secondary rounded-pill">
            <i class="bi-arrow-left-circle-fill"></i> Quay lại Danh sách Người dùng
        </a>
    </div>

    <?php if (empty($borrowings)): ?>
        <!-- Nếu không có hồ sơ mượn -->
        <div class="alert alert-info text-center rounded-4 shadow-sm">
            Người dùng này hiện chưa có hồ sơ mượn sách nào.
        </div>
    <?php else: ?>
        <!-- Bảng danh sách mượn sách -->
        <div class="table-responsive bg-white rounded-4 shadow p-4">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID Mượn</th>
                        <th>Tên Sách</th>
                        <th>Ngày Mượn</th>
                        <th>Ngày Hạn Trả</th>
                        <th>Ngày Trả Thực Tế</th>
                        <th>Trạng Thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($borrowings as $b): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($b['borrowing_id']) ?></td>
                            <td><?= htmlspecialchars($b['book_title']) ?></td>
                            <td><?= format_date($b['borrow_date']) ?></td>
                            <td><?= format_date($b['due_date']) ?></td>
                            <td>
                                <?php 
                                    if (!empty($b['return_date'])) {
                                        echo format_date($b['return_date']);
                                    } else {
                                        echo '<span class="text-muted">Chưa trả</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $status = htmlspecialchars($b['status']);
                                    $badge_class = 'bg-secondary'; // Mặc định
                                    $display_text = $status;

                                    if ($status === 'borrowed') {
                                        $badge_class = 'bg-warning text-dark';
                                        $display_text = 'Đang Mượn';
                                    } elseif ($status === 'returned') {
                                        $badge_class = 'bg-success';
                                        $display_text = 'Đã Trả';
                                    } elseif ($status === 'overdue') {
                                        $badge_class = 'bg-danger';
                                        $display_text = 'Quá Hạn';
                                    }
                                ?>
                                <span class="badge <?= $badge_class ?> p-2"><?= $display_text ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>