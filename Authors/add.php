<?php
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

$name = '';
$error = '';

// --- XỬ LÝ LOGIC (SERVER-SIDE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

   if (empty($name)) {
    $error = 'Tên tác giả không được để trống.';
} else {
    try {
        // 1. Kiểm tra tồn tại (Sử dụng PDO và fetchColumn)
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM authors WHERE name = :name");
        $stmt_check->execute([':name' => $name]);
        
        if ($stmt_check->fetchColumn() > 0) {
            $error = "Tên tác giả " . htmlspecialchars($name) . " đã tồn tại.";
        } else {
            // 2. Thêm mới
            $stmt = $pdo->prepare("INSERT INTO authors (name) VALUES (:name)");
            
            if ($stmt->execute([':name' => $name])) {
                set_session_message("Đã thêm tác giả " . htmlspecialchars($name) . " thành công!", 'success');
                header('Location: list.php');
                exit();
            } else {
                $error = 'Lỗi hệ thống: Không thể lưu dữ liệu.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
    }
}
}

// --- HIỂN THỊ GIAO DIỆN (HTML) ---
include '../layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="list.php" class="text-decoration-none">Tác giả</a></li>
                    <li class="breadcrumb-item active">Thêm mới</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-header bg-success py-3">
                    <h5 class="card-title mb-0 text-white fw-bold text-center">
                        <i class="bi bi-person-plus-fill me-2"></i> THÊM TÁC GIẢ MỚI
                    </h5>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label for="name" class="form-label fw-bold text-muted small text-uppercase">Họ và Tên Tác Giả</label>
                            <input type="text" 
                                   class="form-control form-control-lg bg-light border-0 shadow-none" 
                                   id="name" name="name" 
                                   value="<?= htmlspecialchars($name) ?>" 
                                   required autofocus>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill shadow-sm">
                                <i class="bi bi-check-circle me-1"></i> Xác nhận thêm
                            </button>
                            <a href="list.php" class="btn btn-link text-decoration-none text-muted">Hủy và quay lại</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>