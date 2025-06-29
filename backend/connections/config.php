<?php

$host = 'localhost';        
$dbname = 'bike_shop';     
$username = 'root';        
$password = '';            

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO connection
    $pdo = new PDO($dsn, $username, $password, $options);
    
    
} catch (PDOException $e) {
    // Log error and display user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    
    // In production, you might want to redirect to an error page
    die("Database connection failed. Please contact the administrator.");
}

// Optional: Database helper functions
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        throw new Exception("Database query failed");
    }
}

function getLastInsertId($pdo) {
    return $pdo->lastInsertId();
}

function beginTransaction($pdo) {
    return $pdo->beginTransaction();
}

function commit($pdo) {
    return $pdo->commit();
}

function rollback($pdo) {
    return $pdo->rollBack();
}

// Test connection (optional - remove in production)
if (isset($_GET['test_db'])) {
    try {
        $testQuery = $pdo->query("SELECT 1 as test");
        $result = $testQuery->fetch();
        if ($result['test'] == 1) {
            echo "✅ Database connection successful!";
        }
    } catch (Exception $e) {
        echo "❌ Database connection test failed: " . $e->getMessage();
    }
    exit;
}
?>