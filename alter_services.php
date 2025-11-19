<?php
require 'config/db.php';

$query = "ALTER TABLE services ADD COLUMN is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL";

if (mysqli_query($conn, $query)) {
    echo "Services table altered successfully.";
} else {
    echo "Error altering services table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
