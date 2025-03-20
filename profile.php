<?php
// profile.php
require_once 'config.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT username, email, profile_image, school, course FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$userData = getUserData($conn, $user_id);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = $_POST['username'];
    $email = $_POST['email'];
    $school = $_POST['school'];
    $course = $_POST['course'];
    
    // Check if a new profile image was uploaded
    $profile_image = $userData['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Verify file extension
        if (in_array(strtolower($filetype), $allowed)) {
            // Create unique file name
            $newFilename = "profile_" . $user_id . "_" . time() . "." . $filetype;
            $uploadPath = "uploads/" . $newFilename;
            
            // Create uploads directory if it doesn't exist
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // Upload file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                $profile_image = $uploadPath;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Please upload JPG, PNG, or GIF.";
        }
    }
    
    // Update profile in database if no errors
    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_image = ?, school = ?, course = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $username, $email, $profile_image, $school, $course, $user_id);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $userData = getUserData($conn, $user_id); // Refresh user data
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile - Study Planner</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <div class="form-container" style="max-width: 600px;">
            <div class="form-header">
                <h2>Edit Profile</h2>
                <p>Update your personal information</p>
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
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <div class="profile-image-container" style="text-align: center; margin-bottom: 20px;">
                    <?php if (!empty($userData['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($userData['profile_image']); ?>" alt="Profile" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 150px; height: 150px; border-radius: 50%; background-color: #e9ecef; display: inline-flex; align-items: center; justify-content: center; font-size: 40px; color: #adb5bd;">
                            <?php echo strtoupper(substr($userData['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="profile_image">Profile Picture:</label>
                    <input type="file" class="form-control" id="profile_image" name="profile_image">
                </div>
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="school">School/University:</label>
                    <input type="text" class="form-control" id="school" name="school" value="<?php echo htmlspecialchars($userData['school'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="course">Course/Program:</label>
                    <input type="text" class="form-control" id="course" name="course" value="<?php echo htmlspecialchars($userData['course'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
</body>
</html>