<?php 
require_once '../functions.php';
require_once '../connect.php'; 
require_admin();

// 1. Kiểm tra ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_session_message("ID danh mục không hợp lệ!", "danger");
    header("Location: list.php");
    exit();
}

// 2. KIỂM TRÀ RÀNG BUỘC 
// Kiểm tra xem có cuốn sách nào đang thuộc danh mục này không
$check_stmt = $conn->prepare("SELECT COUNT(*) as book_count FROM books WHERE category_id = ?");
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();

if ($result['book_count'] > 0) {
    // Không cho phép xóa nếu danh mục đang chứa sách
    set_session_message("Không thể xóa! Danh mục này đang chứa <strong>{$result['book_count']}</strong> cuốn sách. Hãy chuyển sách sang danh mục khác trước.", "warning");
    header("Location: list.php");
    exit();
}

// 3. THỰC HIỆN XÓA
$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);

try {
    if ($stmt->execute()) {
        set_session_message("Đã xóa danh mục thành công.", "success");
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    set_session_message("Lỗi hệ thống: " . $e->getMessage(), "danger");
}

header("Location: list.php");
exit();