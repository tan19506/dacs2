<?php 

require_once '../functions.php';
require_admin(); // YÊU CẦU QUYỀN ADMIN

include '../layouts/header.php'; 
include '../connect.php'; 

// Dùng Prepared Statement cho tính nhất quán
// Đã thay đổi: Sắp xếp theo ID (tăng dần) thay vì theo Name
$sql = "SELECT id, name, description FROM categories ORDER BY id ASC";
$result = $conn->query($sql);
?>
<div class="container mt-5">
    <h2 class="mb-4">Quản lý Danh mục</h2>

    <a href="add.php" class="btn btn-success mb-3">
        <i class="bi-plus-circle"></i> Thêm danh mục mới
    </a>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Tên danh mục</th>
                <th>Mô tả</th>
                <th style="width: 150px;">Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($row["id"]) ?></td>
                <td><?= htmlspecialchars($row["name"]) ?></td>
                <td><?= htmlspecialchars($row["description"]) ?></td>
                <td>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Sửa</a>
                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Bạn chắc chắn muốn xóa danh mục này? Việc này có thể ảnh hưởng đến sách.')">Xóa</a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php include '../layouts/footer.php'; ?>