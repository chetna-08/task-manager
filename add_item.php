<?php
require_once 'db.php';
require_once 'session.php';

// Require login
requireLogin();

$error = '';

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
            $userId = getCurrentUserId();
            
            // Handle file upload
            $attachment = null;
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
                    $attachment = $fileName;
                } else {
                    $error = "Error uploading file";
                }
            }
            
            if (empty($error)) {
                // Insert task
                $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, attachment, status) VALUES (:user_id, :title, :description, :due_date, :attachment, :status)");
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':due_date', $due_date);
                $stmt->bindParam(':attachment', $attachment);
                $stmt->bindParam(':status', $status);
                $stmt->execute();
                
                setFlashMessage('success', 'Task added successfully!');
                header("Location: dashboard.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error adding task: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task - Task Manager</title>
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
                <h1>Add New Task</h1>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </header>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form action="add_item.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required value="<?php echo $_POST['title'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo $_POST['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo $_POST['due_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="pending" <?php echo isset($_POST['status']) && $_POST['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo isset($_POST['status']) && $_POST['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo isset($_POST['status']) && $_POST['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachment">Attachment</label>
                        <div class="file-input-container">
                            <input type="file" id="attachment" name="attachment" class="file-input">
                            <label for="attachment" class="file-input-label">
                                <i class="fas fa-upload"></i> Choose File
                            </label>
                            <span class="  Choose File
                            </label>
                            <span class="file-name" id="file-name">No file chosen</span>
                        </div>
                        <small class="form-text">Allowed file types: JPG, PNG, GIF, PDF, DOC, DOCX (Max size: 5MB)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Task</button>
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
    </script>
</body>
</html>
