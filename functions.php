<?php
// Bắt đầu bộ đệm đầu ra (Output Buffering) để tránh lỗi "headers already sent"
// Điều này VÔ CÙNG QUAN TRỌNG để cho phép hàm header() (dùng cho redirect) hoạt động
ob_start();

// Mã bí mật Admin (Dùng để tạo Admin đầu tiên qua Register Form)
const ADMIN_SECRET_CODE = 'dacs2admin_secret'; 

// 1. Hàm khởi động Session an toàn
function start_session_if_not_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Hàm chuyển hướng an toàn.
 * Luôn gọi exit; sau khi chuyển hướng để dừng script.
 * @param string $url URL đích
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Đặt thông báo flash vào session
 * @param string $message Nội dung thông báo
 * @param string $type Loại thông báo (success, danger, warning, info)
 */
function set_session_message($message, $type = 'info') {
    start_session_if_not_started();
    $_SESSION['flash_message'] = [
        'content' => $message,
        'type' => $type
    ];
}

/**
 * Hiển thị và xóa thông báo flash (session message).
 * Đã đổi tên từ show_session_message() thành display_session_message().
 *
 * @return string Mã HTML của thông báo hoặc chuỗi rỗng
 */
function display_session_message() {
    start_session_if_not_started();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['content'];
        $type = $_SESSION['flash_message']['type'];
        
        // Chọn icon Bootstrap phù hợp
        $icon = match ($type) {
            'success' => 'check-circle-fill',
            'danger' => 'x-octagon-fill',
            'warning' => 'exclamation-triangle-fill',
            default => 'info-circle-fill',
        };

        // Tạo mã HTML cho alert Bootstrap
        $output = '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show container mt-3" role="alert">
                      <i class="bi-' . $icon . ' me-2"></i>
                      ' . htmlspecialchars($message) . '
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                   </div>';
        
        // Xóa thông báo khỏi session
        unset($_SESSION['flash_message']);
        
        return $output;
    }
    return '';
}

// 2. Hàm kiểm tra và yêu cầu quyền Admin (Đã có logic kiểm tra thực tế)
function require_admin() {
    start_session_if_not_started();
    
    // Kiểm tra session user_id và role
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
        // Đặt thông báo lỗi và chuyển hướng người dùng không có quyền
        set_session_message('Bạn không có quyền truy cập vào trang quản trị.', 'danger');
        redirect('../index.php');
    }
}

// 3. Hàm kiểm tra xem người dùng đã đăng nhập hay chưa
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}

// 4. Hàm lấy vai trò hiện tại
function get_user_role() {
    start_session_if_not_started();
    return $_SESSION['role'] ?? 'guest'; // Mặc định là khách
}

// 5. Hàm định dạng ngày tháng từ yyyy-mm-dd sang dd/mm/yyyy
function format_date(string $date): string 
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    // Chuyển đổi từ YYYY-MM-DD sang timestamp, sau đó sang DD/MM/YYYY
    return date('d/m/Y', strtotime($date));
}
