<?php
session_start();

// Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'poultry';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw $exception; // Re-throw the exception
        }
        
        return $this->conn;
    }
}

$database = new Database();
$pdo = $database->getConnection();

// helper functions
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " - SQL: " . $sql);
        throw $e; // Re-throw the exception instead of returning false
    }
}

function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch(); // No need to check for false since executeQuery throws exception
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll(); // No need to check for false since executeQuery throws exception
}

function getNextPaginationPageNumber() {
    $max_result = fetchOne("SELECT MAX(page_number) as max_page FROM pagination");
    $next_page = ($max_result['max_page'] ?? 0) + 1;
    while (fetchOne("SELECT id FROM pagination WHERE page_number = ?", [$next_page])) {
        $next_page++;
    }
    return $next_page;
}

function ensurePaginationAutoIncrement() {
    $col = fetchOne("SHOW COLUMNS FROM pagination LIKE 'id'");
    if (!$col) return false;
    if (stripos($col['Extra'] ?? '', 'auto_increment') !== false) return true;

    try {
        $max_id_row = fetchOne("SELECT MAX(id) as max_id FROM pagination");
        $max_id = intval($max_id_row['max_id'] ?? 0);
        $has_zero = fetchOne("SELECT COUNT(*) as c FROM pagination WHERE id = 0 OR id IS NULL");
        if (intval($has_zero['c'] ?? 0) > 0) {
            $max_id++;
            executeQuery("UPDATE pagination SET id = ? WHERE id = 0 OR id IS NULL", [$max_id]);
        }

        executeQuery("ALTER TABLE pagination MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        return true;
    } catch (Exception $e) {
        error_log("Pagination auto_increment error: " . $e->getMessage());
        return false;
    }
}

function ensurePaginationPageTypes($page_number, $farm_id) {
    $has_auto = ensurePaginationAutoIncrement();
    $next_id = null;
    if (!$has_auto) {
        $max_id_row = fetchOne("SELECT MAX(id) as max_id FROM pagination");
        $next_id = intval($max_id_row['max_id'] ?? 0) + 1;
    }
    $page_types = ['summary', 'food', 'sales', 'medicine', 'grand-total'];
    foreach ($page_types as $type) {
        $exists = fetchOne("SELECT id FROM pagination WHERE page_number = ? AND page_type = ? AND farm_id = ?", [$page_number, $type, $farm_id]);
        if (!$exists) {
            if ($has_auto) {
                executeQuery("INSERT INTO pagination (page_number, page_type, farm_id) VALUES (?, ?, ?)", [$page_number, $type, $farm_id]);
            } else {
                executeQuery("INSERT INTO pagination (id, page_number, page_type, farm_id) VALUES (?, ?, ?, ?)", [$next_id, $page_number, $type, $farm_id]);
                $next_id++;
            }
        }
    }
}

function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

function logHistory($user_id, $farm_id, $page_number, $action_type, $table_name, $record_id, $old_values = null, $new_values = null, $description = '') {
    global $pdo;
    
    try {
        $sql = "INSERT INTO history_logs (user_id, farm_id, page_number, action_type, table_name, record_id, old_values, new_values, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $farm_id,
            $page_number,
            $action_type,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("History log error: " . $e->getMessage());
        return false;
    }
}

?>
