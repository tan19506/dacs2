<?php 

require_once '../functions.php';
require_admin(); // YÊU CẦU QUYỀN ADMIN

include '../layouts/header.php'; 
include '../connect.php'; 

// 1. Lấy dữ liệu cũ an toàn
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID không hợp lệ!");
}
$id = (int) $_GET["id"];

$stmt_select = $conn->prepare("SELECT id, name, description FROM categories WHERE id=?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();
$category = $result_select->fetch_assoc();

if (!$category) {
    die("Không tìm thấy danh mục!");
}

$message = '';

if (isset($_POST["update"])) {
    $name = trim($_POST["name"] ?? '');
    $desc = trim($_POST["description"] ?? '');

    if (empty($name)) {
        $message = "Tên danh mục không được để trống.";
    } else {
        // 2. Cập nhật dữ liệu an toàn bằng Prepared Statement
        $stmt_update = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
        $stmt_update->bind_param("ssi", $name, $desc, $id);
        
        if ($stmt_update->execute()) {
            header("Location: list.php?success=edit");
            exit();
        } else {
            $message = "Lỗi khi cập nhật danh mục: " . $conn->error;
            if ($conn->errno == 1062) {
                $message = "Tên danh mục này đã tồn tại.";
            }
        }
    }
}
?>
<div class="container mt-5">
    <h2 class="mb-4">Sửa Danh mục: <?= htmlspecialchars($category['name']) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-warning"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Tên danh mục:</label>
            <input type="text" class="form-control" id="name" name="name" 
                   value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Mô tả:</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($category['description']) ?></textarea>
        </div>
        <button type="submit" name="update" class="btn btn-primary">
            <i class="bi-save"></i> Cập nhật
        </button>
        <a href="list.php" class="btn btn-secondary">
            <i class="bi-arrow-left"></i> Quay lại
        </a>
    </form>
</div>
<?php include '../layouts/footer.php'; ?>