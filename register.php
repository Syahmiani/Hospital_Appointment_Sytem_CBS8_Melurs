<?php
session_start();
// Assumes config/db.php is located one folder above (e.g., /config/db.php)
require 'config/db.php'; 

// Check if a registration type is specified, default to patient
$reg_type = $_GET['type'] ?? 'patient'; 
$errors = [];
$success = '';

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ------------------------------------------
    // 1. COLLECT AND SANITIZE COMMON USER DATA
    // ------------------------------------------
    $f_name = trim($_POST['f_name'] ?? '');
    $l_name = trim($_POST['l_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $reg_type; 
    
    // Basic Validation
    if (empty($f_name) || empty($l_name) || empty($email) || empty($password)) {
        $errors[] = "First Name, Last Name, Email, and Password are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check for existing email address
    if (empty($errors)) {
        $check_email_sql = "SELECT user_id FROM user WHERE email = ?";
        $stmt_check = mysqli_prepare($conn, $check_email_sql);
        mysqli_stmt_bind_param($stmt_check, 's', $email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $errors[] = "This email is already registered.";
        }
        mysqli_stmt_close($stmt_check);
    }

    // Hash the password using MD5 for this project
    if (empty($errors)) {
        $hashed_password = md5($password);
    }
    // ------------------------------------------
    // 2. DOCTOR SPECIFIC REGISTRATION LOGIC
    // ------------------------------------------
    if ($reg_type === 'doctor' && empty($errors)) {
        
        $specialization = trim($_POST['specialization'] ?? '');
        $room_no = trim($_POST['room_no'] ?? '');
        
        if (empty($specialization) || empty($room_no)) {
             $errors[] = "Specialization and Room No. are required for a doctor.";
        }
        
        // --- DATABASE TRANSACTION ---
        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            
            try {
                // STEP A: INSERT into 'user' table
                $sql_user = "INSERT INTO user (f_name, l_name, email, password, phone, user_type) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_user = mysqli_prepare($conn, $sql_user);
                mysqli_stmt_bind_param($stmt_user, 'ssssss', $f_name, $l_name, $email, $hashed_password, $phone, $user_type);
                mysqli_stmt_execute($stmt_user);
                $new_user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_user);

                // STEP B: INSERT into 'doctor' table
                $sql_doctor = "INSERT INTO doctor (user_id, specialization, room_no) 
                               VALUES (?, ?, ?)";
                $stmt_doctor = mysqli_prepare($conn, $sql_doctor);
                mysqli_stmt_bind_param($stmt_doctor, 'iss', $new_user_id, $specialization, $room_no);
                mysqli_stmt_execute($stmt_doctor);
                mysqli_stmt_close($stmt_doctor);

                // Commit and redirect to admin page if successful
                mysqli_commit($conn);
                if (isset($_SESSION['user']['user_type']) && $_SESSION['user']['user_type'] === 'admin') {
                    header('Location: dashboard/admin.php?tab=doctors&status=added');
                    exit;
                }
                $success = "Doctor registration complete. You can now log in.";
                
            } catch (Exception $e) {
                // Rollback and handle error
                mysqli_rollback($conn);
                error_log("Doctor registration failed: " . $e->getMessage()); 
                $errors[] = "Registration failed due to a server error. Please contact the administrator.";
            }
        }
    }

    // ------------------------------------------
    // 3. PATIENT REGISTRATION LOGIC
    // ------------------------------------------
    if ($reg_type === 'patient' && empty($errors)) {
        // --- PATIENT LOGIC ---
        mysqli_begin_transaction($conn);
        try {
            // STEP A: INSERT into 'user' table
            $sql_user = "INSERT INTO user (f_name, l_name, email, password, phone, user_type) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_user = mysqli_prepare($conn, $sql_user);
            mysqli_stmt_bind_param($stmt_user, 'ssssss', $f_name, $l_name, $email, $hashed_password, $phone, $user_type);
            mysqli_stmt_execute($stmt_user);
            $new_user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_user);

            // STEP B: INSERT into 'patient' table (no extra fields assumed)
            $sql_patient = "INSERT INTO patient (user_id) VALUES (?)";
            $stmt_patient = mysqli_prepare($conn, $sql_patient);
            mysqli_stmt_bind_param($stmt_patient, 'i', $new_user_id);
            mysqli_stmt_execute($stmt_patient);
            mysqli_stmt_close($stmt_patient);

            mysqli_commit($conn);
            $success = "Patient registration complete. You can now log in.";
            // Redirect to login with a success message after patient registration
            header('Location: login.php?registration_success=1');
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Patient registration failed: " . $e->getMessage()); 
            $errors[] = "Registration failed due to a server error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucwords($reg_type) ?> Registration</title>
    <!-- Tailwind is included for modern styling environment, though most styles are custom/transcribed -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="glass-card">
        <h1><?= ($reg_type === 'doctor') ? 'Register New Doctor' : 'Patient Registration' ?></h1>
        
        <?php 
        if ($success) echo "<div class='alert alert-success'>".htmlspecialchars($success)."</div>";
        if ($errors) foreach($errors as $e) echo "<div class='alert alert-error'>".htmlspecialchars($e)."</div>"; 
        ?>

        <?php if ($reg_type === 'doctor'): ?>
            <form method="post" action="register.php?type=doctor">
                <p class="text-sm text-gray">Admin use only: Link will redirect to Admin Dashboard on success.</p>
                <div class="form-row">
                    <div class="form-group form-col"><label>First Name</label><input type="text" name="f_name" required value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>"></div>
                    <div class="form-group form-col"><label>Last Name</label><input type="text" name="l_name" required value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>"></div>
                </div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>"></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?=htmlspecialchars($_POST['phone'] ?? '')?>"></div>
                
                <div class="doctor-fields">
                    <h3 class="mt-md mb-xs">Doctor Details</h3>
                    <div class="form-row">
                        <div class="form-group form-col"><label>Specialization</label><input type="text" name="specialization" required value="<?=htmlspecialchars($_POST['specialization'] ?? '')?>"></div>
                        <div class="form-group form-col"><label>Room Number</label><input type="text" name="room_no" required value="<?=htmlspecialchars($_POST['room_no'] ?? '')?>"></div>
                    </div>
                </div>
                
                <button class="btn" type="submit">Register Doctor</button>
                <?php if (isset($_SESSION['user']['user_type']) && $_SESSION['user']['user_type'] === 'admin'): ?>
                    <p class="mt-sm"><a href="dashboard/admin.php?tab=doctors">Back to Admin Panel</a></p>
                <?php endif; ?>
            </form>

        <?php else: ?>
            <form method="post" action="register.php?type=patient">
                <p class="text-sm text-gray">Please fill out the form to register as a patient.</p>
                <div class="form-row">
                    <div class="form-group form-col"><label>First Name</label><input type="text" name="f_name" required value="<?=htmlspecialchars($_POST['f_name'] ?? '')?>"></div>
                    <div class="form-group form-col"><label>Last Name</label><input type="text" name="l_name" required value="<?=htmlspecialchars($_POST['l_name'] ?? '')?>"></div>
                </div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>"></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?=htmlspecialchars($_POST['phone'] ?? '')?>"></div>
                
                <button class="btn" type="submit">Register Patient</button>
                <p class="mt-sm">Already have an account? <a href="login.php">Log in here</a></p>
            </form>

        <?php endif; ?>
    </div>
    <script src="theme.js"></script>
</body>
</html>