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