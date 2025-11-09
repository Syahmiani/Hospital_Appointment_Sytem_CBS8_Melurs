<?php
require 'config/db.php';

$sql = "ALTER TABLE contact_messages ADD COLUMN parent_id INT NULL, ADD FOREIGN KEY (parent_id) REFERENCES contact_messages(id) ON DELETE CASCADE";

if (mysqli_query($conn, $sql)) {
    echo "Table altered successfully.";
} else {
    echo "Error altering table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
