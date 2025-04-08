<?php
// File: includes/navigation.php
$current_page = basename($_SERVER['PHP_SELF']);

// Count notifications (pending activities)
$notification_count = 0;
if (isset($_SESSION['user_id'])) {
    $conn = connectDB();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM study_activities a
        JOIN study_weeks w ON a.week_id = w.id
        JOIN subjects s ON w.subject_id = s.id
        WHERE s.user_id = ? AND a.is_completed = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $notification_count = $result['count'];
    $stmt->close();
}
?>

<nav class="main-nav">
    <div class="nav-brand">
        <a href="dashboard.php" style="color:#929a9a !important;"><Strong style="color:#459173 !important;">Masomo</Strong>Planner</a>
    </div>
    
    <ul class="nav-links">
        <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">Dashboard</a>
        </li>
        
        <li class="<?php echo $current_page == 'subjects.php' ? 'active' : ''; ?>">
            <a href="subjects.php">Subjects</a>
        </li>
        
        <li class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
            <a href="notifications.php">Notifications
                <?php if ($notification_count > 0): ?>
                    <span class="notification-badge"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="<?php echo $current_page == 'achievements.php' ? 'active' : ''; ?>">
            <a href="achievements.php">Achievements</a>
        </li>
        
        <li class="profile-menu">
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </li>
    </ul>
</nav>

<style>
.main-nav {
    background-color: #ffffff;
    padding: 1rem 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand a {
    font-size: 1.5rem;
    font-weight: bold;
    color: #459173;
    text-decoration: none;
}

.nav-links {
    list-style: none;
    display: flex;
    gap: 2rem;
    margin: 0;
    padding: 0;
    align-items: center;
}

.nav-links li a {
    color: #333;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-links li.active a {
    background-color: #459173;
    color: white;
}

.nav-links li a:hover {
    background-color: #f8f9fa;
    color: #459173;
}

.nav-links li.active a:hover {
    background-color:rgb(64, 132, 105);
    color: white;
}

.profile-menu {
    display: flex;
    gap: 1rem;
}

.profile-menu a:last-child {
    color: #dc3545;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #dc3545;
    color: white;
    font-size: 12px;
    height: 20px;
    width: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .main-nav {
        flex-direction: column;
        padding: 1rem;
    }
    
    .nav-links {
        flex-direction: column;
        width: 100%;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .nav-links li {
        width: 100%;
        text-align: center;
    }
    
    .profile-menu {
        flex-direction: column;
        width: 100%;
    }
    
    .notification-badge {
        position: relative;
        top: -2px;
        right: -5px;
        display: inline-flex;
    }
}
</style>