<?php
session_start();
// PATH CHANGE: Adjusted for file moved to root directory
require 'config/db.php'; 

// ---------------------------------------------------------------------
// 1. ACCESS CONTROL & INITIAL FETCH
// ---------------------------------------------------------------------

// Check if user is logged in and is an admin
if (empty($_SESSION['user']) || $_SESSION['user']['user_type'] !== 'admin') {
    header('Location: login.php'); // Path relative to root
    exit;
}

// Ensure a doctor ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // PATH CHANGE: Redirect to admin/admin.php
    header('Location: admin/admin.php?tab=doctors&error=invalid_id');
    exit;
}

$doctor_id = (int)$_GET['id'];
$doctor_data = null;
$error = '';
$success = '';

// Fetch existing doctor data
$sql_fetch = "SELECT d.doctor_id, d.user_id, d.specialization, d.room_no, u.f_name, u.l_name, u.email, u.phone
              FROM doctor d 
              JOIN `user` u ON d.user_id = u.user_id 
              WHERE d.doctor_id = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, 'i', $doctor_id);
mysqli_stmt_execute($stmt_fetch);
$res_fetch = mysqli_stmt_get_result($stmt_fetch);
$doctor_data = mysqli_fetch_assoc($res_fetch);
mysqli_stmt_close($stmt_fetch);

// If doctor not found, redirect
if (!$doctor_data) {
    // PATH CHANGE: Redirect to admin/admin.php
    header('Location: admin/admin.php?tab=doctors&error=not_found');
    exit;
}

// ---------------------------------------------------------------------
// 2. FORM SUBMISSION (UPDATE LOGIC)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $f_name = trim($_POST['f_name'] ?? '');
    $l_name = trim($_POST['l_name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $room_no = trim($_POST['room_no'] ?? '');
    
    // Simple Validation
    if (empty($f_name) || empty($l_name)) $error = 'First Name and Last Name are required.';
    if (empty($specialization)) $error = 'Specialization is required.';
    if (empty($room_no)) $error = 'Room Number is required.';

    if (empty($error)) {
        $user_id = $doctor_data['user_id'];
        
        // Start a transaction for updating two tables
        mysqli_begin_transaction($conn);
        $all_success = true;

        try {
            // --- STEP 1: Update the USER table (Name) ---
            $sql_user_update = "UPDATE `user` SET f_name = ?, l_name = ? WHERE user_id = ?";
            $stmt_user = mysqli_prepare($conn, $sql_user_update);
            if ($stmt_user) {
                mysqli_stmt_bind_param($stmt_user, 'ssi', $f_name, $l_name, $user_id);
                mysqli_stmt_execute($stmt_user);
                if (mysqli_stmt_error($stmt_user)) $all_success = false;
                mysqli_stmt_close($stmt_user);
            } else { $all_success = false; }

            // --- STEP 2: Update the DOCTOR table (Specialization, Room) ---
            $sql_doctor_update = "UPDATE doctor SET specialization = ?, room_no = ? WHERE doctor_id = ?";
            $stmt_doctor = mysqli_prepare($conn, $sql_doctor_update);
            if ($stmt_doctor) {
                mysqli_stmt_bind_param($stmt_doctor, 'ssi', $specialization, $room_no, $doctor_id);
                mysqli_stmt_execute($stmt_doctor);
                if (mysqli_stmt_error($stmt_doctor)) $all_success = false;
                mysqli_stmt_close($stmt_doctor);
            } else { $all_success = false; }

            if ($all_success) {
                mysqli_commit($conn);
                $success = 'Doctor details updated successfully.';
                // Re-fetch data to show updated values in the form
                $doctor_data['f_name'] = $f_name;
                $doctor_data['l_name'] = $l_name;
                $doctor_data['specialization'] = $specialization;
                $doctor_data['room_no'] = $room_no;
            } else {
                mysqli_rollback($conn);
                $error = 'Database update failed. Please try again.';
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }
}

// Use current (or updated) data for form defaults
$f_name_val = htmlspecialchars($_POST['f_name'] ?? $doctor_data['f_name']);
$l_name_val = htmlspecialchars($_POST['l_name'] ?? $doctor_data['l_name']);
$specialization_val = htmlspecialchars($_POST['specialization'] ?? $doctor_data['specialization']);
$room_no_val = htmlspecialchars($_POST['room_no'] ?? $doctor_data['room_no']);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor #<?=htmlspecialchars($doctor_id)?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <header class="header">
        <div class="brand">Admin Panel</div>
        <nav class="nav">
            <a href="dashboard/admin.php?tab=doctors">Back to Doctors</a> 
            <a href="logout.php" class="btn">Logout</a> 
        </nav>
    </header>

    <div class="container">
        <div class="edit-form-container">
            <h2>Edit Doctor: Dr. <?=htmlspecialchars($doctor_data['f_name'] . ' ' . $doctor_data['l_name'])?> (#<?=htmlspecialchars($doctor_id)?>)</h2>
            
            <?php if ($success) echo "<div class='alert-success'>{$success}</div>"; ?>
            <?php if ($error) echo "<div class='alert-error'>{$error}</div>"; ?>

            <form method="post">
                <h3>Personal Details</h3>
                <div class="form-group">
                    <label for="f_name">First Name</label>
                    <input type="text" id="f_name" name="f_name" value="<?=$f_name_val?>" required>
                </div>
                <div class="form-group">
                    <label for="l_name">Last Name</label>
                    <input type="text" id="l_name" name="l_name" value="<?=$l_name_val?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email (Read-Only)</label>
                    <input type="text" id="email" value="<?=htmlspecialchars($doctor_data['email'])?>" disabled>
                </div>
                
                <h3>Medical Details</h3>
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" value="<?=$specialization_val?>" required>
                </div>
                <div class="form-group">
                    <label for="room_no">Room Number</label>
                    <input type="text" id="room_no" name="room_no" value="<?=$room_no_val?>" required>
                </div>
                
                <button class="btn" type="submit">Update Doctor Details</button>
                <a href="admin/admin.php?tab=doctors" class="btn btn-secondary">Cancel</a> 
            </form>
        </div>
    </div>
    
    <footer class="footer">
        &copy; <?= date('Y') ?> Hospital System. Admin Portal.
    </footer>
</body>
</html>