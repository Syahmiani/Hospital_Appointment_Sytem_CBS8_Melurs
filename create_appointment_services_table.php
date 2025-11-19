<?php
require 'config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS appointment_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    App_ID INT NOT NULL,
    Service_ID INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (App_ID) REFERENCES appointment(App_ID) ON DELETE CASCADE,
    FOREIGN KEY (Service_ID) REFERENCES services(Service_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_appointment_service (App_ID, Service_ID)
);";

if (mysqli_query($conn, $sql)) {
    echo 'Table appointment_services created successfully.';
} else {
    echo 'Error creating table: ' . mysqli_error($conn);
}
?>
