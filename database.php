<?php
// Database configuration for XAMPP
class Database {
    private $host = 'localhost';
    private $db_name = 'poultry';
    private $username = 'root';
    private $password = ''; // Default XAMPP MySQL password is empty
    private $conn;
    
    // Get database connection
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    // Close connection
    public function closeConnection() {
        $this->conn = null;
    }
    
    // Test connection
    public function testConnection() {
        $connection = $this->getConnection();
        if($connection) {
            echo "Database connection successful!";
            return true;
        } else {
            echo "Database connection failed!";
            return false;
        }
    }
}

// Create a global database instance
$database = new Database();
$pdo = $database->getConnection();

// Function to execute queries safely
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        echo "Query error: " . $e->getMessage();
        return false;
    }
}

// Function to fetch single record
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Function to fetch multiple records
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : false;
}

// Function to get last insert ID
function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}
?>