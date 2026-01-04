<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Không cho phép Admin tự xóa chính mình
    if ($user_id == $_SESSION['user_id']) {
        set_session_message('Bạn không thể tự xóa tài khoản của mình khi đang đăng nhập.', 'danger');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            set_session_message('Đã xóa người dùng thành công.', 'success');
        } catch (Exception $e) {
            set_session_message('Lỗi khi xóa người dùng: ' . $e->getMessage(), 'danger');
        }
    }
}
redirect('/Users/list.php');