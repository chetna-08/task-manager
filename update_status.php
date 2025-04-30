<?php
require_once 'db.php';
require_once 'session.php';

// Require login
requireLogin();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$taskId = $_POST['task_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$taskId || !$status) {
    setFlashMessage('error', 'Task ID and status are required');
    header("Location: dashboard.php");
    exit;
}

// Validate status
$validStatuses = ['pending', 'in_progress', 'completed'];
if (!in_array($status, $validStatuses)) {
    setFlashMessage('error', 'Invalid status');
    header("Location: dashboard.php");
    exit;
}

try {
    $userId = getCurrentUserId();
    
    // Update task status
    $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $taskId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        setFlashMessage('success', 'Task status updated successfully!');
    } else {
        setFlashMessage('error', 'Task not found or you do not have permission to update it');
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Error updating task status: ' . $e->getMessage());
}

header("Location: dashboard.php");
exit;
?>
