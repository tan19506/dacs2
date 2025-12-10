<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

// Yêu cầu quyền ADMIN
require_admin(); 

// Khởi tạo biến
$errors = [];
$name = ''; // Tên danh mục

// --- BƯỚC 2: XỬ LÝ FORM SUBMISSION (LOGIC & REDIRECTION) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy và làm sạch dữ liệu
    $name = trim($_POST['name'] ?? '');

    // 2. Validation
    if (empty($name)) {
        $errors[] = "Tên danh mục không được để trống.";
    }

    // 3. Nếu không có lỗi, tiến hành INSERT DỮ LIỆU
    if (empty($errors)) {
        try {
            // Chuẩn bị câu lệnh SQL
            $sql = "INSERT INTO categories (name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            
            // Kiểm tra lỗi prepare
            if ($stmt === false) {
                 throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
            }
            
            // Bind và thực thi
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();

            // Đặt thông báo thành công
            set_session_message("Đã thêm danh mục '{$name}' thành công!", 'success');
            
            // Dòng CHUYỂN HƯỚNG THÀNH CÔNG (Dòng 22 cũ được thực thi ở đây)
            header('Location: list.php'); 
            exit(); 

        } catch (Exception $e) {
            $errors[] = "Lỗi CSDL khi thêm danh mục: " . $e->getMessage();
        }
    }
}

// --- BƯỚC 3: HIỂN THỊ FORM (BẮT ĐẦU OUTPUT HTML) ---

// Include Header
include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4 display-6 fw-bold text-primary">
        <i class="bi-plus-circle-fill me-2"></i> Thêm Danh Mục Mới
    </h1>

    <?php 
    // Hiển thị lỗi nếu có
    if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm">
            <h5 class="alert-heading"><i class="bi-exclamation-triangle-fill me-2"></i> Lỗi xảy ra:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="add.php" method="POST">

                <!-- Tên Danh mục -->
                <div class="mb-4">
                    <label for="name" class="form-label fw-bold">Tên Danh Mục</label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" 
                           value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <!-- Nút Submit -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3">
                    <a href="list.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                        <i class="bi-arrow-left-circle me-2"></i> Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">
                        <i class="bi-save-fill me-2"></i> Lưu Danh Mục
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>