<?php

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';

// BƯỚC QUAN TRỌNG: Kiểm tra và yêu cầu quyền Admin
// Giả định hàm require_admin() và set_session_message() có trong functions.php
require_admin();

// Định nghĩa tiêu đề trang
$page_title = "Quản Lý Người Dùng";

try {
    if ($pdo === null) {
        throw new PDOException('Database connection failed');
    }
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY role DESC, username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Xử lý lỗi CSDL và thiết lập thông báo lỗi
    set_session_message('Lỗi truy vấn cơ sở dữ liệu: Vui lòng kiểm tra lại kết nối CSDL và cấu trúc bảng users.', 'danger');
    $users = []; // Đảm bảo $users là mảng rỗng nếu có lỗi
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <h1 class="text-primary mb-4"><?= $page_title ?></h1>

    <!-- Hiển thị thông báo (thành công, lỗi) -->
    <?= display_session_message() ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <!-- Quay lại Dashboard -->
        <a href="/Admin/index.php" class="btn btn-secondary rounded-pill">
            <i class="bi-arrow-left-circle-fill"></i> Quay lại Dashboard
        </a>
        <!-- Nút Thêm Người Dùng Mới -->
        <a href="/Users/add.php" class="btn btn-success rounded-pill">
            <i class="bi-person-plus-fill"></i> Thêm Người Dùng Mới
        </a>
    </div>

    <?php if (empty($users)): ?>
        <!-- Nếu không có người dùng -->
        <div class="alert alert-warning text-center rounded-4 shadow-sm">
            Hiện chưa có người dùng nào trong hệ thống.
        </div>
    <?php else: ?>
        <!-- Bảng danh sách người dùng -->
        <div class="table-responsive bg-white rounded-4 shadow p-4">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Tên Đăng Nhập</th>
                        <th>Vai Trò</th>
                        <th class="text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <?php 
                                    $role = htmlspecialchars($user['role']);
                                    if ($role === 'admin') {
                                        echo '<span class="badge bg-danger p-2">Quản Trị Viên</span>';
                                    } elseif ($role === 'user') {
                                        echo '<span class="badge bg-primary p-2">Người Dùng</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary p-2">' . $role . '</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-center" style="width: 300px;">
                                
                                <!-- NÚT MỚI: XEM HỒ SƠ MƯỢN -->
                                <a href="/Borrowing/user_profile.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-dark me-2 rounded-pill" title="Xem hồ sơ mượn">
                                    <i class="bi-book-half"></i> Hồ Sơ Mượn
                                </a>

                                <!-- Nút Sửa -->
                                <a href="/Users/edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info text-white me-2 rounded-pill" title="Chỉnh sửa">
                                    <i class="bi-pencil-square"></i> Sửa
                                </a>
                                <!-- Nút Xóa (Dùng JavaScript để xác nhận trước khi xóa) -->
                                <button type="button" class="btn btn-sm btn-danger rounded-pill" onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')" title="Xóa">
                                    <i class="bi-trash-fill"></i> Xóa
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Form Ẩn để thực hiện Xóa -->
        <form id="deleteForm" method="POST" action="/Users/delete.php" style="display: none;">
            <input type="hidden" name="user_id" id="userIdToDelete">
        </form>

        <script>
        /**
         * Hàm xác nhận xóa người dùng
         */
        function confirmDelete(userId, username) {
            // Sử dụng window.confirm tạm thời (nên thay bằng Modal Bootstrap)
            if (window.confirm('Bạn có chắc chắn muốn xóa người dùng "' + username + '" (ID: ' + userId + ')?\nHành động này không thể hoàn tác.')) {
                // Đặt ID vào form ẩn và gửi đi
                document.getElementById('userIdToDelete').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
        </script>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>