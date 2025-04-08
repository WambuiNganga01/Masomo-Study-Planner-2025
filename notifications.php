<?php
// notifications.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$message = '';

// Get all incomplete activities grouped by subject and week
$stmt = $conn->prepare("
    SELECT 
        s.id as subject_id,
        s.subject_name,
        w.id as week_id,
        w.start_date,
        w.end_date,
        a.id as activity_id,
        a.activity_type,
        a.description,
        a.is_completed
    FROM subjects s
    JOIN study_weeks w ON s.id = w.subject_id
    JOIN study_activities a ON w.id = a.week_id
    WHERE s.user_id = ? AND a.is_completed = 0
    ORDER BY w.end_date ASC, s.subject_name, w.start_date
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Group notifications by subject and week
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $subject_id = $row['subject_id'];
    $week_id = $row['week_id'];
    
    if (!isset($notifications[$subject_id])) {
        $notifications[$subject_id] = [
            'subject_name' => $row['subject_name'],
            'weeks' => []
        ];
    }
    
    if (!isset($notifications[$subject_id]['weeks'][$week_id])) {
        $notifications[$subject_id]['weeks'][$week_id] = [
            'week_id' => $week_id,
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'activities' => []
        ];
    }
    
    $notifications[$subject_id]['weeks'][$week_id]['activities'][] = [
        'id' => $row['activity_id'],
        'type' => $row['activity_type'],
        'description' => $row['description'],
        'is_completed' => $row['is_completed']
    ];
}

// Handle marking an activity as complete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'complete_activity') {
    $activity_id = $_POST['activity_id'];
    $week_id = $_POST['week_id'];
    
    $stmt = $conn->prepare("UPDATE study_activities SET is_completed = 1, completed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $activity_id);
    
    if ($stmt->execute()) {
        // Check if this is a milestone achievement
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) as completed_count 
            FROM study_activities a
            JOIN study_weeks w ON a.week_id = w.id
            JOIN subjects s ON w.subject_id = s.id
            WHERE s.user_id = ? AND a.is_completed = 1
        ");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $result = $stmt2->get_result()->fetch_assoc();
        $completed_count = $result['completed_count'];
        $stmt2->close();
        
        // Assign points and check achievements
        $milestones = [
            ['count' => 1, 'type' => 'First Step', 'description' => 'Completed your first study activity'],
            ['count' => 5, 'type' => 'Getting Started', 'description' => 'Completed 5 study activities'],
            ['count' => 10, 'type' => 'Gaining Momentum', 'description' => 'Completed 10 study activities'],
            ['count' => 25, 'type' => 'Study Warrior', 'description' => 'Completed 25 study activities'],
            ['count' => 50, 'type' => 'Study Master', 'description' => 'Completed 50 study activities'],
            ['count' => 100, 'type' => 'Academic Excellence', 'description' => 'Completed 100 study activities']
        ];
        
        foreach ($milestones as $milestone) {
            if ($completed_count == $milestone['count']) {
                // Add achievement
                $stmt3 = $conn->prepare("
                    INSERT INTO achievements (user_id, achievement_type, description) 
                    VALUES (?, ?, ?)
                ");
                $stmt3->bind_param("iss", $user_id, $milestone['type'], $milestone['description']);
                $stmt3->execute();
                $stmt3->close();
                
                // Add points
                $points = $milestone['count'] * 10;
                $stmt4 = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt4->bind_param("ii", $points, $user_id);
                $stmt4->execute();
                $stmt4->close();
                
                $message = "Achievement unlocked: " . $milestone['type'] . "! You earned " . $points . " points.";
            }
        }
        
        // Redirect to refresh the page and avoid form resubmission
        header("Location: notifications.php" . (!empty($message) ? "?message=" . urlencode($message) : ""));
        exit();
    }
    $stmt->close();
}

// Get message from URL if exists
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .notification-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .week-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .week-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .week-date {
            color: #6c757d;
            font-size: 14px;
        }
        
        .activity-item {
            padding: 12px;
            background: white;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-info {
            flex: 1;
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
        
        .empty-notification {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .subject-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <h1>Notifications</h1>
        <p>Your upcoming and pending study activities</p>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-notification">
                <h3>No pending activities</h3>
                <p>Great job! You have completed all your study activities.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $subject_id => $subject): ?>
                <div class="notification-section">
                    <h2 class="subject-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
                    
                    <?php foreach ($subject['weeks'] as $week): ?>
                        <div class="week-card">
                            <div class="week-header">
                                <h3>Week: <?php echo date('M d', strtotime($week['start_date'])); ?> - <?php echo date('M d, Y', strtotime($week['end_date'])); ?></h3>
                                <span class="week-date <?php echo strtotime($week['end_date']) < time() ? 'overdue' : ''; ?>">
                                    <?php 
                                    if (strtotime($week['end_date']) < time()) {
                                        echo 'Overdue';
                                    } else {
                                        $days_left = ceil((strtotime($week['end_date']) - time()) / (60 * 60 * 24));
                                        echo $days_left . ' day' . ($days_left != 1 ? 's' : '') . ' left';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php foreach ($week['activities'] as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <span class="badge badge-<?php echo $activity['type']; ?>">
                                            <?php echo $activity['type'] == 'notes' ? 'Notes' : 'Assignment'; ?>
                                        </span>
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                    </div>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                        <input type="hidden" name="action" value="complete_activity">
                                        <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                        <input type="hidden" name="week_id" value="<?php echo $week['week_id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Mark Complete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>