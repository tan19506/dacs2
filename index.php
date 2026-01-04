<?php
require_once __DIR__ . '/functions.php'; 
require_once __DIR__ . '/connect.php'; 
include __DIR__ . '/layouts/header.php';

// 1. Cấu hình & Xử lý biến tìm kiếm (Đặt lên đầu để tránh lỗi Undefined)
$limit = 12; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$search_query = "%" . $search . "%"; // Định nghĩa rõ ràng ở đây

// Hiển thị thông báo
echo display_session_message();

try {
    // 2. Truy vấn COUNT - Sửa: Dùng 2 tên tham số khác nhau
    $sql_count = "SELECT COUNT(DISTINCT b.id) 
                  FROM books b
                  LEFT JOIN book_author ba ON b.id = ba.book_id
                  LEFT JOIN authors a ON ba.author_id = a.id
                  WHERE b.title LIKE :s1 OR a.name LIKE :s2";

    $stmt_count = $pdo->prepare($sql_count);
    // Truyền đủ 2 giá trị cho s1 và s2
    $stmt_count->execute([
        's1' => $search_query,
        's2' => $search_query
    ]); 
    $total_books = $stmt_count->fetchColumn();
    $total_pages = ceil($total_books / $limit);

    // 3. Truy vấn dữ liệu sách - Sửa: Tương tự như trên
    $sql_data = "SELECT b.id, b.title, b.year, b.cover, b.quantity, c.name AS category_name,
                 GROUP_CONCAT(a.name SEPARATOR ', ') AS authors_list
                 FROM books b
                 LEFT JOIN categories c ON b.category_id = c.id
                 LEFT JOIN book_author ba ON b.id = ba.book_id
                 LEFT JOIN authors a ON ba.author_id = a.id
                 WHERE b.title LIKE :s1 OR a.name LIKE :s2
                 GROUP BY b.id
                 ORDER BY b.title ASC
                 LIMIT :limit OFFSET :offset";

    $stmt_data = $pdo->prepare($sql_data);

    // Bind từng giá trị một cách tường minh
    $stmt_data->bindValue(':s1', $search_query, PDO::PARAM_STR);
    $stmt_data->bindValue(':s2', $search_query, PDO::PARAM_STR);
    $stmt_data->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt_data->execute();
    $books = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('<div class="alert alert-danger container mt-5">Lỗi CSDL: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>

<div class="container my-5">
    <div class="text-center mb-5 p-4 bg-white rounded-3 shadow-sm">
        <h1 class="display-5 fw-bold text-primary">Khám Phá Thư Viện</h1>
        <p class="text-muted">Tìm thấy <?= $total_books ?> cuốn sách</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <form action="index.php" method="GET" class="input-group input-group-lg shadow-sm">
                <input type="text" class="form-control" name="search" placeholder="Tìm theo tên sách hoặc tác giả..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-4 g-4">
        <?php foreach ($books as $book): ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0">
                    <img src="<?= htmlspecialchars($book['cover'] ?: 'https://placehold.co/400x600?text=No+Cover') ?>" class="card-img-top" style="height: 280px; object-fit: cover;">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><?= htmlspecialchars($book['title']) ?></h6>
                        <p class="small text-muted mb-1">Tác giả: <?= htmlspecialchars($book['authors_list'] ?: 'Chưa cập nhật') ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge <?= $book['quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                <?= $book['quantity'] > 0 ? 'Còn sách' : 'Hết sách' ?>
                            </span>
                            <a href="Books/details.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">Chi tiết</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="index.php?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layouts/footer.php'; ?>