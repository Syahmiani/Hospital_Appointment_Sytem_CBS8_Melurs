<?php
require 'config/db.php';

$indexes = [
    'CREATE INDEX idx_patient_user_id ON patient (user_id)',
    'CREATE INDEX idx_doctor_user_id ON doctor (user_id)',
    'CREATE INDEX idx_appointment_patient_id ON appointment (patient_id)',
    'CREATE INDEX idx_appointment_doctor_id ON appointment (doctor_id)',
    'CREATE INDEX idx_appointment_status ON appointment (status)',
    'CREATE INDEX idx_appointment_payment_status ON appointment (payment_status)',
    'CREATE INDEX idx_user_user_type ON user (user_type)',
    'CREATE INDEX idx_doctor_specialization ON doctor (specialization)',
    'CREATE INDEX idx_appointment_date ON appointment (App_Date)',
    'CREATE INDEX idx_contact_messages_status ON contact_messages (is_deleted)'
];

foreach ($indexes as $query) {
    if (mysqli_query($conn, $query)) {
        echo "Index created successfully: " . substr($query, strpos($query, 'idx_')) . "\n";
    } else {
        echo "Error creating index: " . mysqli_error($conn) . "\n";
    }
}

echo "Database optimization completed.\n";
?>
