<?php

require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($book_id <= 0) {
    $message = "ID sách không hợp lệ.";
    $message_type = 'error';
}

// Khởi tạo các biến thông báo và form
$message = '';
$message_type = '';
$book = null;
$users = [];
$borrow_date = date('Y-m-d'); // Mặc định: ngày hôm nay
$due_date = date('Y-m-d', strtotime('+14 days')); // Mặc định: 14 ngày sau

try {
    // Check if database connection is available
    if (!$pdo) {
        throw new Exception("Lỗi: Không thể kết nối cơ sở dữ liệu.");
    }

    // 1. Lấy chi tiết Sách
    $stmt_book = $pdo->prepare("SELECT id, title, quantity FROM books WHERE id = :id");
    $stmt_book->bindParam(':id', $book_id, PDO::PARAM_INT);
    $stmt_book->execute();
    $book = $stmt_book->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        throw new Exception("Không tìm thấy sách với ID: " . htmlspecialchars($book_id));
    }
    
    // 2.2 Lấy danh sách Người dùng để chọn người mượn
    // Nếu user hiện tại là Admin -> cho phép chọn người mượn khác
    $is_admin = (get_user_role() === 'admin');
    if ($is_admin) {
        $stmt_users = $pdo->prepare("SELECT id, username FROM users ORDER BY username");
        $stmt_users->execute();
        $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Nếu không phải admin, chỉ đặt users là chính user đang đăng nhập
        $users = [];
    }

} catch (Exception $e) {
    $message = "Lỗi: " . $e->getMessage();
    $message_type = 'error';
} catch (PDOException $e) {
    $message = "Lỗi CSDL khi tải dữ liệu: " . $e->getMessage();
    $message_type = 'error';
}


// --- XỬ LÝ FORM MƯỢN SÁCH ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    // Lấy dữ liệu từ form
    // Nếu người thao tác không phải admin thì user_id phải là session user
    $is_admin = (get_user_role() === 'admin');
    if ($is_admin) {
        $user_id = $_POST['user_id'] ?? '';
    } else {
        $user_id = $_SESSION['user_id'];
    }
    $borrow_date = $_POST['borrow_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    $current_quantity = $book['quantity'] ?? 0;

    // Kiểm tra tính hợp lệ của dữ liệu
    if (empty($user_id) || empty($borrow_date) || empty($due_date)) {
        $message = "Vui lòng chọn Người mượn và Ngày trả dự kiến.";
        $message_type = 'error';
    } elseif ($current_quantity <= 0) {
        $message = "Sách '{$book['title']}' hiện đã hết hàng (Tồn kho: 0). Không thể mượn.";
        $message_type = 'error';
    } elseif (!$pdo) {
        $message = "Lỗi: Không thể kết nối cơ sở dữ liệu.";
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Thêm Giao dịch Mượn vào bảng borrowings
            $stmt_insert = $pdo->prepare("INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) VALUES (:user_id, :book_id, :borrow_date, :due_date, 'borrowed')");
            $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':borrow_date', $borrow_date);
            $stmt_insert->bindParam(':due_date', $due_date);
            $stmt_insert->execute();

            // 2. Cập nhật số lượng sách còn lại (Giảm stock đi 1)
            $stmt_update_stock = $pdo->prepare("UPDATE books SET quantity = quantity - 1 WHERE id = :book_id");
            $stmt_update_stock->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt_update_stock->execute();
            
            $pdo->commit();

            $message = "Mượn sách '{$book['title']}' thành công cho Người dùng ID: {$user_id}. Số lượng sách còn lại đã được cập nhật.";
            $message_type = 'success';
            
            // Cập nhật lại thông tin sách sau khi mượn thành công
            $book['quantity'] -= 1; 

        } catch (PDOException $e) {
            $pdo->rollBack(); // Hoàn tác nếu có lỗi
            $message = "Lỗi CSDL khi MƯỢN SÁCH: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mượn Sách: <?php echo $book ? htmlspecialchars($book['title']) : 'Chi tiết sách'; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
        .card { transition: transform 0.3s, box-shadow 0.3s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="p-4 sm:p-8">

    <div class="max-w-5xl mx-auto">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-8 border-b-4 border-blue-500 pb-4">
            Chi Tiết & Lập Phiếu Mượn Sách
        </h1>

        <!-- Hiển thị thông báo (Thành công/Lỗi) -->
        <?php if ($message): ?>
            <div role="alert" class="p-4 mb-6 rounded-lg font-medium 
                <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Layout 2 cột: Chi tiết sách & Form mượn -->
        <?php if ($book): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Cột 1: Chi tiết Sách -->
            <div class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-lg card">
                <h2 class="text-2xl font-bold text-blue-600 mb-4"><?php echo htmlspecialchars($book['title']); ?></h2>
                <div class="space-y-3 text-gray-700">
                    <p><strong>ID Sách:</strong> <?php echo htmlspecialchars($book['id']); ?></p>
                    <p class="text-lg font-semibold">
                        <strong>Tồn kho:</strong> 
                        <span class="<?php echo $book['quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-extrabold">
                            <?php echo htmlspecialchars($book['quantity']); ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Cột 2: Form Mượn Sách -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-lg border border-blue-200">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Tạo Giao Dịch Mượn Sách</h2>

                <form method="POST" action="borrow.php?id=<?php echo htmlspecialchars($book_id); ?>" class="space-y-6">
                    <input type="hidden" name="action" value="borrow">
                    
                    <!-- Trường Người dùng -->
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Người mượn <span class="text-red-500">*</span>
                        </label>
                        <?php if ($is_admin): ?>
                        <select id="user_id" name="user_id" required 
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg shadow-sm">
                            <option value="">-- Chọn Người Dùng --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php echo htmlspecialchars("{$user['username']} (ID: {$user['id']})"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                            <div class="p-2 rounded bg-gray-50 border">Bạn mượn với tư cách: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <!-- Trường Ngày mượn và Ngày trả dự kiến -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="borrow_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Ngày mượn <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="borrow_date" name="borrow_date" required 
                                   value="<?php echo htmlspecialchars($borrow_date); ?>" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Ngày trả dự kiến <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="due_date" name="due_date" required 
                                   value="<?php echo htmlspecialchars($due_date); ?>" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Nút Mượn Sách -->
                    <div>
                        <button type="submit" 
                                <?php echo $book['quantity'] <= 0 ? 'disabled' : ''; ?>
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white 
                                <?php echo $book['quantity'] > 0 ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-gray-400 cursor-not-allowed'; ?> 
                                focus:outline-none focus:ring-2 focus:ring-offset-2 transition duration-150 ease-in-out">
                            <?php echo $book['quantity'] > 0 ? 'Mượn Sách ' : 'HẾT HÀNG - Không thể mượn'; ?>
                        </button>
                    </div>
                </form>

            </div>
        </div>
        <?php else: ?>
            <p class="text-xl text-red-500 font-medium"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- Nút Quay lại (ví dụ quay lại trang danh sách sách) -->
        <a href="../index.php" class="inline-flex items-center text-blue-600 mt-8 hover:text-blue-800 font-medium">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Quay lại Danh sách Sách
        </a>

    </div>

</body>
</html>