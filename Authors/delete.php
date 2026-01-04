<?php
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

$author_id = (int)($_GET['id'] ?? 0);
if ($author_id <= 0) {
    set_session_message('ID tác giả không hợp lệ.', 'danger');
    header('Location: list.php');
    exit();
}

// BƯỚC 1: Kiểm tra sự tồn tại của tác giả và lấy tên
$stmt_get = $conn->prepare("SELECT name FROM authors WHERE id = ?");
$stmt_get->bind_param("i", $author_id);
$stmt_get->execute();
$author = $stmt_get->get_result()->fetch_assoc();


if (!$author) {
    set_session_message('Tác giả không tồn tại hoặc đã bị xóa.', 'danger');
    header('Location: list.php');
    exit();
}

// BƯỚC 2: Sử dụng Transaction để đảm bảo an toàn dữ liệu
$conn->begin_transaction();

try {
    // 2.1 Xóa liên kết trong bảng trung gian (Many-to-Many relationship)
    $stmt_link = $conn->prepare("DELETE FROM book_author WHERE author_id = ?");
    $stmt_link->bind_param("i", $author_id);
    $stmt_link->execute();

    // 2.2 Xóa bản ghi chính trong bảng authors
    $stmt_delete = $conn->prepare("DELETE FROM authors WHERE id = ?");
    $stmt_delete->bind_param("i", $author_id);
    $stmt_delete->execute();

    // Nếu mọi thứ ổn, lưu thay đổi vào CSDL
    $conn->commit();
    set_session_message("Đã xóa tác giả <strong>{$author['name']}</strong> thành công.", "success");

} catch (Exception $e) {
    // Nếu có lỗi, hoàn tác mọi thay đổi đã thực hiện trước đó
    $conn->rollback();
    set_session_message("Lỗi hệ thống: Không thể xóa tác giả. Vui lòng thử lại sau.", "danger");
}

header('Location: list.php');
exit();