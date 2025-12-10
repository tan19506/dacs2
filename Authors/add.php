<?php

require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

include '../layouts/header.php';

$name = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);

    if (empty($name)) {
        $error = 'Tên tác giả không được để trống.';
    } else {
        // Kiểm tra xem tên tác giả đã tồn tại chưa
        $stmt_check = $conn->prepare("SELECT id FROM authors WHERE name = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = 'Tên tác giả đã tồn tại trong hệ thống.';
        } else {
            // Thêm mới tác giả
            $stmt = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            
            if ($stmt->execute()) {
                set_session_message('Thêm tác giả <strong>' . htmlspecialchars($name) . '</strong> thành công!', 'success');
                redirect('list.php');
            } else {
                $error = 'Lỗi CSDL khi thêm tác giả: ' . $conn->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="mb-4 text-success">
                <i class="bi-plus-circle me-2"></i> Thêm Tác Giả Mới
            </h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" action="add.php">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Tên Tác Giả:</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="list.php" class="btn btn-secondary">
                                <i class="bi-arrow-left-circle me-1"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi-plus-lg me-1"></i> Thêm Tác Giả
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>