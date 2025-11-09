<?php
session_start();
// IMPORTANT: Adjust this path if your config/db.php is in a different location
require 'config/db.php';
require 'vendor/autoload.php'; // Load Composer autoload for PHPMailer

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get and sanitize input
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    
    // 2. Validation
    if (empty($name)) $errors[] = 'Your name is required.';
    if (!$email) $errors[] = 'A valid email is required.';
    if (empty($message)) $errors[] = 'A message cannot be empty.';

    if (empty($errors)) {
        // 3. Prepare and execute the INSERT query
        $sql = "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $message);
            $inserted = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($inserted) {
                // Send email using PHPMailer
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'aimshooter02@gmail.com';
                    $mail->Password = 'sgdz hodm cybj isav';
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('aimshooter02@gmail.com', 'Hospital System');
                    $mail->addAddress('contact@hospital.example', 'Hospital Contact');

                    $mail->isHTML(false);
                    $mail->Subject = 'New Contact Message from ' . $name;
                    $mail->Body = "Name: $name\nEmail: $email\n\nMessage:\n$message";

                    $mail->send();
                    // SUCCESS: Redirect back to index with a success flag
                    header('Location: index.php?msg_status=success');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Message saved, but failed to send email: ' . $mail->ErrorInfo;
                }
            } else {
                // DB Failure
                $errors[] = 'Failed to save message to the database.';
            }
        } else {
            $errors[] = 'Database error during preparation.';
        }
    }
}

// If there are validation errors or DB failure, store errors in session and redirect
if (!empty($errors)) {
    $_SESSION['msg_errors'] = $errors;
    header('Location: index.php?msg_status=error');
    exit;
}
// Fallback
header('Location: index.php');
exit;
?>