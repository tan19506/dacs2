<?php

require_once '../functions.php';
start_session_if_not_started();

require_once '../connect.php'; 

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $secret_code = $_POST['secret_code'] ?? '';
    
    if (empty($username) || empty($password) || empty($email)) {
        $message = "Vui lòng điền đầy đủ các trường.";
    } elseif (strlen($password) < 6) {
        $message = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        // 1. Xác định Vai trò
        $final_role = 'user';
        // Sử dụng hằng số ADMIN_SECRET_CODE từ functions.php
        if (!empty($secret_code) && $secret_code === ADMIN_SECRET_CODE) { 
            $final_role = 'admin';
        }

        try {
            // 2. Kiểm tra Username hoặc Email đã tồn tại chưa
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $count = $stmt_check->get_result()->fetch_row()[0];

            if ($count > 0) {
                $message = "Tên đăng nhập hoặc Email đã được sử dụng.";
            } else {
                // 3. Hash mật khẩu và Chèn dữ liệu
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $email, $final_role);
                
                if ($stmt->execute()) {
                    $message = "Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.";
                    // Chuyển hướng người dùng đến trang đăng nhập
                    header('Location: login.php?register=success');
                    exit();
                } else {
                    $message = "Đã xảy ra lỗi trong quá trình đăng ký: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $message = "Lỗi CSDL: " . $e->getMessage();
        }
    }
}
?>
<?php include '../layouts/header.php'; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h2>Đăng Ký Tài Khoản</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="register.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên Đăng Nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật Khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="secret_code" class="form-label text-danger">Mã Admin (Tùy chọn)</label>
                            <input type="text" class="form-control" id="secret_code" name="secret_code">
                            <small class="text-muted">Nhập mã để đăng ký với quyền Quản trị.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Đăng Ký</button>
                        <p class="mt-3 text-center">Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../layouts/footer.php'; ?>