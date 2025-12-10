<?php 

require_once '../functions.php';
require_admin(); // YÊU CẦU QUYỀN ADMIN ĐỂ THỰC HIỆN CHỨC NĂNG XÓA
include '../connect.php'; 

// 1. Kiểm tra ID sách hợp lệ
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: list.php?error=invalid_id');
    exit();
}
$book_id = (int)$_GET['id'];
$uploadDir = "../uploads/";

// Bắt đầu Transaction
$conn->begin_transaction();
try {
    // 2. Lấy tên file ảnh bìa cũ để chuẩn bị xóa
    $stmt_fetch_cover = $conn->prepare("SELECT cover FROM books WHERE id = ?");
    $stmt_fetch_cover->bind_param("i", $book_id);
    $stmt_fetch_cover->execute();
    $result_cover = $stmt_fetch_cover->get_result();
    $book_data = $result_cover->fetch_assoc();
    $cover_file = $book_data['cover'] ?? null;
    $stmt_fetch_cover->close();

    // 3. Xóa bản ghi liên kết trong bảng book_author
    $stmt_delete_link = $conn->prepare("DELETE FROM book_author WHERE book_id = ?");
    $stmt_delete_link->bind_param("i", $book_id);
    if (!$stmt_delete_link->execute()) {
        throw new Exception("Lỗi khi xóa liên kết tác giả.");
    }
    $stmt_delete_link->close();

    // 4. Xóa bản ghi sách trong bảng books
    $stmt_delete_book = $conn->prepare("DELETE FROM books WHERE id = ?");
    $stmt_delete_book->bind_param("i", $book_id);
    if (!$stmt_delete_book->execute()) {
        throw new Exception("Lỗi khi xóa sách.");
    }
    $stmt_delete_book->close();

    // 5. Xóa file ảnh bìa trên server (Chỉ thực hiện sau khi CSDL thành công)
    if ($cover_file && is_file($uploadDir . $cover_file)) {
        // Sử dụng @unlink để tránh lỗi hiển thị nếu file không tồn tại
        @unlink($uploadDir . $cover_file);
    }
    
    // 6. Hoàn tất Transaction
    $conn->commit();
    header('Location: list.php?success=delete');
    exit();

} catch (Exception $e) {
    // Hoàn tác nếu có bất kỳ lỗi nào xảy ra
    $conn->rollback();
    // Chuyển hướng về trang danh sách với thông báo lỗi
    header('Location: list.php?error=delete_failed&msg=' . urlencode($e->getMessage()));
    exit();
}
// Nếu không phải POST, người dùng đang truy cập trực tiếp, tự động redirect về list.php
header('Location: list.php');
exit();
?>