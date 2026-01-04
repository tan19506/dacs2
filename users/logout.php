<?php
require_once __DIR__ . '/../functions.php';

start_session_if_not_started();

// 1. Lưu thông báo vào session trước khi hủy (tùy chọn)
// Tuy nhiên, vì chúng ta hủy toàn bộ session, thông báo nên được đặt SAU khi khởi tạo session mới
// hoặc sử dụng biến tạm. Cách đơn giản nhất là redirect kèm thông báo qua hàm có sẵn.

// 2. Xóa tất cả biến session
$_SESSION = array();

// 3. Xóa session cookie trên trình duyệt
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hủy session trên server
session_destroy();

// 5. Khởi tạo session mới chỉ để hiển thị thông báo thành công
start_session_if_not_started();
set_session_message("Bạn đã đăng xuất thành công. Hẹn gặp lại!", "info");

// 6. Chuyển hướng về trang chủ bằng hàm đã viết
redirect('/index.php');