<?php
require_once 'db.php';
require_once 'session.php';

// Require login
requireLogin();

// Get tasks for current user
try {
    $userId = getCurrentUserId();
    
    // Handle status filter
    $statusFilter = $_GET['status'] ?? 'all';
    $whereClause = "WHERE user_id = :user_id";
    
    if ($statusFilter !== 'all') {
        $whereClause .= " AND status = :status";
    }
    
    // Handle search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $_GET['search'];
        $whereClause .= " AND (title LIKE :search OR description LIKE :search)";
    }
    
    // Handle sorting
    $sortBy = $_GET['sort'] ?? 'due_date';
    $sortOrder = $_GET['order'] ?? 'ASC';
    
    // Validate sort parameters to prevent SQL injection
    $allowedSortFields = ['title', 'due_date', 'status', 'created_at'];
    $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'due_date';
    
    $allowedSortOrders = ['ASC', 'DESC'];
    $sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrders) ? $sortOrder : 'ASC';
    
    $orderClause = "ORDER BY $sortBy $sortOrder";
    
    $stmt = $pdo->prepare("SELECT * FROM tasks $whereClause $orderClause");
    $stmt->bindParam(':user_id', $userId);
    
    if ($statusFilter !== 'all') {
        $stmt->bindParam(':status', $statusFilter);
    }
    
    if (isset($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    
    $stmt->execute();
    $tasks = $stmt->fetchAll();
    
    // Get task counts by status
    $countStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = :user_id GROUP BY status");
    $countStmt->bindParam(':user_id', $userId);
    $countStmt->execute();
    $statusCounts = [];
    
    while ($row = $countStmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    $totalTasks = array_sum($statusCounts);
    $pendingTasks = $statusCounts['pending'] ?? 0;
    $completedTasks = $statusCounts['completed'] ?? 0;
    $inProgressTasks = $statusCounts['in_progress'] ?? 0;
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Error loading tasks: ' . $e->getMessage());
    $tasks = [];
}

// Get flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Task Manager</title>
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
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
                <h1>My Tasks</h1>
                <div class="header-actions">
                    <form action="dashboard.php" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search tasks..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    <a href="add_item.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Task</a>
                </div>
            </header>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>
            
            <!-- Task Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-list"></i></div>
                    <div class="stat-details">
                        <h3>Total Tasks</h3>
                        <p><?php echo $totalTasks; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <h3>Pending</h3>
                        <p><?php echo $pendingTasks; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon in-progress"><i class="fas fa-spinner"></i></div>
                    <div class="stat-details">
                        <h3>In Progress</h3>
                        <p><?php echo $inProgressTasks; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <h3>Completed</h3>
                        <p><?php echo $completedTasks; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Task List -->
            <div class="task-list-header">
                <h2>
                    <?php 
                    if ($statusFilter === 'all') echo 'All Tasks';
                    elseif ($statusFilter === 'pending') echo 'Pending Tasks';
                    elseif ($statusFilter === 'in_progress') echo 'In Progress Tasks';
                    elseif ($statusFilter === 'completed') echo 'Completed Tasks';
                    ?>
                </h2>
                <div class="sort-options">
                    <label for="sort">Sort by:</label>
                    <select id="sort" onchange="updateSort(this.value)">
                        <option value="due_date" <?php echo $sortBy === 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title</option>
                        <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Created Date</option>
                    </select>
                    <button onclick="toggleSortOrder()" class="btn btn-icon">
                        <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                    </button>
                </div>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No tasks found</h3>
                    <p>Get started by adding a new task</p>
                    <a href="add_item.php" class="btn btn-primary">Add Task</a>
                </div>
            <?php else: ?>
                <div class="task-list">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card <?php echo $task['status']; ?>">
                            <div class="task-status">
                                <?php if ($task['status'] === 'completed'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php elseif ($task['status'] === 'in_progress'): ?>
                                    <i class="fas fa-spinner"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="task-content">
                                <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                                <div class="task-meta">
                                    <span class="due-date">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No due date'; ?>
                                    </span>
                                    <?php if ($task['attachment']): ?>
                                        <span class="attachment">
                                            <i class="fas fa-paperclip"></i> 
                                            <a href="uploads/<?php echo $task['attachment']; ?>" target="_blank">View Attachment</a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="task-actions">
                                <a href="edit_item.php?id=<?php echo $task['id']; ?>" class="btn btn-icon" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="update_status.php" method="POST" class="inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-icon" title="Mark as Completed">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($task['status'] === 'pending'): ?>
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="btn btn-icon" title="Mark as In Progress">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <a href="delete_item.php?id=<?php echo $task['id']; ?>" class="btn btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this task?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function updateSort(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', value);
            window.location.search = urlParams.toString();
        }
        
        function toggleSortOrder() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentOrder = urlParams.get('order') || 'ASC';
            urlParams.set('order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>
