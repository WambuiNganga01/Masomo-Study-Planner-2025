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
            border-bottom: 1px solid #e