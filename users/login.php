<?php

require_once '../functions.php';
start_session_if_not_started();

require_once '../connect.php'; 

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Vui lòng điền đầy đủ tên đăng nhập và mật khẩu.";
    } else {
        // Sử dụng Prepared Statement để lấy thông tin user
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công, lưu session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role']; 
            
            // Chuyển hướng tùy theo vai trò
            if ($user['role'] === 'admin') {
                header('Location: /Books/list.php'); // Chuyển hướng Admin về trang quản lý sách
            } else {
                header('Location: /index.php'); // Chuyển hướng User về trang chủ
            }
            exit();
        } else {
            $message = "Tên đăng nhập hoặc mật khẩu không đúng.";
        }
    }
}
?>
<?php include '../layouts/header.php'; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-success text-white text-center">
                    <h2>Đăng Nhập</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-danger"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên Đăng Nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật Khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Đăng Nhập</button>
                        <p class="mt-3 text-center">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../layouts/footer.php'; ?>