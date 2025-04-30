<?php
require_once 'db.php';
require_once 'session.php';

// Require login
requireLogin();

// Get task ID from URL
$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    setFlashMessage('error', 'Task ID is required');
    header("Location: dashboard.php");
    exit;
}

try {
    $userId = getCurrentUserId();
    
    // Get task details to check ownership and get attachment filename
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $taskId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Task not found or you do not have permission to delete it');
        header("Location: dashboard.php");
        exit;
    }
    
    // Delete attachment file if exists
    if ($task['attachment'] && file_exists('uploads/' . $task['attachment'])) {
        unlink('uploads/' . $task['attachment']);
    }
    
    // Delete task
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $taskId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    setFlashMessage('success', 'Task deleted successfully!');
} catch (PDOException $e) {
    setFlashMessage('error', 'Error deleting task: ' . $e->getMessage());
}

header("Location: dashboard.php");
exit;
?>
