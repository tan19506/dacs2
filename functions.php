<?php
// functions.php
ob_start();

const ADMIN_SECRET_CODE = 'dacs2admin_secret';

// 1. Khởi động Session
function start_session_if_not_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// 2. Chuyển hướng
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// 3. Quản lý thông báo Flash
function set_session_message($message, $type = 'info') {
    start_session_if_not_started();
    $_SESSION['flash_message'] = [
        'content' => $message,
        'type' => $type
    ];
}

function display_session_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);

        // Thêm id="session-alert" để JavaScript tìm thấy
        return '<div id="session-alert" class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm border-0" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>' . $message . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    return '';
}

// 4. Phân quyền
function require_admin() {
    start_session_if_not_started();
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
        set_session_message('Bạn không có quyền truy cập vào trang này.', 'danger');
        redirect('/index.php');
    }
}

// 5. Định dạng dữ liệu
function format_date($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '1970-01-01') return '---';
    return date('d/m/Y', strtotime($date));
}

/**
 * 6. Hàm xử lý Upload ảnh bìa sách
 * Trả về tên file mới hoặc false nếu lỗi
 */
function upload_cover($file_input) {
    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) return false;

    $target_dir = __DIR__ . "/../uploads/covers/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_ext = strtolower(pathinfo($file_input['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = "cover_" . uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file_input['tmp_name'], $target_file)) {
            return "/uploads/covers/" . $new_filename;
        }
    }
    return false;
}

//7. Kiểm tra trạng thái quá hạn

function check_overdue($due_date, $status) {
    if ($status === 'Returned') return false;
    return strtotime($due_date) < strtotime(date('Y-m-d'));
}

// 8. Hàm kiểm tra xem người dùng đã đăng nhập hay chưa
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}

// 9. Hàm lấy vai trò hiện tại
function get_user_role() {
    start_session_if_not_started();
    return $_SESSION['role'] ?? 'guest'; // Mặc định là khách
}