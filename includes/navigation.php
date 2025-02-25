<?php
// File: includes/navigation.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="main-nav">
    <div class="nav-brand">
        <a href="dashboard.php">Masomo Study Planner</a>
    </div>
    
    <ul class="nav-links">
        <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">Dashboard</a>
        </li>
        
        <li class="<?php echo $current_page == 'subjects.php' ? 'active' : ''; ?>">
            <a href="subjects.php">Subjects</a>
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
    color: #007bff;
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
}

.nav-links li.active a {
    background-color: #007bff;
    color: white;
}

.nav-links li a:hover {
    background-color: #f8f9fa;
    color: #007bff;
}

.nav-links li.active a:hover {
    background-color: #0056b3;
    color: white;
}

.profile-menu {
    display: flex;
    gap: 1rem;
}

.profile-menu a:last-child {
    color: #dc3545;
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
}
</style>