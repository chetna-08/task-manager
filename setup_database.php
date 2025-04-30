<?php
// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = 'mysql';

try {
    // Connect to MySQL server without selecting a database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS task_manager");
    
    echo "Database 'task_manager' created or already exists.<br>";
    
    // Select the database
    $pdo->exec("USE task_manager");
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Table 'users' created successfully.<br>";
    
    // Create tasks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        due_date DATE,
        attachment VARCHAR(255),
        status ENUM('pending', 'completed', 'in_progress') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "Table 'tasks' created successfully.<br>";
    
    // Check if admin user exists, if not create one
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email) VALUES ('admin', :password, 'admin@example.com')");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->execute();
        
        echo "Default admin user created:<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Admin user already exists.<br>";
    }
    
    echo "<br>Database setup completed successfully!<br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
