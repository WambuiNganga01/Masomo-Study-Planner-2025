<?php
// dashboard.php
require_once 'config.php';
requireLogin();

class Dashboard {
    private $conn;
    private $user_id;
    
    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }
    
    // Get user profile information
    public function getUserProfile() {
        $stmt = $this->conn->prepare("
            SELECT username, email, profile_image, school, course, points 
            FROM users 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    // Get upcoming activities (due within next 7 days)
    public function getUpcomingActivities() {
        $stmt = $this->conn->prepare("
            SELECT s.subject_name, w.start_date, w.end_date, 
                   a.description, a.activity_type, a.is_completed
            FROM study_activities a
            JOIN study_weeks w ON a.week_id = w.id
            JOIN subjects s ON w.subject_id = s.id
            WHERE s.user_id = ? 
            AND w.end_date >= CURDATE() 
            AND w.end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND a.is_completed = 0
            ORDER BY w.end_date ASC
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get completion statistics
    public function getCompletionStats() {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(CASE WHEN a.is_completed = 1 THEN 1 END) as completed_activities,
                COUNT(*) as total_activities,
                COUNT(DISTINCT s.id) as total_subjects,
                COUNT(DISTINCT w.id) as total_weeks
            FROM subjects s
            LEFT JOIN study_weeks w ON s.id = w.subject_id
            LEFT JOIN study_activities a ON w.id = a.week_id
            WHERE s.user_id = ?
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    // Get recent achievements
    public function getRecentAchievements() {
        $stmt = $this->conn->prepare("
            SELECT achievement_type, description, earned_at
            FROM achievements
            WHERE user_id = ?
            ORDER BY earned_at DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get subject-wise progress
    public function getSubjectProgress() {
        $stmt = $this->conn->prepare("
            SELECT 
                s.subject_name,
                COUNT(CASE WHEN a.is_completed = 1 THEN 1 END) as completed_activities,
                COUNT(a.id) as total_activities
            FROM subjects s
            LEFT JOIN study_weeks w ON s.id = w.subject_id
            LEFT JOIN study_activities a ON w.id = a.week_id
            WHERE s.user_id = ?
            GROUP BY s.id
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Initialize dashboard
$conn = connectDB();
$dashboard = new Dashboard($conn, $_SESSION['user_id']);

// Get all dashboard data
$userProfile = $dashboard->getUserProfile();
$upcomingActivities = $dashboard->getUpcomingActivities();
$completionStats = $dashboard->getCompletionStats();
$recentAchievements = $dashboard->getRecentAchievements();
$subjectProgress = $dashboard->getSubjectProgress();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="profile-info">
                <?php if ($userProfile['profile_image']): ?>
                    <img src="<?php echo htmlspecialchars($userProfile['profile_image']); ?>" alt="Profile" class="profile-image">
                <?php endif; ?>
                <h2>Welcome, <?php echo htmlspecialchars($userProfile['username']); ?>!</h2>
                <p>School: <?php echo htmlspecialchars($userProfile['school']); ?></p>
                <p>Course: <?php echo htmlspecialchars($userProfile['course']); ?></p>
                <p>Points: <?php echo $userProfile['points']; ?></p>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <h3>Completion Rate</h3>
                <p class="stat-number">
                    <?php 
                    $completion_rate = $completionStats['total_activities'] > 0 
                        ? round(($completionStats['completed_activities'] / $completionStats['total_activities']) * 100)
                        : 0;
                    echo $completion_rate . '%';
                    ?>
                </p>
            </div>
            
            <div class="stat-card">
                <h3>Total Subjects</h3>
                <p class="stat-number"><?php echo $completionStats['total_subjects']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Study Weeks</h3>
                <p class="stat-number"><?php echo $completionStats['total_weeks']; ?></p>
            </div>
        </div>
        
        <!-- Upcoming Activities -->
        <div class="upcoming-activities">
            <h3>Upcoming Activities</h3>
            <?php if (empty($upcomingActivities)): ?>
                <p>No upcoming activities for the next 7 days.</p>
            <?php else: ?>
                <?php foreach ($upcomingActivities as $activity): ?>
                    <div class="activity-card">
                        <h4><?php echo htmlspecialchars($activity['subject_name']); ?></h4>
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <p class="due-date">Due: <?php echo date('M d, Y', strtotime($activity['end_date'])); ?></p>
                        <span class="activity-type"><?php echo ucfirst($activity['activity_type']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Subject Progress -->
        <div class="subject-progress">
            <h3>Subject Progress</h3>
            <canvas id="subjectProgressChart"></canvas>
        </div>
        
        <!-- Recent Achievements -->
        <div class="recent-achievements">
            <h3>Recent Achievements</h3>
            <?php if (empty($recentAchievements)): ?>
                <p>No achievements yet. Keep studying to earn achievements!</p>
            <?php else: ?>
                <?php foreach ($recentAchievements as $achievement): ?>
                    <div class="achievement-card">
                        <div class="achievement-icon">🏆</div>
                        <div class="achievement-info">
                            <h4><?php echo htmlspecialchars($achievement['achievement_type']); ?></h4>
                            <p><?php echo htmlspecialchars($achievement['description']); ?></p>
                            <small>Earned on: <?php echo date('M d, Y', strtotime($achievement['earned_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Create subject progress chart
    const ctx = document.getElementById('subjectProgressChart').getContext('2d');
    const subjectData = <?php echo json_encode($subjectProgress); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: subjectData.map(subject => subject.subject_name),
            datasets: [{
                label: 'Completion Rate (%)',
                data: subjectData.map(subject => 
                    subject.total_activities > 0 
                        ? (subject.completed_activities / subject.total_activities) * 100 
                        : 0
                ),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    </script>
</body>
</html>