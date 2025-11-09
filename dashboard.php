<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_type = $_SESSION['user']['user_type'] ?? null;
$destination = 'login.php'; // Default redirect location

// Whitelist of allowed user types and their dashboard paths
$dashboards = [
    'admin'   => 'dashboard/admin.php',
    'doctor'  => 'dashboard/doctor.php',
    'patient' => 'dashboard/patient.php'
];

if ($user_type && isset($dashboards[$user_type])) {
    $destination = $dashboards[$user_type];
}

header('Location: ' . $destination);
exit;
?>