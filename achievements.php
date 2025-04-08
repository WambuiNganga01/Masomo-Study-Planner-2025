<?php
// achievements.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT username, points FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all user achievements
$stmt = $conn->prepare("
    SELECT * FROM achievements 
    WHERE user_id = ?
    ORDER BY earned_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get study stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_subjects,
        COUNT(DISTINCT w.id) as total_weeks,
        COUNT(a.id) as total_activities,
        SUM(CASE WHEN a.is_completed = 1 THEN 1 ELSE 0 END) as completed_activities
    FROM subjects s
    LEFT JOIN study_weeks w ON s.id = w.subject_id
    LEFT JOIN study_activities a ON w.id = a.week_id
    WHERE s.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate completion percentage
$completion_percentage = 0;
if ($stats['total_activities'] > 0) {
    $completion_percentage = round(($stats['completed_activities'] / $stats['total_activities']) * 100);
}

// Define available badges and user levels
$badges = [
    'beginner' => [
        'name' => 'Study Beginner',
        'icon' => '🔰',
        'points_required' => 0
    ],
    'intermediate' => [
        'name' => 'Study Intermediate',
        'icon' => '📚',
        'points_required' => 100
    ],
    'advanced' => [
        'name' => 'Study Advanced',
        'icon' => '🎓',
        'points_required' => 500
    ],
    'master' => [
        'name' => 'Study Master',
        'icon' => '🏆',
        'points_required' => 1000
    ],
    'genius' => [
        'name' => 'Study Genius',
        'icon' => '🧠',
        'points_required' => 2000
    ]
];

// Determine user's current level
$current_level = 'beginner';
foreach ($badges as $level => $badge) {
    if ($user['points'] >= $badge['points_required']) {
        $current_level = $level;
    } else {
        break;
    }
}

// Calculate progress to next level
$next_level = null;
$next_level_points = 0;
$current_level_points = $badges[$current_level]['points_required'];
$progress_percentage = 100; // Default if at max level

foreach ($badges as $level => $badge) {
    if ($badge['points_required'] > $user['points']) {
        $next_level = $level;
        $next_level_points = $badge['points_required'];
        break;
    }
}

if ($next_level) {
    $points_needed = $next_level_points - $user['points'];
    $points_range = $next_level_points - $current_level_points;
    $progress_percentage = round((($user['points'] - $current_level_points) / $points_range) * 100);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Achievements - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .achievement-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .achievement-container {
                grid-template-columns: 1fr 2fr;
            }
        }
        
        .user-profile-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .user-level {
            font-size: 24px;
            margin: 10px 0;
        }
        
        .user-level-icon {
            font-size: 48px;
            margin: 15px 0;
        }
        
        .points-display {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .progress-container {
            background-color: #e9ecef;
            border-radius: 4px;
            height: 20px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            transition: width 0.5s;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .achievement-list {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .achievement-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .achievement-card:last-child {
            border-bottom: none;
        }
        
        .achievement-icon {
            font-size: 30px;
            margin-right: 20px;
        }
        
        .achievement-info {
            flex: 1;
        }
        
        .achievement-date {
            color: #6c757d;
            font-size: 14px;
        }
        
        .level-progress {
            margin-top: 20px;
        }
        
        .empty-achievements {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .badges-section {
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .badge-item {
            text-align: center;
            padding: 15px 10px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .badge-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .badge-name {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .badge-points {
            font-size: 12px;
            color: #6c757d;
        }
        
        .badge-locked {
            opacity: 0.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <h1>Achievements</h1>
        
        <div class="achievement-container">
            <div class="user-profile-card">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <div class="user-level-icon"><?php echo $badges[$current_level]['icon']; ?></div>
                <div class="user-level"><?php echo $badges[$current_level]['name']; ?></div>
                <div class="points-display"><?php echo $user['points']; ?> Points</div>
                
                <?php if ($next_level): ?>
                <div class="level-progress">
                    <p>Next Level: <?php echo $badges[$next_level]['name']; ?> (<?php echo $next_level_points; ?> Points)</p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%">
                            <?php echo $progress_percentage; ?>%
                        </div>
                    </div>
                    <small><?php echo $next_level_points - $user['points']; ?> points needed</small>
                </div>
                <?php else: ?>
                <div class="level-progress">
                    <p>Maximum Level Reached!</p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: 100%">100%</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_subjects']; ?></div>
                        <div>Subjects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_weeks']; ?></div>
                        <div>Weeks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['completed_activities']; ?></div>
                        <div>Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $completion_percentage; ?>%</div>
                        <div>Completion</div>
                    </div>
                </div>
                
                <div class="badges-section">
                    <h3>Available Badges</h3>
                    <div class="badges-grid">
                        <?php foreach ($badges as $level => $badge): ?>
                            <div class="badge-item <?php echo $user['points'] < $badge['points_required'] ? 'badge-locked' : ''; ?>">
                                <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                                <div class="badge-name"><?php echo $badge['name']; ?></div>
                                <div class="badge-points"><?php echo $badge['points_required']; ?> points</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="achievement-list">
                <h2>Your Achievements</h2>
                
                <?php if (empty($achievements)): ?>
                    <div class="empty-achievements">
                        <h3>No achievements yet</h3>
                        <p>Complete activities to earn achievements and points!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="achievement-card">
                            <div class="achievement-icon">🏆</div>
                            <div class="achievement-info">
                                <h3><?php echo htmlspecialchars($achievement['achievement_type']); ?></h3>
                                <p><?php echo htmlspecialchars($achievement['description']); ?></p>
                                <div class="achievement-date">
                                    Earned on <?php echo date('F j, Y', strtotime($achievement['earned_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>