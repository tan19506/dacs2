<?php
require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

// Đảm bảo session đã chạy
start_session_if_not_started();

// 1. KIỂM TRA ĐIỀU KIỆN TIÊN QUYẾT
if (!is_logged_in()) {
    set_session_message('Vui lòng đăng nhập để mượn sách.', 'danger');
    redirect('/users/login.php');
}

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    set_session_message('Sách không hợp lệ.', 'danger');
    redirect('/index.php');
}

$book_id = intval($_GET['book_id']);
$user_id = $_SESSION['user_id'];
// Link quay lại trang danh sách hoặc chi tiết
$redirect_url = '/Books/details.php?id=' . $book_id; 

try {
    // 2. KIỂM TRA TÌNH TRẠNG SÁCH
    $sql_book = "SELECT title, quantity FROM books WHERE id = :book_id";
    $stmt_book = $pdo->prepare($sql_book);
    $stmt_book->execute(['book_id' => $book_id]);
    $book = $stmt_book->fetch();

    if (!$book) {
        set_session_message('Sách không tồn tại.', 'danger');
        redirect('/index.php');
    }

    if ($book['quantity'] <= 0) {
        set_session_message('Sách "' . htmlspecialchars($book['title']) . '" hiện đã hết trong kho.', 'warning');
        redirect($redirect_url);
    }

    // 3. KIỂM TRA XEM ĐANG MƯỢN CUỐN NÀY CHƯA
    // Lưu ý: status = 'Borrowed' (Viết hoa chữ B cho đúng Database)
    $sql_check = "SELECT id FROM loans WHERE book_id = :book_id AND user_id = :user_id AND status = 'Borrowed'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['book_id' => $book_id, 'user_id' => $user_id]);
    
    if ($stmt_check->rowCount() > 0) {
        set_session_message('Bạn đang mượn cuốn này rồi, vui lòng trả trước khi mượn tiếp.', 'warning');
        redirect($redirect_url);
    }

    // 4. THỰC HIỆN GIAO DỊCH (TRANSACTION)
    $pdo->beginTransaction();

    // Tính ngày trả (14 ngày sau) - Chỉ lấy định dạng DATE (Y-m-d)
    $due_date = date('Y-m-d', strtotime('+14 days'));

    // 4.1 Thêm vào bảng loans
    $sql_loan = "INSERT INTO loans (book_id, user_id, loan_date, due_date, status) 
                 VALUES (:book_id, :user_id, CURRENT_DATE, :due_date, 'Borrowed')";
    $stmt_loan = $pdo->prepare($sql_loan);
    $stmt_loan->execute([
        'book_id'  => $book_id, 
        'user_id'  => $user_id, 
        'due_date' => $due_date
    ]);

    // 4.2 Cập nhật giảm số lượng trong kho
    // Thêm điều kiện quantity > 0 để tránh lỗi đồng thời (race condition)
    $sql_update_book = "UPDATE books SET quantity = quantity - 1 WHERE id = :book_id AND quantity > 0";
    $stmt_update = $pdo->prepare($sql_update_book);
    $stmt_update->execute(['book_id' => $book_id]);

    if ($stmt_update->rowCount() === 0) {
        // Nếu không có dòng nào được update nghĩa là sách vừa bị người khác mượn hết
        throw new Exception("Sách vừa hết hàng trong tích tắc!");
    }

    $pdo->commit();
    set_session_message('Mượn sách "' . htmlspecialchars($book['title']) . '" thành công. Hạn trả: ' . format_date($due_date), 'success');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi mượn sách: " . $e->getMessage());
    set_session_message('Lỗi: ' . $e->getMessage(), 'danger');
}

redirect($redirect_url);