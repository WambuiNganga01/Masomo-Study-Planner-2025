<?php
// weeks.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if subject_id is provided
if (!isset($_GET['subject_id'])) {
    header("Location: subjects.php");
    exit();
}

$subject_id = $_GET['subject_id'];

// Verify the subject belongs to the user
$stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $subject_id, $user_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: subjects.php");
    exit();
}

// Handle adding a new week
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_week') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        $error = "Start date cannot be after end date.";
    } else {
        $stmt = $conn->prepare("INSERT INTO study_weeks (subject_id, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $subject_id, $start_date, $end_date);
        
        if ($stmt->execute()) {
            $week_id = $conn->insert_id;
            $message = "Week added successfully!";
            
            // Redirect to the activities page for this week
            header("Location: activities.php?week_id=" . $week_id);
            exit();
        } else {
            $error = "Error adding week: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle deleting a week
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_week') {
    $week_id = $_POST['week_id'];
    
    $stmt = $conn->prepare("DELETE FROM study_weeks WHERE id = ? AND subject_id = ?");
    $stmt->bind_param("ii", $week_id, $subject_id);
    
    if ($stmt->execute()) {
        $message = "Week deleted successfully!";
    } else {
        $error = "Error deleting week: " . $conn->error;
    }
    $stmt->close();
}

// Get all weeks for the subject
$stmt = $conn->prepare("
    SELECT w.*, 
           COUNT(a.id) as total_activities,
           SUM(CASE WHEN a.is_completed = 1 THEN 1 ELSE 0 END) as completed_activities
    FROM study_weeks w
    LEFT JOIN study_activities a ON w.id = a.week_id
    WHERE w.subject_id = ?
    GROUP BY w.id
    ORDER BY w.start_date ASC
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$weeks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Study Weeks - <?php echo htmlspecialchars($subject['subject_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .week-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .week-info {
            flex: 1;
        }
        
        .week-actions {
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
        
        .add-week-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .date-group {
            display: flex;
            gap: 20px;
        }
        
        .date-group .form-group {
            flex: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <a href="subjects.php" class="back-link">← Back to Subjects</a>
        
        <h1>Study Weeks: <?php echo htmlspecialchars($subject['subject_name']); ?></h1>
        
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
        
        <div class="add-week-form">
            <h3>Add New Study Week</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?subject_id=' . $subject_id); ?>">
                <input type="hidden" name="action" value="add_week">
                <div class="date-group">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add Week & Set Activities</button>
            </form>
        </div>
        
        <?php if (empty($weeks)): ?>
            <div class="empty-state">
                <h3>No study weeks yet</h3>
                <p>Add your first study week to start planning your activities.</p>
            </div>
        <?php else: ?>
            <div class="weeks-list">
                <?php foreach ($weeks as $week): ?>
                    <div class="week-card">
                        <div class="week-info">
                            <h3>Week: <?php echo date('M d', strtotime($week['start_date'])); ?> - <?php echo date('M d, Y', strtotime($week['end_date'])); ?></h3>
                            <p>Activities: <?php echo $week['total_activities']; ?></p>
                            <?php 
                            $progress = $week['total_activities'] > 0 ? 
                                ($week['completed_activities'] / $week['total_activities']) * 100 : 0;
                            ?>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <small><?php echo round($progress); ?>% complete</small>
                        </div>
                        <div class="week-actions">
                            <a href="activities.php?week_id=<?php echo $week['id']; ?>" class="btn btn-primary btn-sm">Manage Activities</a>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?subject_id=' . $subject_id); ?>" onsubmit="return confirm('Are you sure you want to delete this week?');">
                                <input type="hidden" name="action" value="delete_week">
                                <input type="hidden" name="week_id" value="<?php echo $week['id']; ?>">
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