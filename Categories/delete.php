<?php 

require_once '../functions.php';
require_admin(); // YÊU CẦU QUYỀN ADMIN

include '../connect.php'; 

// Kiểm tra ID và bảo mật
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID không hợp lệ!");
}
$id = (int) $_GET["id"];

// Dùng Prepared Statement để xóa dữ liệu
$stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: list.php?success=delete");
    exit();
} else {
    // Nếu xóa thất bại (ví dụ: có sách đang sử dụng danh mục này), hiển thị lỗi
    die("Lỗi khi xóa danh mục: " . $conn->error);
}
?>