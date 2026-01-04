<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php';

// Kiểm tra quyền Admin
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        set_session_message("Vui lòng nhập tên danh mục.", "danger");
    } else {
        try {
            // 1. KIỂM TRA XEM TÊN DANH MỤC ĐÃ TỒN TẠI CHƯA
            $sql_check = "SELECT COUNT(*) FROM categories WHERE name = :name";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':name' => $name]);
            
            if ($stmt_check->fetchColumn() > 0) {
                // Nếu đã tồn tại, báo lỗi thay vì để SQL văng lỗi Integrity constraint
                set_session_message("Danh mục '$name' đã tồn tại trong hệ thống. Vui lòng chọn tên khác.", "warning");
            } else {
                // 2. NẾU CHƯA CÓ THÌ MỚI THỰC HIỆN INSERT
                $sql = "INSERT INTO categories (name, description) VALUES (:name, :description)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description
                ]);

                set_session_message("Thêm danh mục thành công!", "success");
                header("Location: list.php");
                exit;
            }
        } catch (PDOException $e) {
            // Trường hợp có lỗi khác phát sinh
            set_session_message("Lỗi hệ thống: " . $e->getMessage(), "danger");
        }
    }
}

include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-folder-plus me-2"></i>Thêm Danh Mục Mới</h5>
                </div>
                <div class="card-body p-4">
                    <?= display_session_message() ?>
                    <form action="add.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tên danh mục</label>
                            <input type="text" name="name" class="form-control" placeholder="Ví dụ: Công nghệ thông tin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mô tả</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Nhập mô tả ngắn gọn..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">Lưu lại</button>
                            <a href="list.php" class="btn btn-outline-secondary px-4">Quay lại</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>