<?php
require_once __DIR__ . '/../connect.php'; 
require_once __DIR__ . '/../functions.php'; 

start_session_if_not_started();

// Yêu cầu đăng nhập mới được mượn
if (!is_logged_in()) {
    set_session_message("Vui lòng đăng nhập để thực hiện mượn sách.", "warning");
    redirect('/users/login.php');
}

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$is_admin = (get_user_role() === 'admin');

// Khởi tạo dữ liệu
$book = null;
$users = [];
$loan_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+14 days'));

try {
    // 1. Lấy thông tin sách
    $stmt_book = $pdo->prepare("SELECT id, title, quantity FROM books WHERE id = :id");
    $stmt_book->execute(['id' => $book_id]);
    $book = $stmt_book->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        set_session_message("Không tìm thấy sách yêu cầu.", "danger");
        redirect('/index.php');
    }

    // 2. Nếu là Admin, lấy danh sách user để chọn hộ
    if ($is_admin) {
        $users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    die("Lỗi hệ thống: " . $e->getMessage());
}

// --- XỬ LÝ KHI SUBMIT FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $target_user_id = $is_admin ? (int)$_POST['user_id'] : $_SESSION['user_id'];
    $post_loan_date = $_POST['loan_date'];
    $post_due_date = $_POST['due_date'];

    try {
        // Kiểm tra 1: Còn sách không?
        if ($book['quantity'] <= 0) {
            throw new Exception("Sách hiện đã hết trong kho.");
        }

        // Kiểm tra 2: User này đã mượn cuốn này mà chưa trả chưa?
        $stmt_check = $pdo->prepare("SELECT id FROM loans WHERE user_id = :uid AND book_id = :bid AND status = 'Borrowed'");
        $stmt_check->execute(['uid' => $target_user_id, 'bid' => $book_id]);
        if ($stmt_check->fetch()) {
            throw new Exception("Người dùng này hiện đang mượn cuốn sách này và chưa trả.");
        }

        // BẮT ĐẦU TRANSACTION
        $pdo->beginTransaction();

        // Bước A: Thêm vào bảng loans
        $stmt_insert = $pdo->prepare("INSERT INTO loans (user_id, book_id, loan_date, due_date, status) 
                                      VALUES (:uid, :bid, :bdate, :ddate, 'Borrowed')");
        $stmt_insert->execute([
            'uid'   => $target_user_id,
            'bid'   => $book_id,
            'bdate' => $post_loan_date,
            'ddate' => $post_due_date
        ]);

        // Bước B: Trừ tồn kho
        $stmt_update = $pdo->prepare("UPDATE books SET quantity = quantity - 1 WHERE id = :bid");
        $stmt_update->execute(['bid' => $book_id]);

        $pdo->commit();

        set_session_message("Đã lập phiếu mượn thành công cho cuốn '" . $book['title'] . "'.", "success");
        redirect('/index.php');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mượn Sách: <?= htmlspecialchars($book['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-slate-50 min-h-screen p-4 md:p-10">

    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <a href="/Books/details.php?id=<?= $book_id ?>" class="text-blue-600 hover:underline">
                <i class="bi bi-arrow-left"></i> Quay lại chi tiết sách
            </a>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Thông tin sách</h2>
                <h3 class="text-xl font-bold text-slate-800 mb-4"><?= htmlspecialchars($book['title']) ?></h3>
                <div class="text-sm text-slate-600 space-y-2">
                    <p><i class="bi bi-hash"></i> ID: <?= $book['id'] ?></p>
                    <p><i class="bi bi-archive"></i> Tồn kho: 
                        <span class="font-bold <?= $book['quantity'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $book['quantity'] ?> cuốn
                        </span>
                    </p>
                </div>
            </div>

            <div class="md:col-span-2 bg-white p-8 rounded-2xl shadow-md border-t-4 border-blue-500">
                <h2 class="text-2xl font-bold text-slate-800 mb-6">Lập Phiếu Mượn Sách</h2>
                
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="borrow">

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Người mượn</label>
                        <?php if ($is_admin): ?>
                            <select name="user_id" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">-- Chọn thành viên --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (ID: <?= $u['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="p-3 bg-blue-50 text-blue-800 rounded-xl border border-blue-100">
                                <i class="bi bi-person-circle me-2"></i> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (Bạn)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Ngày mượn</label>
                            <input type="date" name="loan_date" value="<?= $loan_date ?>" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Hạn trả dự kiến</label>
                            <input type="date" name="due_date" value="<?= $due_date ?>" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" 
                            <?= $book['quantity'] <= 0 ? 'disabled' : '' ?>
                            class="w-full py-4 px-6 rounded-xl font-bold text-white shadow-lg transition-all
                            <?= $book['quantity'] > 0 ? 'bg-blue-600 hover:bg-blue-700 hover:shadow-blue-200' : 'bg-slate-400 cursor-not-allowed' ?>">
                            <?= $book['quantity'] > 0 ? 'XÁC NHẬN MƯỢN SÁCH' : 'HẾT SÁCH TRONG KHO' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>