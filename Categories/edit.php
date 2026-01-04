<?php 
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

// 1. Kiểm tra ID và lấy dữ liệu cũ
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_session_message("ID không hợp lệ!", "danger");
    header("Location: list.php");
    exit();
}

$stmt_select = $conn->prepare("SELECT id, name, description FROM categories WHERE id=?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$category = $stmt_select->get_result()->fetch_assoc();

if (!$category) {
    set_session_message("Không tìm thấy danh mục!", "danger");
    header("Location: list.php");
    exit();
}

$error_message = '';
// Giữ lại dữ liệu người dùng nhập nếu có lỗi xảy ra
$name = $category['name'];
$description = $category['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST["name"] ?? '');
    $description = trim($_POST["description"] ?? '');

    if (empty($name)) {
        $error_message = "Tên danh mục không được để trống.";
    } else {
        // 2. Cập nhật dữ liệu
        $stmt_update = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
        $stmt_update->bind_param("ssi", $name, $description, $id);
        
        if ($stmt_update->execute()) {
            set_session_message("Cập nhật danh mục '<strong>$name</strong>' thành công!", "success");
            header("Location: list.php");
            exit();
        } else {
            if ($conn->errno == 1062) {
                $error_message = "Tên danh mục này đã tồn tại, vui lòng chọn tên khác.";
            } else {
                $error_message = "Lỗi hệ thống: " . $conn->error;
            }
        }
    }
}

include '../layouts/header.php'; 
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="d-flex align-items-center mb-4">
                <a href="list.php" class="btn btn-outline-secondary btn-sm rounded-circle me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 class="mb-0 fw-bold">Sửa Danh mục</h2>
            </div>

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">Đang chỉnh sửa: <span class="badge bg-light text-dark border">ID #<?= $id ?></span></p>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <i class="bi bi-exclamation-circle me-2"></i> <?= $error_message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Tên danh mục <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($name) ?>" required autofocus>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">Mô tả chi tiết</label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Nhập mô tả cho danh mục này..."><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2 fw-bold">
                                <i class="bi bi-check-lg me-1"></i> Lưu thay đổi
                            </button>
                            <a href="list.php" class="btn btn-link text-muted text-decoration-none">Hủy bỏ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>