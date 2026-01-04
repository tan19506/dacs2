<?php
require_once '../functions.php';
require_once '../connect.php';
include '../layouts/header.php';

if (!is_logged_in()) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$role = get_user_role();

// Truy vấn thông tin người dùng
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-gradient-primary text-white text-center py-5" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                    <div class="mb-3">
                        <i class="bi bi-person-circle" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?= htmlspecialchars($user['username']) ?></h3>
                    <span class="badge <?= $role === 'admin' ? 'bg-danger' : 'bg-light text-dark' ?> rounded-pill px-3">
                        <?= $role === 'admin' ? 'Quản trị viên' : 'Thành viên' ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12 border-bottom pb-2">
                            <label class="text-muted small uppercase fw-bold">Email liên hệ</label>
                            <p class="mb-0 fw-medium"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <label class="text-muted small uppercase fw-bold">Ngày tham gia</label>
                            <p class="mb-0"><?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <label class="text-muted small uppercase fw-bold">Mã tài khoản</label>
                            <p class="mb-0">#<?= $user['id'] ?></p>
                        </div>
                    </div>
                    <div class="mt-4 d-grid gap-2">
                        <?php if($role === 'admin'): ?>
                            <a href="../admin/index.php" class="btn btn-outline-danger rounded-pill">Vào trang Quản trị</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>