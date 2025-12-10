<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../connect.php'; 

// Yêu cầu quyền ADMIN để truy cập trang này
require_admin(); 

// Lấy book_id từ URL
$book_id = (int)($_GET['id'] ?? 0);

// Khởi tạo các biến
$errors = [];
$book = null;
$title = '';
$year = date('Y');
$quantity = 1;
$category_id = '';
$cover_path = ''; // Đường dẫn ảnh sẽ được dùng để UPDATE CSDL
$current_cover = ''; // Đường dẫn ảnh hiện tại (từ DB)
$author_ids = []; 
$new_author_name = '';

// --- BƯỚC 2: LẤY DỮ LIỆU SÁCH CŨ (GET DATA) ---
if ($book_id > 0) {
    // Truy vấn chính: Lấy thông tin sách
    $sql_book = "SELECT id, title, year, quantity, category_id, cover FROM books WHERE id = ?";
    $stmt_book = $conn->prepare($sql_book);

    if ($stmt_book === false) {
        set_session_message("Lỗi CSDL khi chuẩn bị truy vấn sách: " . $conn->error, 'danger');
        header('Location: list.php');
        exit();
    }
    
    $stmt_book->bind_param("i", $book_id);
    $stmt_book->execute();
    $result_book = $stmt_book->get_result();
    $book = $result_book->fetch_assoc();
    $stmt_book->close();

    if (!$book) {
        set_session_message("Sách có ID #{$book_id} không tồn tại.", 'danger');
        header('Location: list.php');
        exit();
    }

    // Gán dữ liệu sách cũ vào các biến form
    $title = $book['title'];
    $year = $book['year'];
    $quantity = $book['quantity'];
    $category_id = $book['category_id'];
    $current_cover = $book['cover'];
    $cover_path = $book['cover']; // Khởi tạo $cover_path bằng giá trị cũ
    
    // Truy vấn phụ: Lấy các ID tác giả hiện tại
    $sql_current_authors = "SELECT author_id FROM book_author WHERE book_id = ?";
    $stmt_authors = $conn->prepare($sql_current_authors);
    
    if ($stmt_authors === false) {
        set_session_message("Lỗi CSDL khi chuẩn bị truy vấn tác giả hiện tại: " . $conn->error, 'danger');
        header('Location: list.php');
        exit();
    }
    
    $stmt_authors->bind_param("i", $book_id);
    $stmt_authors->execute();
    $result_authors = $stmt_authors->get_result();
    
    // Tạo mảng author_ids từ dữ liệu CSDL
    while ($row = $result_authors->fetch_assoc()) {
        $author_ids[] = $row['author_id'];
    }
    $stmt_authors->close();

} else {
    set_session_message("ID sách không hợp lệ.", 'danger');
    header('Location: list.php');
    exit();
}


// --- BƯỚC 3: XỬ LÝ FORM SUBMISSION (UPDATE LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy và làm sạch dữ liệu từ POST
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

    // 3. Xử lý File Upload (BỔ SUNG LOGIC CẬP NHẬT/XÓA FILE CŨ)
    $upload_dir = __DIR__ . '/../uploads/covers/';
    
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['cover']['tmp_name'];
        $file_name = $_FILES['cover']['name'];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Kiểm tra loại file cơ bản
        if (!in_array(strtolower($file_ext), $allowed_types)) {
            $errors[] = "Chỉ chấp nhận file ảnh (JPG, JPEG, PNG, GIF).";
        }

        if (empty($errors)) {
            // Tạo tên file duy nhất
            $new_file_name = uniqid('cover_', true) . '.' . $file_ext;
            $destination_path = $upload_dir . $new_file_name;

            // Đảm bảo thư mục tồn tại
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($file_tmp_name, $destination_path)) {
                $new_cover_path = '/uploads/covers/' . $new_file_name;
                
                // Xóa file cũ nếu nó tồn tại
                if (!empty($current_cover) && file_exists(__DIR__ . '/..' . $current_cover)) {
                    unlink(__DIR__ . '/..' . $current_cover);
                }
                
                // Cập nhật đường dẫn mới
                $cover_path = $new_cover_path; 

            } else {
                $errors[] = "Lỗi khi di chuyển file ảnh mới. Vui lòng kiểm tra quyền ghi.";
            }
        }
    } 
    // Nếu không upload file mới, $cover_path vẫn giữ giá trị cũ ($current_cover) và sẽ được UPDATE.
    
    // 4. Nếu không có lỗi, tiến hành CẬP NHẬT DỮ LIỆU
    if (empty($errors)) {
        // Bắt đầu Transaction
        $conn->begin_transaction();
        
        try {
            // --- XỬ LÝ TÁC GIẢ MỚI ---
            if (!empty($new_author_name)) {
                $sql_new_author = "INSERT INTO authors (name) VALUES (?)";
                $stmt_new_author = $conn->prepare($sql_new_author);
                
                if ($stmt_new_author === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn Tác giả mới: " . $conn->error);
                }
                
                $stmt_new_author->bind_param("s", $new_author_name); 
                $stmt_new_author->execute();
                $new_author_id = $conn->insert_id; 
                $stmt_new_author->close();

                $author_ids[] = $new_author_id;
            }

            // A. CẬP NHẬT SÁCH vào bảng 'books'
            $sql_update_book = "UPDATE books SET title = ?, year = ?, quantity = ?, category_id = ?, cover = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update_book);

            if ($stmt_update === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn Cập nhật Sách: " . $conn->error);
            }

            $stmt_update->bind_param("siiisi", $title, $year, $quantity, $category_id, $cover_path, $book_id);
            $stmt_update->execute();
            $stmt_update->close();

            // B. CẬP NHẬT LIÊN KẾT TÁC GIẢ (Xóa hết cũ, thêm lại mới)
            
            // B1: Xóa tất cả liên kết tác giả cũ của sách này
            $sql_delete_authors = "DELETE FROM book_author WHERE book_id = ?";
            $stmt_delete = $conn->prepare($sql_delete_authors);

            if ($stmt_delete === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn Xóa Tác giả cũ: " . $conn->error);
            }

            $stmt_delete->bind_param("i", $book_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // B2: Thêm lại các tác giả mới được chọn 
            if (!empty($author_ids)) {
                $sql_insert_author = "INSERT INTO book_author (book_id, author_id) VALUES (?, ?)";
                $stmt_insert_author = $conn->prepare($sql_insert_author);
                
                if ($stmt_insert_author === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn Thêm Tác giả mới: " . $conn->error);
                }

                $unique_author_ids = array_unique(array_filter($author_ids, 'is_numeric'));
                
                foreach ($unique_author_ids as $author_id_item) {
                    $author_id_item = (int)$author_id_item;
                    $stmt_insert_author->bind_param("ii", $book_id, $author_id_item);
                    $stmt_insert_author->execute();
                }
                $stmt_insert_author->close();
            }

            // Commit Transaction
            $conn->commit();

            // Đặt thông báo thành công
            set_session_message("Đã cập nhật sách '{$title}' thành công!", 'success');
            
            header('Location: list.php'); 
            exit(); 

        } catch (Exception $e) {
            // Rollback nếu có lỗi xảy ra
            $conn->rollback();
            $errors[] = "Lỗi CSDL khi cập nhật sách: " . $e->getMessage();
            // Nếu có lỗi CSDL, nhưng đã upload file mới, ta phải xóa nó đi
            if (!empty($cover_path) && $cover_path !== $current_cover && file_exists(__DIR__ . '/..' . $cover_path)) {
                unlink(__DIR__ . '/..' . $cover_path);
            }
        }
    }
    // Dữ liệu $author_ids đã được cập nhật từ $_POST ở trên.
}


// --- BƯỚC 4: HIỂN THỊ FORM (FETCH DATA FOR DROPDOWNS) ---

// Lấy danh sách Categories và Authors để hiển thị trong form
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$authors = $conn->query("SELECT id, name FROM authors ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Include Header (BẮT ĐẦU OUTPUT HTML)
include __DIR__ . '/../layouts/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4 display-6 fw-bold text-primary">
        <i class="bi-pencil-square me-2"></i> Chỉnh Sửa Sách: <?= htmlspecialchars($title) ?>
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
            <form action="edit.php?id=<?= $book_id ?>" method="POST" enctype="multipart/form-data">

                <!-- Tiêu đề Sách -->
                <div class="mb-4">
                    <label for="title" class="form-label fw-bold">Tiêu đề</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                </div>

                <!-- Năm xuất bản & Số lượng -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="year" class="form-label fw-bold">Năm xuất bản</label>
                        <input type="number" class="form-control" id="year" name="year" value="<?= htmlspecialchars($year) ?>" required min="1000" max="<?= date('Y') ?>">
                    </div>
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
                        <label for="author_ids" class="form-label small text-muted">Chọn tác giả đã có sẵn (Các tác giả hiện tại đã được chọn):</label>
                        <select class="form-select" id="author_ids" name="author_ids[]" multiple size="5">
                            <?php foreach ($authors as $author): ?>
                                <option value="<?= $author['id'] ?>" <?= in_array($author['id'], $author_ids) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($author['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Giữ phím Ctrl (hoặc Command) để chọn/bỏ chọn nhiều tác giả.</small>
                    </div>

                    <div class="mt-4 border-top pt-3">
                        <label for="new_author_name" class="form-label small text-muted">Hoặc nhập tên tác giả mới (nếu muốn thêm):</label>
                        <input type="text" class="form-control" id="new_author_name" name="new_author_name" 
                               value="<?= htmlspecialchars($new_author_name) ?>" 
                               placeholder="Chỉ nhập 1 tác giả mới nếu cần">
                    </div>
                </div>

                <!-- Ảnh bìa (Cover) -->
                <div class="mb-4">
                    <label for="cover" class="form-label fw-bold">Ảnh bìa (Tùy chọn)</label>
                    <input type="file" class="form-control" id="cover" name="cover" accept="image/*">
                    <?php if (!empty($current_cover)): ?>
                        <small class="form-text text-muted mt-2 d-block">
                            Ảnh bìa hiện tại: **<?= basename($current_cover) ?>**. Chọn file mới để thay thế.
                        </small>
                        <div class="mt-2">
                             <img src="<?= htmlspecialchars($current_cover) ?>" alt="Ảnh bìa hiện tại" style="max-width: 150px; height: auto; border: 1px solid #ccc; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Nút Submit -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3">
                    <a href="list.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                        <i class="bi-arrow-left-circle me-2"></i> Hủy và Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">
                        <i class="bi-save-fill me-2"></i> Lưu Thay Đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>