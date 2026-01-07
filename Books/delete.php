<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

require_admin(); 
start_session_if_not_started();

// 1. Kiểm tra ID sách
$book_id = (int)($_GET['id'] ?? 0);
if ($book_id <= 0) {
    set_session_message("ID sách không hợp lệ.", "danger");
    header('Location: list.php');
    exit();
}

// 2. KIỂM TRA RÀNG BUỘC: Sách có đang được mượn không?
$check_loan = $conn->prepare("SELECT COUNT(*) as count FROM loans WHERE book_id = ? AND status = 'borrowed'");
$check_loan->bind_param("i", $book_id);
$check_loan->execute();
$is_borrowed = $check_loan->get_result()->fetch_assoc()['count'];

if ($is_borrowed > 0) {
    set_session_message("Không thể xóa! Cuốn sách này đang có người mượn.", "warning");
    header('Location: list.php');
    exit();
}

// 3. THỰC HIỆN XÓA (Transaction)
$conn->begin_transaction();
try {
    // Lấy thông tin ảnh trước khi xóa bản ghi
    $stmt = $conn->prepare("SELECT cover FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();

    // Xóa liên kết tác giả (Bảng phụ trước)
    $stmt_ba = $conn->prepare("DELETE FROM book_author WHERE book_id = ?");
    $stmt_ba->bind_param("i", $book_id);
    $stmt_ba->execute();

    // Xóa sách (Bảng chính sau)
    $stmt_b = $conn->prepare("DELETE FROM books WHERE id = ?");
    $stmt_b->bind_param("i", $book_id);
    $stmt_b->execute();

    // 4. Hoàn tất và xóa file
    $conn->commit();

    if (!empty($book['cover'])) {
        $full_path = __DIR__ . '/..' . $book['cover'];
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
    }

    set_session_message("Đã xóa sách thành công.", "success");

} catch (Exception $e) {
    $conn->rollback();
    set_session_message("Lỗi hệ thống: " . $e->getMessage(), "danger");
}

header('Location: list.php');
exit();