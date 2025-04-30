<?php
require_once 'db.php';
require_once 'session.php';

// Require login
requireLogin();

$error = '';
$task = null;

// Get task ID from URL
$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    setFlashMessage('error', 'Task ID is required');
    header("Location: dashboard.php");
    exit;
}

// Get task details
try {
    $userId = getCurrentUserId();
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $taskId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Task not found or you do not have permission to edit it');
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Error loading task: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    
    // Validate input
    if (empty($title)) {
        $error = "Title is required";
    } else {
        try {
            // Handle file upload
            $attachment = $task['attachment']; // Keep existing attachment by default
            
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                
                // Create uploads directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileName = uniqid() . '_' . basename($_FILES['attachment']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                // Check file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $fileType = $_FILES['attachment']['type'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $error = "Invalid file type. Allowed types: JPG, PNG, GIF, PDF, DOC, DOCX";
                } elseif ($_FILES['attachment']['size'] > 5000000) { // 5MB limit
                    $error = "File is too large. Maximum size is 5MB";
                } elseif (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadFile)) {
                    // Delete old attachment if exists
                    if ($task['attachment'] && file_exists($uploadDir . $task['attachment'])) {
                        unlink($uploadDir . $task['attachment']);
                    }
                    $attachment = $fileName;
                } else {
                    $error = "Error uploading file";
                }
            } elseif (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1') {
                // Remove existing attachment
                if ($task['attachment'] && file_exists('uploads/' . $task['attachment'])) {
                    unlink('uploads/' . $task['attachment']);
                }
                $attachment = null;
            }
            
            if (empty($error)) {
                // Update task
                $stmt = $pdo->prepare("UPDATE tasks SET title = :title, description = :description, due_date = :due_date, attachment = :attachment, status = :status WHERE id = :id AND user_id = :user_id");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':due_date', $due_date);
                $stmt->bindParam(':attachment', $attachment);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $taskId);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                setFlashMessage('success', 'Task updated successfully!');
                header("Location: dashboard.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error updating task: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - Task Manager</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Task Manager</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="dashboard.php?status=pending"><i class="fas fa-tasks"></i> Pending</a></li>
                    <li><a href="dashboard.php?status=in_progress"><i class="fas fa-spinner"></i> In Progress</a></li>
                    <li><a href="dashboard.php?status=completed"><i class="fas fa-check-circle"></i> Completed</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Logged in as: <strong><?php echo getCurrentUsername(); ?></strong></p>
                <a href="logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1>Edit Task</h1>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </header>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form action="edit_item.php?id=<?php echo $taskId; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($task['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo $task['due_date']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachment</label>
                        <?php if ($task['attachment']): ?>
                            <div class="current-attachment">
                                <p>Current file: <a href="uploads/<?php echo $task['attachment']; ?>" target="_blank"><?php echo $task['attachment']; ?></a></p>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="remove_attachment" name="remove_attachment" value="1">
                                    <label for="remove_attachment">Remove current attachment</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-input-container">
                            <input type="file" id="attachment" name="attachment" class="file-input">
                            <label for="attachment" class="file-input-label">
                                <i class="fas fa-upload"></i> Choose New File
                            </label>
                            <span class="file-name" id="file-name">No file chosen</span>
                        </div>
                        <small class="form-text">Allowed file types: JPG, PNG, GIF, PDF, DOC, DOCX (Max size: 5MB)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Task</button>
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Display filename when file is selected
        document.getElementById('attachment').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        });
        
        // Disable file input when remove attachment is checked
        document.getElementById('remove_attachment')?.addEventListener('change', function() {
            document.getElementById('attachment').disabled = this.checked;
            if (this.checked) {
                document.getElementById('file-name').textContent = 'No file chosen';
            }
        });
    </script>
</body>
</html>
