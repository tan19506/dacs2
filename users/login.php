<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

start_session_if_not_started();

// Nếu đã đăng nhập rồi thì không cho vào trang login nữa
if (is_logged_in()) {
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        set_session_message("Vui lòng điền đầy đủ tên đăng nhập và mật khẩu.", "danger");
    } else {
        // Sử dụng $conn (đối tượng PDOWrapper)
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công, lưu session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; 
            
            set_session_message("Chào mừng " . htmlspecialchars($user['username']) . " quay trở lại!", "success");

            // Chuyển hướng tùy theo vai trò
            if ($user['role'] === 'admin') {
                redirect('/Admin/index.php'); // Về Dashboard Admin tổng quát
            } else {
                redirect('/index.php'); // Về trang chủ User
            }
        } else {
            set_session_message("Tên đăng nhập hoặc mật khẩu không chính xác.", "danger");
        }
    }
}

include __DIR__ . '/../layouts/header.php'; 
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <?= display_session_message(); ?>

            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-success text-white text-center py-4">
                    <h2 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Đăng Nhập</h2>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Tên Đăng Nhập</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0 bg-light" id="username" name="username" placeholder="Nhập tên tài khoản..." required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">Mật Khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0 bg-light" id="password" name="password" placeholder="Nhập mật khẩu..." required>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng Nhập
                            </button>
                        </div>
                        <div class="mt-4 text-center">
                            <span class="text-muted">Chưa có tài khoản?</span> 
                            <a href="register.php" class="text-success fw-bold text-decoration-none">Đăng ký ngay</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="/index.php" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Quay lại trang chủ
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>