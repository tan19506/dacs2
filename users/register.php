<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

start_session_if_not_started();

// Nếu đã đăng nhập thì không cần đăng ký nữa
if (is_logged_in()) {
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $secret_code = $_POST['secret_code'] ?? '';
    
    // 1. Kiểm tra tính hợp lệ cơ bản
    if (empty($username) || empty($password) || empty($email)) {
        set_session_message("Vui lòng điền đầy đủ tất cả các trường.", "danger");
    } elseif (strlen($password) < 6) {
        set_session_message("Mật khẩu phải có ít nhất 6 ký tự để đảm bảo an toàn.", "warning");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_session_message("Định dạng Email không hợp lệ.", "warning");
    } else {
        // 2. Xác định Vai trò (Admin secret check)
        $final_role = 'user';
        if (!empty($secret_code) && $secret_code === ADMIN_SECRET_CODE) { 
            $final_role = 'admin';
        }

        try {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            // Truyền tham số trực tiếp vào execute
            $stmt_check->execute([
                'username' => $username,
                'email'    => $email
            ]);

            // Dùng fetchColumn() để lấy ngay giá trị COUNT(*) mà không cần fetch_row
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                set_session_message("Tên đăng nhập hoặc Email này đã tồn tại trong hệ thống.", "danger");
            } else {
                // 4. Mã hóa mật khẩu và Lưu vào Database
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $email, $final_role);
                
                if ($stmt->execute()) {
                    set_session_message("Đăng ký thành công! Chào mừng bạn gia nhập thư viện.", "success");
                    redirect('login.php');
                } else {
                    set_session_message("Lỗi hệ thống khi lưu dữ liệu. Vui lòng thử lại.", "danger");
                }
            }
        } catch (Exception $e) {
            set_session_message("Lỗi kết nối CSDL: " . $e->getMessage(), "danger");
        }
    }
}

include __DIR__ . '/../layouts/header.php'; 
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <?= display_session_message(); ?>

            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h2 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Đăng Ký</h2>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="register.php">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Tên Đăng Nhập</label>
                            <input type="text" class="form-control bg-light" id="username" name="username" 
                                   value="<?= htmlspecialchars($username ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Địa chỉ Email</label>
                            <input type="email" class="form-control bg-light" id="email" name="email" 
                                   value="<?= htmlspecialchars($email ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">Mật Khẩu</label>
                            <input type="password" class="form-control bg-light" id="password" name="password" 
                                   placeholder="Tối thiểu 6 ký tự" required>
                        </div>

                        <div class="mb-4 border-start border-danger border-4 ps-3 py-2 bg-light">
                            <label for="secret_code" class="form-label fw-bold text-danger">Mã Xác Nhận Admin</label>
                            <input type="text" class="form-control border-danger-subtle" id="secret_code" name="secret_code" 
                                   placeholder="Chỉ dành cho Quản trị viên">
                            <div class="form-text">Bỏ trống nếu bạn là người dùng thông thường.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-2"></i>Xác Nhận Đăng Ký
                            </button>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <span class="text-muted">Đã có tài khoản?</span> 
                            <a href="login.php" class="text-primary fw-bold text-decoration-none">Đăng nhập</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>