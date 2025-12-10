<?php

require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

$author_id = (int)($_GET['id'] ?? 0);
if ($author_id <= 0) {
    set_session_message('ID tác giả không hợp lệ.', 'danger');
    redirect('list.php');
}

// BƯỚC 1: Lấy tên tác giả để hiển thị trong thông báo
$stmt_get = $conn->prepare("SELECT name FROM authors WHERE id = ?");
$stmt_get->bind_param("i", $author_id);
$stmt_get->execute();
$author_data = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if (!$author_data) {
    set_session_message('Không tìm thấy tác giả cần xóa.', 'danger');
    redirect('list.php');
}

$author_name = $author_data['name'];

// BƯỚC 2: Xóa liên kết trong bảng trung gian (book_author)
// Việc này là cần thiết để tránh lỗi khóa ngoại trước khi xóa tác giả
$stmt_link = $conn->prepare("DELETE FROM book_author WHERE author_id = ?");
$stmt_link->bind_param("i", $author_id);
$stmt_link->execute();
$stmt_link->close();


// BƯỚC 3: Xóa tác giả khỏi bảng authors
$stmt_delete = $conn->prepare("DELETE FROM authors WHERE id = ?");
$stmt_delete->bind_param("i", $author_id);

if ($stmt_delete->execute()) {
    set_session_message('Đã xóa tác giả <strong>' . htmlspecialchars($author_name) . '</strong> thành công!', 'warning');
} else {
    set_session_message('Lỗi CSDL khi xóa tác giả: ' . $conn->error, 'danger');
}
$stmt_delete->close();

redirect('list.php');
?>