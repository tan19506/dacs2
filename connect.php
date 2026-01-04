<?php
// connect.php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';          
$DB_NAME = 'library';

try {
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    
} catch (PDOException $e) {
    die("LỖI KẾT NỐI HỆ THỐNG: " . $e->getMessage());
}

/**
 * PDOWrapper: Giúp code cũ (mysqli style) chạy được trên PDO
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
            return false;
        }
    }

    public function begin_transaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollBack(); }
    public function insert_id() { return $this->pdo->lastInsertId(); }

    public function __get($name) {
        if ($name === 'insert_id') return $this->insert_id();
        return null;
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

    public function bind_param($types, ...$values) {
        $this->params = $values;
    }

    public function execute() {
        try {
            $this->stmt = $this->pdo->prepare($this->sql);
            return $this->stmt->execute($this->params);
        } catch (PDOException $e) {
            $this->parent->error = $e->getMessage();
            return false;
        }
    }

    public function get_result() {
        return new PDOWrapperResult($this->stmt);
    }
}

class PDOWrapperResult {
    private $stmt;
    public function __construct($stmt) { $this->stmt = $stmt; }
    public function fetch_assoc() { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
    public function fetch_all() { return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
    public function num_rows() { return $this->stmt->rowCount(); }
}

// Khởi tạo biến $conn huyền thoại để các file khác sử dụng
$conn = new PDOWrapper($pdo);