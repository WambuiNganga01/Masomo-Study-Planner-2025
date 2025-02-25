<?php
// login.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            header("Location: dashboard.php");
            exit();
        }
    }
    
    $error = "Invalid email or password";
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <?php 
        if (isset($error)) echo "<p class='error'>$error</p>";
        if (isset($_SESSION['success'])) {
            echo "<p class='success'>{$_SESSION['success']}</p>";
            unset($_SESSION['success']);
        }
        ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>

