</main> <footer class="bg-dark text-white-50 py-4 mt-auto">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <span class="small">&copy; <?= date('Y') ?> <strong>Thư Viện Tâm Hồn</strong>. Tất cả quyền được bảo lưu.</span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="d-inline-flex gap-3">
                    <a href="#" class="text-white-50 text-decoration-none small"><i class="bi bi-shield-lock me-1"></i>Bảo mật</a>
                    <a href="#" class="text-white-50 text-decoration-none small"><i class="bi bi-info-circle me-1"></i>Hỗ trợ</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<button onclick="topFunction()" id="btnToTop" class="btn btn-primary rounded-circle shadow" 
        style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 99; width: 45px; height: 45px;">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Xử lý nút quay lại đầu trang
    let mybutton = document.getElementById("btnToTop");
    window.onscroll = function() { scrollFunction() };

    function scrollFunction() {
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            mybutton.style.display = "block";
        } else {
            mybutton.style.display = "none";
        }
    }

    function topFunction() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
</script>
</body>
</html>