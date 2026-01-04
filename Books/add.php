<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

require_admin(); 
start_session_if_not_started();

$errors = [];
$title = ''; $year = date('Y'); $quantity = 1; $category_id = ''; $author_ids = []; $new_author_name = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $year = (int)$_POST['year'];
    $quantity = (int)$_POST['quantity'];
    $category_id = (int)$_POST['category_id'];
    $author_ids = $_POST['author_ids'] ?? []; 
    $new_author_name = trim($_POST['new_author_name'] ?? ''); 

    // 1. Validation
    if (empty($title)) $errors[] = "Tiêu đề sách không được để trống.";
    if ($category_id <= 0) $errors[] = "Vui lòng chọn Danh mục.";
    if (empty($author_ids) && empty($new_author_name)) $errors[] = "Vui lòng chọn ít nhất một tác giả.";

    // 2. Xử lý File Upload
    $cover_path = ''; 
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/covers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $new_file_name = 'cover_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $upload_dir . $new_file_name)) {
                $cover_path = '/uploads/covers/' . $new_file_name;
            }
        } else {
            $errors[] = "Định dạng ảnh không hỗ trợ (chỉ nhận JPG, PNG, WEBP).";
        }
    }

    // 3. Thực thi Database
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Thêm tác giả mới nếu có
            if (!empty($new_author_name)) {
                $stmt = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
                $stmt->bind_param("s", $new_author_name);
                $stmt->execute();
                $author_ids[] = $conn->insert_id;
            }

            // Thêm sách
            $stmt = $conn->prepare("INSERT INTO books (title, year, quantity, category_id, cover) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("siiis", $title, $year, $quantity, $category_id, $cover_path);
            $stmt->execute();
            $book_id = $conn->insert_id;

            // Liên kết tác giả
            $stmt_link = $conn->prepare("INSERT INTO book_author (book_id, author_id) VALUES (?, ?)");
            foreach (array_unique($author_ids) as $aid) {
                $stmt_link->bind_param("ii", $book_id, $aid);
                $stmt_link->execute();
            }

            $conn->commit();
            set_session_message("Thêm sách '$title' thành công!", 'success');
            header('Location: list.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            if ($cover_path) @unlink(__DIR__ . '/..' . $cover_path);
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$authors = $conn->query("SELECT * FROM authors ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="bg-primary p-4 text-white">
                    <h3 class="mb-0"><i class="bi bi-book-half me-2"></i> Thêm sách mới vào thư viện</h3>
                    <p class="mb-0 opacity-75">Vui lòng điền đầy đủ thông tin bên dưới</p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger border-0 shadow-sm">
                            <ul class="mb-0"><?php foreach($errors as $err) echo "<li>$err</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tiêu đề sách</label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($title) ?>" placeholder="Nhập tên sách..." required>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-bold">Năm xuất bản</label>
                                        <input type="number" name="year" class="form-control" value="<?= $year ?>" max="<?= date('Y') ?>">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label fw-bold">Số lượng kho</label>
                                        <input type="number" name="quantity" class="form-control" value="<?= $quantity ?>" min="1">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Danh mục</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach($categories as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $category_id == $c['id'] ? 'selected' : '' ?>><?= $c['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 text-center border-start">
                                <label class="form-label fw-bold">Ảnh bìa</label>
                                <div class="mb-3">
                                    <img src="https://placehold.co/150x220?text=Preview" id="img-preview" class="img-fluid rounded shadow-sm border" style="height: 220px; object-fit: cover;">
                                </div>
                                <input type="file" name="cover" class="form-control form-control-sm" accept="image/*" onchange="previewFile(this)">
                            </div>

                            <div class="col-12 mt-0">
                                <hr>
                                <label class="form-label fw-bold">Tác giả</label>
                                <select name="author_ids[]" class="form-select mb-2" multiple style="height: 150px;">
                                    <?php foreach($authors as $a): ?>
                                        <option value="<?= $a['id'] ?>" <?= in_array($a['id'], $author_ids) ? 'selected' : '' ?>><?= $a['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                                    <input type="text" name="new_author_name" class="form-control" placeholder="Hoặc thêm tên tác giả mới nếu chưa có trong danh sách...">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-5">
                            <a href="list.php" class="btn btn-light px-4">Hủy bỏ</a>
                            <button type="submit" class="btn btn-primary px-5 shadow">Lưu thông tin sách</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('img-preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>