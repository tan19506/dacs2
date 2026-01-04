<?php

require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

// KIỂM TRA KẾT NỐI PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    set_session_message('Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu (PDO). Vui lòng kiểm tra file connect.php.', 'danger');
    header('Location: /index.php');
    exit;
}

// 1. KIỂM TRA ĐIỀU KIỆN TIÊN QUYẾT
if (!is_logged_in()) {
    set_session_message('Vui lòng đăng nhập.', 'danger');
    header('Location: /users/login.php');
    exit;
}

$raw_loan_id = null;
if (isset($_GET['loan_id']) && is_numeric($_GET['loan_id'])) {
    $raw_loan_id = $_GET['loan_id'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Một số link dùng `id` thay vì `loan_id` — hỗ trợ cả hai
    $raw_loan_id = $_GET['id'];
}

if ($raw_loan_id === null) {
    set_session_message('ID hồ sơ mượn không hợp lệ.', 'danger');
    header('Location: /index.php');
    exit;
}

$loan_id = intval($raw_loan_id);
$current_user_id = $_SESSION['user_id'];
$is_admin = get_user_role() === 'admin';
$redirect_url = $is_admin ? 'list.php' : '/index.php'; // Chuyển hướng khác nhau cho Admin/User

// 2. LẤY THÔNG TIN HỒ SƠ MƯỢN
try {
    $sql_loan = "SELECT book_id, user_id FROM loans WHERE id = :loan_id AND status = 'Borrowed'";
    $stmt_loan = $pdo->prepare($sql_loan);
    $stmt_loan->execute(['loan_id' => $loan_id]);
    $loan = $stmt_loan->fetch(PDO::FETCH_ASSOC);

    $using_borrowings_table = false;

    // Nếu không tìm thấy trong bảng `loans`, thử trong bảng `borrowings` (một số nơi trong project dùng borrowings)
    if (!$loan) {
        $sql_b = "SELECT id, book_id, user_id FROM borrowings WHERE id = :loan_id AND status = 'Borrowed'";
        $stmt_b = $pdo->prepare($sql_b);
        $stmt_b->execute(['loan_id' => $loan_id]);
        $brow = $stmt_b->fetch(PDO::FETCH_ASSOC);
        if ($brow) {
            $loan = $brow;
            $using_borrowings_table = true;
        }
    }

    if (!$loan) {
        set_session_message('Hồ sơ mượn không tồn tại hoặc sách đã được trả.', 'danger');
        header('Location: ' . $redirect_url);
        exit;
    }

    // Kiểm tra quyền: Chỉ người mượn hoặc Admin mới được phép trả/xác nhận
    if ($loan['user_id'] != $current_user_id && !$is_admin) {
        set_session_message('Bạn không có quyền trả hồ sơ mượn này.', 'danger');
        header('Location: ' . $redirect_url);
        exit;
    }

    $book_id = $loan['book_id'];

    // 3. THỰC HIỆN GIAO DỊCH TRẢ SÁCH
    $pdo->beginTransaction();

    // 3.1 Cập nhật hồ sơ mượn: status = 'returned', return_date
    if ($using_borrowings_table) {
        $sql_update_loan = "UPDATE borrowings SET status = 'returned', return_date = NOW() WHERE id = :loan_id";
    } else {
        $sql_update_loan = "UPDATE loans SET status = 'returned', return_date = NOW() WHERE id = :loan_id";
    }
    $stmt_update_loan = $pdo->prepare($sql_update_loan);
    $stmt_update_loan->execute(['loan_id' => $loan_id]);

    // 3.2 Tăng số lượng sách trong bảng books
    $sql_update_book = "UPDATE books SET quantity = quantity + 1 WHERE id = :book_id";
    $stmt_update_book = $pdo->prepare($sql_update_book);
    $stmt_update_book->execute(['book_id' => $book_id]);

    // 4. KẾT THÚC GIAO DỊCH
    $pdo->commit();
    
    // Lấy tên sách để thông báo
    $sql_book_title = "SELECT title FROM books WHERE id = :book_id";
    $stmt_book_title = $pdo->prepare($sql_book_title);
    $stmt_book_title->execute(['book_id' => $book_id]);
    $book_title = $stmt_book_title->fetch(PDO::FETCH_COLUMN) ?? 'Sách';
    
        $message = 'Bạn đã trả sách "' . htmlspecialchars($book_title) . '" thành công! Cảm ơn bạn.';
        if ($is_admin) {
            $message = 'Admin đã xác nhận trả sách "' . htmlspecialchars($book_title) . '" thành công cho người dùng ID: ' . $loan['user_id'] . '.';
        }

        // Sửa thứ tự tham số: set_session_message(message, type)
        set_session_message($message, 'success');

} catch (PDOException $e) {
    // Nếu có lỗi, rollback transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi trả sách: " . $e->getMessage());
    set_session_message('Có lỗi xảy ra trong quá trình trả sách. Vui lòng thử lại.', 'danger');
}

// Chuyển hướng
header('Location: ' . $redirect_url);
exit;