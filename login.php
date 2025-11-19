<?php
session_start();
// Redirect logged-in users away from the login page
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'config/db.php';
$error = '';
$success = '';

// Check for registration success message (This handles the "thank you" notification)
if (isset($_GET['registration_success']) && $_GET['registration_success'] == 1) {
    // The message is now fully contained and styled in the HTML section for clarity, 
    // but we set $success to a truthy value here for consistency with the existing code structure.
    $success = true; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // Hash the input password with MD5 for comparison
    $hash = md5($password);

    $sql = "SELECT user_id, email, password, f_name, user_type FROM `user` WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_id, $uemail, $stored_hash, $f_name, $user_type);

    if (mysqli_stmt_fetch($stmt)) {
        // Compare MD5 hashes
        if ($hash === $stored_hash) {
            $_SESSION['user'] = [
                'user_id' => $user_id,
                'email' => $uemail,
                'f_name' => $f_name,
                'user_type' => $user_type
            ];
            mysqli_stmt_close($stmt);
            // Redirect based on user_type
            if ($user_type === 'admin') {
                header('Location: dashboard/admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="glass-card">
      <h2>Login</h2>
      
      <?php 
      // ---------------------------------------------------------------------
      // THANK YOU NOTIFICATION (Updated Display)
      // ---------------------------------------------------------------------
      if ($success): 
      ?>
          <div class="alert-success">
              ✅ Thank you for registering! Your account has been created. Please log in below.
          </div>
      <?php 
      endif;
      ?>

      <?php 
      // Display Login Errors
      if ($error) {
          // Changed to use alert-error class defined in the <style> block
          echo "<div class='alert-error'>".htmlspecialchars($error)."</div>"; 
      }
      ?>
      
      <form method="post">
        <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>

        <button class="btn" type="submit">Log In</button>
      </form>

      <p class="login-register-link">
        <a href="register.php">Don't have an account? Register here.</a>
      </p>
    </div>
  <script src="theme.js"></script>
</body>
</html>