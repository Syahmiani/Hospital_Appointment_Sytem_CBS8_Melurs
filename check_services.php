<?php
require 'config/db.php';

$result = mysqli_query($conn, 'SELECT Service_ID, Service_Name, Service_Price FROM services ORDER BY Service_Name');
if ($result) {
    echo 'Available services:' . PHP_EOL;
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Service_ID'] . ': ' . $row['Service_Name'] . ' - RM' . number_format($row['Service_Price'], 2) . PHP_EOL;
    }
} else {
    echo 'Error: ' . mysqli_error($conn);
}
?>
