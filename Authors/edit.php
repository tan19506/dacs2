<?php

require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

include '../layouts/header.php';

$author_id = (int)($_GET['id'] ?? 0);
if ($author_id <= 0) {
    set_session_message('ID tác giả không hợp lệ.', 'danger');
    redirect('list.php');
}

$error = '';
$name = '';

// BƯỚC 1: Lấy thông tin tác giả hiện tại
$stmt_get = $conn->prepare("SELECT name FROM authors WHERE id = ?");
$stmt_get->bind_param("i", $author_id);
$stmt_get->execute();
$author_data = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if (!$author_data) {
    set_session_message('Không tìm thấy tác giả này.', 'danger');
    redirect('list.php');
}

$name = $author_data['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name']);

    if (empty($new_name)) {
        $error = 'Tên tác giả không được để trống.';
    } else {
        // Kiểm tra xem tên mới đã tồn tại cho tác giả khác chưa
        $stmt_check = $conn->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $new_name, $author_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = 'Tên tác giả này đã được sử dụng bởi tác giả khác.';
        } else {
            // Cập nhật tác giả
            $stmt_update = $conn->prepare("UPDATE authors SET name = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_name, $author_id);
            
            if ($stmt_update->execute()) {
                set_session_message('Cập nhật tác giả <strong>' . htmlspecialchars($new_name) . '</strong> thành công!', 'success');
                redirect('list.php');
            } else {
                $error = 'Lỗi CSDL khi cập nhật tác giả: ' . $conn->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
    // Cập nhật lại biến $name để giữ giá trị người dùng nhập trong trường hợp có lỗi
    $name = $new_name;
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="mb-4 text-warning">
                <i class="bi-pencil-square me-2"></i> Chỉnh Sửa Tác Giả: <?= htmlspecialchars($author_data['name']) ?>
            </h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" action="edit.php?id=<?= $author_id ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Tên Tác Giả:</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="list.php" class="btn btn-secondary">
                                <i class="bi-arrow-left-circle me-1"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="bi-check-lg me-1"></i> Lưu Thay Đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>