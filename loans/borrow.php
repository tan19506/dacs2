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
    set_session_message('Vui lòng đăng nhập để thực hiện chức năng mượn sách.', 'danger');
    header('Location: /users/login.php');
    exit;
}

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    set_session_message('ID sách không hợp lệ.', 'danger');
    header('Location: /index.php');
    exit;
}

$book_id = intval($_GET['book_id']);
$user_id = $_SESSION['user_id'];
$redirect_url = '/Books/details.php?id=' . $book_id; // URL chi tiết sách để quay lại

// 2. KIỂM TRA TÌNH TRẠNG SÁCH VÀ HỒ SƠ MƯỢN CŨ
try {
    // 2.1 Lấy thông tin sách
    $sql_book = "SELECT title, quantity FROM books WHERE id = :book_id";
    $stmt_book = $pdo->prepare($sql_book);
    $stmt_book->execute(['book_id' => $book_id]);
    $book = $stmt_book->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        set_session_message('Sách này không tồn tại.', 'danger');
        header('Location: /index.php');
        exit;
    }

    if ($book['quantity'] <= 0) {
        set_session_message('Xin lỗi, sách "' . htmlspecialchars($book['title']) . '" hiện đã hết hàng.', 'danger');
        header('Location: ' . $redirect_url);
        exit;
    }

    // 2.2 Kiểm tra người dùng đã mượn sách này chưa
    $sql_check = "SELECT id FROM loans WHERE book_id = :book_id AND user_id = :user_id AND status = 'borrowed'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['book_id' => $book_id, 'user_id' => $user_id]);
    
    if ($stmt_check->rowCount() > 0) {
        set_session_message('Bạn đã mượn sách "' . htmlspecialchars($book['title']) . '" và chưa trả.', 'warning');
        header('Location: ' . $redirect_url);
        exit;
    }

    // 3. THỰC HIỆN GIAO DỊCH MƯỢN SÁCH
    $pdo->beginTransaction();

    // Tính ngày đáo hạn (14 ngày)
    $due_date = date('Y-m-d H:i:s', strtotime('+14 days'));

    // 3.1 Ghi nhận hồ sơ mượn vào bảng loans
    $sql_loan = "INSERT INTO loans (book_id, user_id, due_date, status) VALUES (:book_id, :user_id, :due_date, 'borrowed')";
    $stmt_loan = $pdo->prepare($sql_loan);
    $stmt_loan->execute([
        'book_id' => $book_id, 
        'user_id' => $user_id, 
        'due_date' => $due_date
    ]);

    // 3.2 Giảm số lượng sách trong bảng books
    $sql_update_book = "UPDATE books SET quantity = quantity - 1 WHERE id = :book_id";
    $stmt_update = $pdo->prepare($sql_update_book);
    $stmt_update->execute(['book_id' => $book_id]);

    // 4. KẾT THÚC GIAO DỊCH
    $pdo->commit();
    set_session_message('Bạn đã mượn sách "' . htmlspecialchars($book['title']) . '" thành công! Vui lòng trả sách trước ngày ' . date('d/m/Y', strtotime($due_date)) . '.', 'success');

} catch (PDOException $e) {
    // Nếu có lỗi, rollback transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi mượn sách: " . $e->getMessage());
    set_session_message('Có lỗi xảy ra trong quá trình mượn sách. Vui lòng thử lại.', 'danger');
}

// Chuyển hướng về trang chi tiết sách
header('Location: ' . $redirect_url);
exit;