<?php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';          
$DB_NAME = 'library';

// tạo kết nối
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// kiểm tra lỗi kết nối
if ($conn->connect_error) {
    die("Kết nối DB thất bại: " . $conn->connect_error);
}

// set charset
$conn->set_charset("utf8");

$pdo = null;

try {
    $dsn = "mysql:host=" . $DB_HOST . ";dbname=" . $DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // Thực hiện kết nối
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    
} catch (PDOException $e) {
    // Nếu kết nối thất bại, in ra lỗi chi tiết để debug
    // Lỗi thường là: Access denied (sai user/pass) hoặc Unknown database (sai tên CSDL)
    
    // Vui lòng đọc LỖI CHI TIẾT này để biết vấn đề là gì:
    die("LỖI KẾT NỐI HỆ THỐNG: Vui lòng kiểm tra lại cấu hình DB trong file connect.php và đảm bảo MySQL đang chạy. Lỗi chi tiết: " . $e->getMessage());
}

/**
 * PDOWrapper: một lớp wrapper nhẹ để mô phỏng một số API mysqli
 * được dùng trong project (prepare/bind_param/get_result/query/begin_transaction/commit/rollback/insert_id/error)
 * Điều này cho phép giữ nguyên hầu hết code hiện tại nhưng sử dụng PDO bên dưới.
 */
class PDOWrapper {
    private $pdo;
    public $error = '';
    public $errno = 0;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function prepare($sql) {
        return new PDOWrapperStatement($this->pdo, $sql, $this);
    }

    public function query($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            return new PDOWrapperResult($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            return false;
        }
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    public function insert_id() {
        return $this->pdo->lastInsertId();
    }

    // compatibility property access: allow $conn->insert_id
    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->insert_id();
        }
        return null;
    }

    // set_charset stub for compatibility
    public function set_charset($cs) {
        // PDO charset already set in DSN; noop
        return true;
    }
}

class PDOWrapperStatement {
    private $pdo;
    private $sql;
    private $parent;
    private $params = [];
    private $stmt = null;

    public function __construct(PDO $pdo, $sql, $parent) {
        $this->pdo = $pdo;
        $this->sql = $sql;
        $this->parent = $parent;
    }

    // mysqli style bind_param: first arg is types string (ignored), rest are values
    public function bind_param() {
        $args = func_get_args();
        if (!$args) return;
        // if first is a types string, drop it
        if (is_string($args[0]) && count($args) > 1) {
            array_shift($args);
        }
        $this->params = $args;
    }

    // For code that calls bindValue or bindParam directly
    public function bindValue($key, $value) {
        // store by position
        $this->params[] = $value;
    }

    public function execute() {
        try {
            $this->stmt = $this->pdo->prepare($this->sql);
            // bind params by position
            foreach ($this->params as $i => $val) {
                $this->stmt->bindValue($i+1, $val);
            }
            $this->stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->parent->error = $e->getMessage();
            $this->parent->errno = (int)$e->getCode();
            return false;
        }
    }

    // Return a result wrapper similar to mysqli_result
    public function get_result() {
        if ($this->stmt === null) {
            // try to execute if not already
            $this->execute();
        }
        return new PDOWrapperResult($this->stmt);
    }

    public function close() {
        // PDO statements are freed by garbage collector; noop
        $this->stmt = null;
    }
}

class PDOWrapperResult {
    private $stmt;

    public function __construct($stmt) {
        $this->stmt = $stmt;
    }

    public function fetch_assoc() {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetch_all($mode = PDO::FETCH_ASSOC) {
        return $this->stmt->fetchAll($mode === MYSQLI_ASSOC ? PDO::FETCH_ASSOC : $mode);
    }

    public function fetch_row() {
        return $this->stmt->fetch(PDO::FETCH_NUM);
    }

    public function fetchColumn($col = 0) {
        return $this->stmt->fetchColumn($col);
    }
}

// Thay thế biến $conn cũ bằng wrapper PDO nếu PDO thành công
if ($pdo instanceof PDO) {
    $conn = new PDOWrapper($pdo);
}
?>
