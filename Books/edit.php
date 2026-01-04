<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

require_admin(); 
start_session_if_not_started();

$book_id = (int)($_GET['id'] ?? 0);
$errors = [];

// 1. LẤY DỮ LIỆU CŨ ĐỂ HIỂN THỊ TRÊN FORM
try {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();

    if (!$book) {
        set_session_message("Sách không tồn tại.", "danger");
        header('Location: list.php');
        exit();
    }

    // Lấy danh sách tác giả hiện tại của sách
    $current_authors = [];
    $auth_stmt = $conn->prepare("SELECT author_id FROM book_author WHERE book_id = ?");
    $auth_stmt->bind_param("i", $book_id);
    $auth_stmt->execute();
    $res = $auth_stmt->get_result();
    while($row = $res->fetch_assoc()) $current_authors[] = $row['author_id'];

} catch (Exception $e) {
    die("Lỗi: " . $e->getMessage());
}

// 2. XỬ LÝ KHI NGƯỜI DÙNG LƯU THAY ĐỔI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $year = (int)$_POST['year'];
    $quantity = (int)$_POST['quantity'];
    $category_id = (int)$_POST['category_id'];
    $author_ids = $_POST['author_ids'] ?? [];
    $new_author_name = trim($_POST['new_author_name'] ?? '');

    // Validation cơ bản
    if (empty($title)) $errors[] = "Tiêu đề không được để trống.";
    
    // Xử lý Upload Ảnh
    $cover_path = $book['cover']; // Mặc định dùng ảnh cũ
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = 'cover_' . uniqid() . '.' . $ext;
            $upload_dir = __DIR__ . '/../uploads/covers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            if (move_uploaded_file($_FILES['cover']['tmp_name'], $upload_dir . $new_name)) {
                // Xóa ảnh cũ nếu có
                if (!empty($book['cover']) && file_exists(__DIR__ . '/..' . $book['cover'])) {
                    unlink(__DIR__ . '/..' . $book['cover']);
                }
                $cover_path = '/uploads/covers/' . $new_name;
            }
        } else {
            $errors[] = "Định dạng ảnh không hợp lệ.";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Thêm tác giả mới nếu có nhập
            if (!empty($new_author_name)) {
                $stmt_a = $conn->prepare("INSERT INTO authors (name) VALUES (?)");
                $stmt_a->bind_param("s", $new_author_name);
                $stmt_a->execute();
                $author_ids[] = $conn->insert_id;
            }

            // Cập nhật thông tin sách
            $update_sql = "UPDATE books SET title=?, year=?, quantity=?, category_id=?, cover=? WHERE id=?";
            $stmt_u = $conn->prepare($update_sql);
            $stmt_u->bind_param("siiisi", $title, $year, $quantity, $category_id, $cover_path, $book_id);
            $stmt_u->execute();

            // Cập nhật quan hệ Tác giả (Xóa cũ - Thêm mới)
            $conn->query("DELETE FROM book_author WHERE book_id = $book_id");
            if (!empty($author_ids)) {
                $stmt_ba = $conn->prepare("INSERT INTO book_author (book_id, author_id) VALUES (?, ?)");
                foreach (array_unique($author_ids) as $aid) {
                    $stmt_ba->bind_param("ii", $book_id, $aid);
                    $stmt_ba->execute();
                }
            }

            $conn->commit();
            set_session_message("Cập nhật sách thành công!", "success");
            header('Location: list.php');
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}

// Lấy data cho dropdowns
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$authors = $conn->query("SELECT * FROM authors ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center mb-4">
                <a href="list.php" class="btn btn-outline-secondary btn-sm rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
                <h2 class="mb-0 fw-bold">Chỉnh sửa thông tin sách</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4">
                    <ul class="mb-0"><?php foreach($errors as $err) echo "<li>$err</li>"; ?></ul>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm p-4 p-md-5 rounded-4">
                <div class="row g-4">
                    <div class="col-md-4 border-end pe-md-5 text-center">
                        <label class="form-label d-block fw-bold text-muted mb-3">Ảnh bìa hiện tại</label>
                        <div class="mb-3">
                            <img src="<?= $book['cover'] ?: '/assets/img/no-cover.png' ?>" 
                                 class="img-fluid rounded shadow-sm border" id="preview" style="max-height: 300px;">
                        </div>
                        <input type="file" name="cover" class="form-control form-control-sm" onchange="previewImage(this)">
                        <small class="text-muted d-block mt-2">Định dạng: JPG, PNG, WEBP</small>
                    </div>

                    <div class="col-md-8 ps-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tiêu đề sách</label>
                            <input type="text" name="title" class="form-control form-control-lg bg-light border-0" 
                                   value="<?= htmlspecialchars($book['title']) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Năm xuất bản</label>
                                <input type="number" name="year" class="form-control" value="<?= $book['year'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Số lượng nhập kho</label>
                                <input type="number" name="quantity" class="form-control" value="<?= $book['quantity'] ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Danh mục</label>
                            <select name="category_id" class="form-select border-0 bg-light">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $book['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tác giả</label>
                            <select name="author_ids[]" class="form-select bg-light border-0 mb-2" multiple style="height: 120px;">
                                <?php foreach($authors as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= in_array($a['id'], $current_authors) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="new_author_name" class="form-control form-control-sm" placeholder="Hoặc thêm tác giả mới...">
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary px-5 rounded-pill shadow">Cập nhật dữ liệu</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { $('#preview').attr('src', e.target.result); }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>