<?php
// activities.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if week_id is provided
if (!isset($_GET['week_id'])) {
    header("Location: subjects.php");
    exit();
}

$week_id = $_GET['week_id'];

// Verify the week and subject belongs to the user
$stmt = $conn->prepare("
    SELECT w.*, s.subject_name, s.id as subject_id
    FROM study_weeks w
    JOIN subjects s ON w.subject_id = s.id
    WHERE w.id = ? AND s.user_id = ?
");
$stmt->bind_param("ii", $week_id, $user_id);
$stmt->execute();
$week = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$week) {
    header("Location: subjects.php");
    exit();
}

// Handle adding a new activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_activity') {
    $activity_type = $_POST['activity_type'];
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("INSERT INTO study_activities (week_id, activity_type, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $week_id, $activity_type, $description);
    
    if ($stmt->execute()) {
        $message = "Activity added successfully!";
    } else {
        $error = "Error adding activity: " . $conn->error;
    }
    $stmt->close();
}

// Handle marking an activity as complete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'complete_activity') {
    $activity_id = $_POST['activity_id'];
    
    $stmt = $conn->prepare("UPDATE study_activities SET is_completed = 1, completed_at = NOW() WHERE id = ? AND week_id = ?");
    $stmt->bind_param("ii", $activity_id, $week_id);
    
    if ($stmt->execute()) {
        // Check if this is a milestone achievement
        checkAchievements($conn, $user_id);
        
        $message = "Activity marked as complete!";
    } else {
        $error = "Error updating activity: " . $conn->error;
    }
    $stmt->close();
}

// Handle deleting an activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_activity') {
    $activity_id = $_POST['activity_id'];
    
    $stmt = $conn->prepare("DELETE FROM study_activities WHERE id = ? AND week_id = ?");
    $stmt->bind_param("ii", $activity_id, $week_id);
    
    if ($stmt->execute()) {
        $message = "Activity deleted successfully!";
    } else {
        $error = "Error deleting activity: " . $conn->error;
    }
    $stmt->close();
}

// Get all activities for the week
$stmt = $conn->prepare("
    SELECT * FROM study_activities 
    WHERE week_id = ?
    ORDER BY activity_type, created_at
");
$stmt->bind_param("i", $week_id);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to check and award achievements
function checkAchievements($conn, $user_id) {
    // Get completed activities count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as completed_count 
        FROM study_activities a
        JOIN study_weeks w ON a.week_id = w.id
        JOIN subjects s ON w.subject_id = s.id
        WHERE s.user_id = ? AND a.is_completed = 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $completed_count = $result['completed_count'];
    $stmt->close();
    
    // Award achievements based on milestones
    $milestones = [
        ['count' => 1, 'type' => 'First Step', 'description' => 'Completed your first study activity'],
        ['count' => 5, 'type' => 'Getting Started', 'description' => 'Completed 5 study activities'],
        ['count' => 10, 'type' => 'Gaining Momentum', 'description' => 'Completed 10 study activities'],
        ['count' => 25, 'type' => 'Study Warrior', 'description' => 'Completed 25 study activities'],
        ['count' => 50, 'type' => 'Study Master', 'description' => 'Completed 50 study activities'],
        ['count' => 100, 'type' => 'Academic Excellence', 'description' => 'Completed 100 study activities']
    ];
    
    foreach ($milestones as $milestone) {
        if ($completed_count >= $milestone['count']) {
            // Check if achievement already exists
            $stmt = $conn->prepare("
                SELECT id FROM achievements 
                WHERE user_id = ? AND achievement_type = ?
            ");
            $stmt->bind_param("is", $user_id, $milestone['type']);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            
            // Add new achievement if it doesn't exist
            if (!$exists) {
                $stmt = $conn->prepare("
                    INSERT INTO achievements (user_id, achievement_type, description) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $user_id, $milestone['type'], $milestone['description']);
                $stmt->execute();
                $stmt->close();
                
                // Add points to user
                $points = $milestone['count'] * 10; // Simple point system
                $stmt = $conn->prepare("
                    UPDATE users SET points = points + ? WHERE id = ?
                ");
                $stmt->bind_param("ii", $points, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Week Activities - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .activities-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .activity-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-actions {
            display: flex;
            gap: 10px;
        }
        
        .activity-completed {
            opacity: 0.6;
            text-decoration: line-through;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .badge-notes {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-assignment {
            background-color: #ffc107;
            color: black;
        }
        
        .add-activity-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
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
        
        .week-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
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
        
        .empty-message {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <a href="weeks.php?subject_id=<?php echo $week['subject_id']; ?>" class="back-link">← Back to Weeks</a>
        
        <h1>Activities for <?php echo htmlspecialchars($week['subject_name']); ?></h1>
        
        <div class="week-info">
            <p><strong>Week Period:</strong> <?php echo date('M d, Y', strtotime($week['start_date'])); ?> - <?php echo date('M d, Y', strtotime($week['end_date'])); ?></p>
        </div>
        
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
        
        <div class="add-activity-form">
            <h3>Add New Activity</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week_id=' . $week_id); ?>">
                <input type="hidden" name="action" value="add_activity">
                <div class="form-group">
                    <label for="activity_type">Activity Type:</label>
                    <select class="form-control" id="activity_type" name="activity_type" required>
                        <option value="notes">Notes to Read</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Activity</button>
            </form>
        </div>
        
        <div class="activities-container">
            <!-- Notes Section -->
            <div class="activity-section">
                <h3>Notes to Read</h3>
                <?php 
                $notes_count = 0;
                foreach ($activities as $activity) {
                    if ($activity['activity_type'] == 'notes') {
                        $notes_count++;
                ?>
                <div class="activity-item <?php echo $activity['is_completed'] ? 'activity-completed' : ''; ?>">
                    <div class="activity-info">
                        <span class="badge badge-notes">Notes</span>
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <?php if ($activity['is_completed']): ?>
                            <small>Completed: <?php echo date('M d, Y', strtotime($activity['completed_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="activity-actions">
                        <?php if (!$activity['is_completed']): ?>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week_id=' . $week_id); ?>">
                                <input type="hidden" name="action" value="complete_activity">
                                <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">Mark Complete</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week_id=' . $week_id); ?>" onsubmit="return confirm('Are you sure you want to delete this activity?');">
                            <input type="hidden" name="action" value="delete_activity">
                            <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
                <?php 
                    }
                }
                if ($notes_count == 0): 
                ?>
                <div class="empty-message">
                    <p>No notes to read added yet.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Assignments Section -->
            <div class="activity-section">
                <h3>Assignments</h3>
                <?php 
                $assignments_count = 0;
                foreach ($activities as $activity) {
                    if ($activity['activity_type'] == 'assignment') {
                        $assignments_count++;
                ?>
                <div class="activity-item <?php echo $activity['is_completed'] ? 'activity-completed' : ''; ?>">
                    <div class="activity-info">
                        <span class="badge badge-assignment">Assignment</span>
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <?php if ($activity['is_completed']): ?>
                            <small>Completed: <?php echo date('M d, Y', strtotime($activity['completed_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="activity-actions">
                        <?php if (!$activity['is_completed']): ?>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week_id=' . $week_id); ?>">
                                <input type="hidden" name="action" value="complete_activity">
                                <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">Mark Complete</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week_id=' . $week_id); ?>" onsubmit="return confirm('Are you sure you want to delete this activity?');">
                            <input type="hidden" name="action" value="delete_activity">
                            <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
                <?php 
                    }
                }
                if ($assignments_count == 0): 
                ?>
                <div class="empty-message">
                    <p>No assignments added yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>