<?php
require 'config/db.php';

echo "Verifying database structure...\n\n";

// Check tables
$tables = ['user', 'doctor', 'patient', 'services', 'appointment', 'contact_messages', 'medicine', 'prescription', 'appointment_services'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' missing\n";
    }
}

echo "\nChecking key columns...\n";

// Check appointment table columns
$result = mysqli_query($conn, "DESCRIBE appointment");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
}

$required_columns = ['is_deleted', 'deleted_at', 'deleted_by', 'payment_status', 'payment_proof', 'payment_time', 'payment_updated_by'];
foreach ($required_columns as $col) {
    if (in_array($col, $columns)) {
        echo "✓ Column '$col' exists in appointment\n";
    } else {
        echo "✗ Column '$col' missing in appointment\n";
    }
}

// Check services table columns
$result = mysqli_query($conn, "DESCRIBE services");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
}

$required_columns = ['is_deleted', 'deleted_at', 'deleted_by'];
foreach ($required_columns as $col) {
    if (in_array($col, $columns)) {
        echo "✓ Column '$col' exists in services\n";
    } else {
        echo "✗ Column '$col' missing in services\n";
    }
}

// Check contact_messages table columns
$result = mysqli_query($conn, "DESCRIBE contact_messages");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
}

$required_columns = ['parent_id', 'is_deleted', 'deleted_at', 'deleted_by'];
foreach ($required_columns as $col) {
    if (in_array($col, $columns)) {
        echo "✓ Column '$col' exists in contact_messages\n";
    } else {
        echo "✗ Column '$col' missing in contact_messages\n";
    }
}

echo "\nVerification complete.\n";
?>
