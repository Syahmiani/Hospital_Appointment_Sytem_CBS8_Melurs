<?php
session_start();
require 'config/db.php';

// ---------------------------------------------------------------------
// 1. ACCESS CONTROL & INITIAL FETCH
// ---------------------------------------------------------------------

// Check if user is logged in and is an admin
if (empty($_SESSION['user']) || $_SESSION['user']['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Ensure a service ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard/admin.php?tab=services&error=invalid_id');
    exit;
}

$service_id = (int)$_GET['id'];
$service_data = null;
$error = '';
$success = '';

// Fetch existing service data
$sql_fetch = "SELECT Service_ID, Service_Name, Service_Price, Available FROM services WHERE Service_ID = ? AND (is_deleted = 0 OR is_deleted IS NULL)";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, 'i', $service_id);
mysqli_stmt_execute($stmt_fetch);
$res_fetch = mysqli_stmt_get_result($stmt_fetch);
$service_data = mysqli_fetch_assoc($res_fetch);
mysqli_stmt_close($stmt_fetch);

// If service not found, redirect
if (!$service_data) {
    header('Location: dashboard/admin.php?tab=services&error=not_found');
    exit;
}

// ---------------------------------------------------------------------
// 2. FORM SUBMISSION (UPDATE LOGIC)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $service_name = trim($_POST['service_name'] ?? '');
    $service_price = floatval($_POST['service_price'] ?? 0);
    $available = $_POST['available'] ?? '';

    // Simple Validation
    if (empty($service_name)) $error = 'Service Name is required.';
    if ($service_price <= 0) $error = 'Service Price must be greater than 0.';
    if (!in_array($available, ['0', '1'])) $error = 'Availability must be selected.';

    if (empty($error)) {
        $sql_update = "UPDATE services SET Service_Name = ?, Service_Price = ?, Available = ? WHERE Service_ID = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        if ($stmt_update) {
            $available_int = intval($available);
            mysqli_stmt_bind_param($stmt_update, 'sdii', $service_name, $service_price, $available_int, $service_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $success = 'Service details updated successfully.';
                // Re-fetch data to show updated values in the form
                $service_data['Service_Name'] = $service_name;
                $service_data['Service_Price'] = $service_price;
                $service_data['Available'] = $available;
            } else {
                $error = 'Database update failed. Please try again.';
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error = 'Failed to prepare update statement.';
        }
    }
}

// Use current (or updated) data for form defaults
$service_name_val = htmlspecialchars($_POST['service_name'] ?? $service_data['Service_Name']);
$service_price_val = htmlspecialchars($_POST['service_price'] ?? $service_data['Service_Price']);
$available_val = htmlspecialchars($_POST['available'] ?? $service_data['Available']);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service #<?=htmlspecialchars($service_id)?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="brand">Admin Panel</div>
        <nav class="nav">
            <a href="dashboard/admin.php?tab=services">Back to Services</a>
            <a href="logout.php" class="btn">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="edit-form-container">
            <h2>Edit Service: <?=htmlspecialchars($service_data['Service_Name'])?> (#<?=htmlspecialchars($service_id)?>)</h2>

            <?php if ($success) echo "<div class='alert-success'>{$success}</div>"; ?>
            <?php if ($error) echo "<div class='alert-error'>{$error}</div>"; ?>

            <form method="post">
                <h3>Service Details</h3>
                <div class="form-group">
                    <label for="service_name">Service Name</label>
                    <input type="text" id="service_name" name="service_name" value="<?=$service_name_val?>" required>
                </div>
                <div class="form-group">
                    <label for="service_price">Price (RM)</label>
                    <input type="number" step="0.01" id="service_price" name="service_price" value="<?=$service_price_val?>" required>
                </div>
                <div class="form-group">
                    <label for="available">Available</label>
                    <select id="available" name="available" required>
                        <option value="1" <?=$available_val == '1' ? 'selected' : ''?>>Yes</option>
                        <option value="0" <?=$available_val == '0' ? 'selected' : ''?>>No</option>
                    </select>
                </div>

                <button class="btn" type="submit">Update Service Details</button>
                <a href="dashboard/admin.php?tab=services" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <footer class="footer">
        &copy; <?= date('Y') ?> Hospital System. Admin Portal.
    </footer>
</body>
</html>
