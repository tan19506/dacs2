<?php
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

$author_id = (int)($_GET['id'] ?? 0);

// Kiểm tra sự tồn tại của tác giả trước khi bắt đầu
$stmt_get = $conn->prepare("SELECT name FROM authors WHERE id = ?");
$stmt_get->bind_param("i", $author_id);
$stmt_get->execute();
$author_data = $stmt_get->get_result()->fetch_assoc();


if (!$author_data) {
    set_session_message('Không tìm thấy tác giả này.', 'danger');
    header('Location: list.php');
    exit();
}

$error = '';
$name = $author_data['name']; // Giá trị mặc định

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
    $error = 'Tên tác giả không được để trống.';
} else {
    try {
        // 1. Kiểm tra trùng tên (trừ ID hiện tại)
        $sql_check = "SELECT COUNT(*) FROM authors WHERE name = :name AND id != :id";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([
            ':name' => $name,
            ':id'   => $author_id
        ]);
        
        // Sử dụng fetchColumn để lấy số lượng trực tiếp
        if ($stmt_check->fetchColumn() > 0) {
            $error = "Tên tác giả " . htmlspecialchars($name) . " đã tồn tại trong hệ thống.";
        } else {
            // 2. Cập nhật dữ liệu
            $sql_update = "UPDATE authors SET name = :name WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            
            // Thực thi cập nhật
            if ($stmt_update->execute([':name' => $name, ':id' => $author_id])) {
                set_session_message("Cập nhật tác giả thành công!", "success");
                header('Location: list.php');
                exit();
            } else {
                $error = 'Lỗi hệ thống: Không thể cập nhật dữ liệu.';
            }
        }
    } catch (PDOException $e) {
        // Bắt lỗi CSDL và hiển thị thông báo an toàn
        $error = 'Lỗi CSDL: ' . $e->getMessage();
    }
    
    // Trong PDO, chỉ cần gán null để đóng statement (không bắt buộc nhưng tốt cho bộ nhớ)
    $stmt_check = null;
    $stmt_update = null;
}
}

include '../layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="list.php" class="text-decoration-none">Tác giả</a></li>
                    <li class="breadcrumb-item active">Chỉnh sửa</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="bg-warning py-3 px-4">
                    <h4 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-pencil-square me-2"></i> Chỉnh sửa thông tin
                    </h4>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <i class="bi bi-exclamation-circle me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label for="name" class="form-label small text-uppercase fw-bold text-muted">Họ và Tên Tác Giả</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control bg-light border-start-0 py-2" 
                                       id="name" name="name" value="<?= htmlspecialchars($name) ?>" 
                                       placeholder="Ví dụ: Paulo Coelho" required>
                            </div>
                            <div class="form-text mt-2">Đảm bảo tên tác giả chính xác để việc tìm kiếm sách dễ dàng hơn.</div>
                        </div>

                        <hr class="my-4 opacity-50">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning flex-grow-1 py-2 fw-bold shadow-sm">
                                <i class="bi bi-save me-2"></i> Cập nhật ngay
                            </button>
                            <a href="list.php" class="btn btn-outline-secondary px-4 py-2">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded-3 border d-flex align-items-center">
                <div class="flex-shrink-0 bg-white p-2 rounded-circle shadow-sm me-3">
                    <i class="bi bi-info-circle text-primary fs-4"></i>
                </div>
                <div>
                    <p class="mb-0 small text-muted">Lưu ý: Thay đổi tên tác giả sẽ tự động cập nhật tên hiển thị cho tất cả sách liên quan của tác giả này.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>