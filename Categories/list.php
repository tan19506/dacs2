<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php';

// Kiểm tra quyền Admin
require_admin();

try {
    // Truy vấn danh mục và đếm số sách trong mỗi danh mục
    $sql = "SELECT c.*, COUNT(b.id) as total_books 
            FROM categories c 
            LEFT JOIN books b ON c.id = b.category_id 
            GROUP BY c.id";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Sửa đường dẫn Header (Lùi 1 cấp)
include __DIR__ . '/../layouts/header.php'; 
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Quản lý Danh mục</h2>
        <a href="add.php" class="btn btn-primary rounded-pill">
            <i class="bi bi-plus-lg"></i> Thêm danh mục mới
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Tên danh mục</th>
                        <th>Mô tả</th>
                        <th>Số lượng sách</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categories) > 0): // Thay cho $num_rows ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($cat['description'] ?? 'Không có mô tả') ?></td>
                            <td><span class="badge bg-info text-dark"><?= $cat['total_books'] ?> cuốn</span></td>
                            <td class="text-center">
                                <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <a href="delete.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa danh mục này?')">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">Chưa có danh mục nào được tạo.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../layouts/footer.php'; 
?>