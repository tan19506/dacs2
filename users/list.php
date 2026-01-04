<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Admin
require_admin();

$page_title = "Quản Lý Người Dùng";

try {
    if ($pdo === null) throw new Exception('Không thể kết nối CSDL.');
    
    // Lấy danh sách người dùng, sắp xếp Admin lên đầu
    $stmt = $pdo->query("SELECT id, username, role, email FROM users ORDER BY role ASC, username ASC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    set_session_message('Lỗi: ' . $e->getMessage(), 'danger');
    $users = [];
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary fw-bold"><i class="bi bi-people-fill me-2"></i><?= $page_title ?></h1>
        <div>
            <a href="/Admin/index.php" class="btn btn-outline-secondary rounded-pill me-2">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </div>

    <?= display_session_message() ?>

    <?php if (empty($users)): ?>
        <div class="alert alert-light text-center border rounded-4 py-5 shadow-sm">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <p class="mt-3">Chưa có người dùng nào được đăng ký.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Người dùng</th>
                            <th>Email</th>
                            <th>Vai Trò</th>
                            <th class="text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $user['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-2">
                                        <i class="bi bi-person text-primary"></i>
                                    </div>
                                    <span class="fw-bold"><?= htmlspecialchars($user['username']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 rounded-pill">Quản trị viên</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 rounded-pill">Người dùng</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group shadow-sm rounded-pill bg-white p-1">
                                    <a href="/Loans/user_history.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-light border-0 text-dark" title="Lịch sử mượn trả">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                    <a href="/Users/edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-light border-0 text-info" title="Sửa thông tin">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-light border-0 text-danger" 
                                            onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-light border-0 text-muted" disabled title="Bạn không thể tự xóa mình">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form id="deleteForm" method="POST" action="/Users/delete_process.php">
            <input type="hidden" name="user_id" id="userIdToDelete">
        </form>

        <script>
        function confirmDelete(userId, username) {
            if (confirm('Bạn có chắc muốn xóa "' + username + '"?\nTất cả lịch sử mượn sách liên quan sẽ bị xóa vĩnh viễn.')) {
                document.getElementById('userIdToDelete').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
        </script>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>