<?php
/**
 * Database Configuration File
 *
 * This file sets up the connection to the MySQL database.
 * NOTE: The provided credentials are for local development (like XAMPP/WAMP).
 * You must create a database named 'hospital_system' for the system to function.
 */

// Database credentials
$host = "localhost";
$user = "root";
$password = ""; // Change this if you have a password
$database = "hospital_systems";

// Create connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check connection and stop execution on failure
if (!$conn) {
    // We die here because the application cannot function without a database connection.
    die("Database Connection failed: " . mysqli_connect_error());
}

// Set character set
mysqli_set_charset($conn, "utf8");

// Constants for common values
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $password);
define('DB_NAME', $database);

// Status constants
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_DISAPPROVED', 'disapproved');
define('STATUS_DONE', 'done');
define('STATUS_CANCELLED', 'cancel');

// Payment status constants
define('PAYMENT_UNPAID', 'unpaid');
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_PAID', 'paid');
define('PAYMENT_REJECTED', 'rejected');

// User types
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_DOCTOR', 'doctor');
define('USER_TYPE_PATIENT', 'patient');

// File upload constants
define('UPLOAD_PATH_PAYMENTS', '../uploads/payments/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf'
]);

// CSRF token generation
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate limiting helper
function check_rate_limit($key, $max_attempts = 5, $time_window = 300) {
    $now = time();
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['attempts' => 0, 'first_attempt' => $now];
    }

    $rate_data = &$_SESSION['rate_limit'][$key];

    // Reset if time window has passed
    if ($now - $rate_data['first_attempt'] > $time_window) {
        $rate_data['attempts'] = 0;
        $rate_data['first_attempt'] = $now;
    }

    $rate_data['attempts']++;

    return $rate_data['attempts'] <= $max_attempts;
}

// Error logging function
function log_error($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . " - ERROR: " . $message;
    if (!empty($context)) {
        $log_message .= " - Context: " . json_encode($context);
    }
    $log_message .= "\n";

    // Log to file (create logs directory if it doesn't exist)
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_dir . '/error.log', $log_message, FILE_APPEND);
}

// Success logging function
function log_success($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . " - SUCCESS: " . $message;
    if (!empty($context)) {
        $log_message .= " - Context: " . json_encode($context);
    }
    $log_message .= "\n";

    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_dir . '/success.log', $log_message, FILE_APPEND);
}

?>
