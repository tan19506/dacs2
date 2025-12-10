<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

// Yêu cầu quyền ADMIN để truy cập trang này
require_admin(); 

// Khởi tạo các biến (quan trọng để tránh lỗi undefined)
$errors = [];
$title = '';
$year = date('Y');
$quantity = 1;
$category_id = '';
$cover_path = ''; // Đường dẫn ảnh sẽ được lưu
$author_ids = []; 
$new_author_name = ''; 

// --- BƯỚC 2: XỬ LÝ FORM SUBMISSION (LOGIC & REDIRECTION) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy và làm sạch dữ liệu
    $title = trim($_POST['title'] ?? '');
    $year = (int)($_POST['year'] ?? date('Y'));
    $quantity = (int)($_POST['quantity'] ?? 1);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $author_ids = $_POST['author_ids'] ?? []; 
    $new_author_name = trim($_POST['new_author_name'] ?? ''); 

    // 2. Validation
    if (empty($title)) {
        $errors[] = "Tiêu đề sách không được để trống.";
    }
    if ($quantity <= 0) {
        $errors[] = "Số lượng phải lớn hơn 0.";
    }
    if ($category_id <= 0) {
        $errors[] = "Vui lòng chọn Danh mục.";
    }
    if (empty($author_ids) && empty($new_author_name)) {
        $errors[] = "Vui lòng chọn hoặc thêm ít nhất một tác giả.";
    }

    // 3. Xử lý File Upload (ĐÃ BỔ SUNG LẠI LOGIC NÀY)
    $upload_dir = __DIR__ . '/../uploads/covers/';
    $cover_path = ''; // Mặc định là chuỗi rỗng nếu không upload
    
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['cover']['tmp_name'];
        $file_name = $_FILES['cover']['name'];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        // Kiểm tra loại file cơ bản
        if (!in_array(strtolower($file_ext), $allowed_types)) {
            $errors[] = "Chỉ chấp nhận file ảnh (JPG, JPEG, PNG, GIF).";
        }
        
        // Nếu không có lỗi về file
        if (empty($errors)) {
            // Tạo tên file duy nhất để tránh trùng lặp
            $new_file_name = uniqid('cover_', true) . '.' . $file_ext;
            $destination_path = $upload_dir . $new_file_name;

            // Đảm bảo thư mục tồn tại
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($file_tmp_name, $destination_path)) {
                // Lưu đường dẫn tương đối (ví dụ: /uploads/covers/cover_xyz.jpg)
                $cover_path = '/uploads/covers/' . $new_file_name;
            } else {
                $errors[] = "Lỗi khi di chuyển file ảnh. Vui lòng kiểm tra quyền ghi của thư mục `uploads/covers`.";
            }
        }
    }


    // 4. Nếu không có lỗi, tiến hành INSERT DỮ LIỆU
    if (empty($errors)) {
        // Bắt đầu Transaction
        $conn->begin_transaction();
        
        try {
            // --- XỬ LÝ TÁC GIẢ MỚI ---
            if (!empty($new_author_name)) {
                $sql_new_author = "INSERT INTO authors (name) VALUES (?)";
                $stmt_new_author = $conn->prepare($sql_new_author);
                
                if ($stmt_new_author === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn Tác giả: " . $conn->error);
                }
                
                $stmt_new_author->bind_param("s", $new_author_name);
                $stmt_new_author->execute();
                $new_author_id = $conn->insert_id; 
                $stmt_new_author->close();
                $author_ids[] = $new_author_id;
            }

            // A. Thêm Sách vào bảng 'books'
            $sql_book = "INSERT INTO books (title, year, quantity, category_id, cover) VALUES (?, ?, ?, ?, ?)";
            $stmt_book = $conn->prepare($sql_book);

            if ($stmt_book === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn Sách: " . $conn->error);
            }

            $stmt_book->bind_param("siiis", $title, $year, $quantity, $category_id, $cover_path); // $cover_path đã có giá trị nếu upload thành công
            $stmt_book->execute();
            $book_id = $conn->insert_id; 
            $stmt_book->close();

            // B. Thêm các tác giả vào bảng 'book_author'
            if (!empty($author_ids)) {
                $sql_author = "INSERT INTO book_author (book_id, author_id) VALUES (?, ?)";
                $stmt_author = $conn->prepare($sql_author);
                
                if ($stmt_author === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn Liên kết Tác giả: " . $conn->error);
                }

                $unique_author_ids = array_unique(array_filter($author_ids, 'is_numeric'));
                
                foreach ($unique_author_ids as $author_id) {
                    $author_id = (int)$author_id;
                    $stmt_author->bind_param("ii", $book_id, $author_id);
                    $stmt_author->execute();
                }
                $stmt_author->close();
            }

            // Commit Transaction
            $conn->commit();
            set_session_message("Đã thêm sách '{$title}' và tác giả mới (nếu có) thành công!", 'success');
            
            header('Location: list.php'); 
            exit(); 

        } catch (Exception $e) {
            // Rollback nếu có lỗi xảy ra
            $conn->rollback();
            $errors[] = "Lỗi CSDL khi thêm sách: " . $e->getMessage();
            // Xóa file đã upload nếu có lỗi CSDL xảy ra sau khi upload
            if (!empty($cover_path) && file_exists(__DIR__ . '/..' . $cover_path)) {
                unlink(__DIR__ . '/..' . $cover_path);
            }
        }
    }
}

// --- BƯỚC 3: HIỂN THỊ FORM ---

// Lấy danh sách Categories và Authors để hiển thị trong form
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$authors = $conn->query("SELECT id, name FROM authors ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Include Header (BẮT ĐẦU OUTPUT HTML)
include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4 display-6 fw-bold text-primary">
        <i class="bi-plus-circle-fill me-2"></i> Thêm Sách Mới
    </h1>

    <?php 
    // Hiển thị lỗi nếu có
    if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm">
            <h5 class="alert-heading"><i class="bi-exclamation-triangle-fill me-2"></i> Lỗi xảy ra:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="add.php" method="POST" enctype="multipart/form-data">

                <!-- Tiêu đề Sách -->
                <div class="mb-4">
                    <label for="title" class="form-label fw-bold">Tiêu đề</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                </div>

                <!-- Năm xuất bản -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="year" class="form-label fw-bold">Năm xuất bản</label>
                        <input type="number" class="form-control" id="year" name="year" value="<?= htmlspecialchars($year) ?>" required min="1000" max="<?= date('Y') ?>">
                    </div>
                    <!-- Số lượng có sẵn -->
                    <div class="col-md-6">
                        <label for="quantity" class="form-label fw-bold">Số lượng có sẵn</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="<?= htmlspecialchars($quantity) ?>" required min="1">
                    </div>
                </div>

                <!-- Danh mục (Category) -->
                <div class="mb-4">
                    <label for="category_id" class="form-label fw-bold">Danh mục</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Chọn danh mục...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tác giả (Authors - Cho phép chọn nhiều) -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Tác giả</label>
                    
                    <div class="mb-3">
                        <label for="author_ids" class="form-label small text-muted">Chọn tác giả đã có sẵn:</label>
                        <select class="form-select" id="author_ids" name="author_ids[]" multiple size="5">
                            <?php foreach ($authors as $author): ?>
                                <option value="<?= $author['id'] ?>" <?= in_array($author['id'], $author_ids) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($author['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Giữ phím Ctrl (hoặc Command) để chọn nhiều tác giả.</small>
                    </div>

                    <div class="mt-4 border-top pt-3">
                        <label for="new_author_name" class="form-label small text-muted">Hoặc nhập tên tác giả mới:</label>
                        <input type="text" class="form-control" id="new_author_name" name="new_author_name" 
                               value="<?= htmlspecialchars($new_author_name) ?>" 
                               placeholder="Ví dụ: Nguyễn Văn A (Chỉ nhập 1 tác giả mới)">
                        <small class="form-text text-danger">Nếu bạn nhập tác giả mới, họ sẽ được thêm vào CSDL.</small>
                    </div>
                </div>

                <!-- Ảnh bìa (Cover) -->
                <div class="mb-4">
                    <label for="cover" class="form-label fw-bold">Ảnh bìa (Tùy chọn)</label>
                    <input type="file" class="form-control" id="cover" name="cover" accept="image/*">
                </div>

                <!-- Nút Submit -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3">
                    <a href="list.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                        <i class="bi-arrow-left-circle me-2"></i> Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">
                        <i class="bi-save-fill me-2"></i> Lưu Sách
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>