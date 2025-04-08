<?php
// subjects.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle adding a new subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_subject') {
    $subject_name = trim($_POST['subject_name']);
    
    if (!empty($subject_name)) {
        $stmt = $conn->prepare("INSERT INTO subjects (user_id, subject_name) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $subject_name);
        
        if ($stmt->execute()) {
            $message = "Subject added successfully!";
        } else {
            $error = "Error adding subject: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Subject name cannot be empty.";
    }
}

// Handle deleting a subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_subject') {
    $subject_id = $_POST['subject_id'];
    
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $subject_id, $user_id);
    
    if ($stmt->execute()) {
        $message = "Subject deleted successfully!";
    } else {
        $error = "Error deleting subject: " . $conn->error;
    }
    $stmt->close();
}

// Get all subjects for the user
$stmt = $conn->prepare("
    SELECT s.*, 
           COUNT(DISTINCT w.id) as week_count,
           SUM(CASE WHEN a.is_completed = 1 THEN 1 ELSE 0 END) as completed_activities,
           COUNT(a.id) as total_activities
    FROM subjects s
    LEFT JOIN study_weeks w ON s.id = w.subject_id
    LEFT JOIN study_activities a ON w.id = a.week_id
    WHERE s.user_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subjects - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .subject-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-info {
            flex: 1;
        }
        
        .subject-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        .progress-container {
            margin-top: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
            height: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #007bff;
        }
        
        .add-subject-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <h1>Your Subjects</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="add-subject-form">
            <h3>Add New Subject</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="action" value="add_subject">
                <div class="form-group">
                    <label for="subject_name">Subject Name:</label>
                    <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </form>
        </div>
        
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <h3>No subjects yet</h3>
                <p>Add your first subject to get started with your study planning.</p>
            </div>
        <?php else: ?>
            <div class="subjects-list">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject-card">
                        <div class="subject-info">
                            <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                            <p>Weeks: <?php echo $subject['week_count']; ?></p>
                            <p>Activities: <?php echo $subject['total_activities']; ?></p>
                            <?php 
                            $progress = $subject['total_activities'] > 0 ? 
                                ($subject['completed_activities'] / $subject['total_activities']) * 100 : 0;
                            ?>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <small><?php echo round($progress); ?>% complete</small>
                        </div>
                        <div class="subject-actions">
                            <a href="weeks.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm">Manage Weeks</a>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>