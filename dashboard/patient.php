<?php
session_start();
require '../config/db.php'; 

if (empty($_SESSION['user']) || $_SESSION['user']['user_type'] !== 'patient') {
    header('Location: ../login.php'); exit;
}
$uid = $_SESSION['user']['user_id'];
$patient_name = $_SESSION['user']['f_name'];
$user_email = $_SESSION['user']['email'];
$current_tab = $_GET['tab'] ?? 'appointments';

// ---------------------------------------------------------------------
// 1. GET PATIENT ID AND HANDLE STATUS MESSAGES
// ---------------------------------------------------------------------

$sql = "SELECT patient_id FROM patient WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $patient_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$success_message = '';
$error_message = '';

if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $success_message = 'Your appointment has been successfully cancelled.';
}
if (isset($_GET['error']) && $_GET['error'] === 'delete') {
    $error_message = 'Error: Could not delete the appointment. Please try again.';
}
if (isset($_GET['status']) && $_GET['status'] === 'message_deleted') {
    $success_message = 'Message deleted successfully.';
}
if (isset($_GET['error']) && $_GET['error'] === 'message_delete') {
    $error_message = 'Error: Could not delete the message. Please try again.';
}

// ---------------------------------------------------------------------
// 2. PAYMENT UPLOAD LOGIC
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_payment') {
    $app_id = intval($_POST['app_id']);
    
    // Verify appointment belongs to patient
    $sql_check = "SELECT App_ID FROM appointment WHERE App_ID = ? AND patient_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'ii', $app_id, $patient_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        mysqli_stmt_close($stmt_check);
        
        // Handle file upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payments/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $file_name = 'payment_' . $app_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            // Validate file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
                    // Update database
                    $sql_update = "UPDATE appointment 
                                  SET payment_status = 'pending', payment_proof = ?, payment_time = NOW() 
                                  WHERE App_ID = ?";
                    $stmt_update = mysqli_prepare($conn, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, 'si', $file_name, $app_id);
                    
                    if (mysqli_stmt_execute($stmt_update)) {
                        $success_message = 'Payment proof uploaded successfully! Waiting for admin verification.';
                    } else {
                        $error_message = 'Error updating payment status. Please try again.';
                        // Delete uploaded file if DB update fails
                        unlink($file_path);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $error_message = 'Error uploading file. Please try again.';
                }
            } else {
                $error_message = 'Invalid file type. Please upload JPG, PNG, GIF, or PDF files only.';
            }
        } else {
            $error_message = 'Please select a payment proof file.';
        }
    } else {
        $error_message = 'Invalid appointment.';
    }
}

// ---------------------------------------------------------------------
// 3. SOFT DELETE LOGIC (Instead of permanent delete)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    
    // Verify ownership
    $sql_check = "SELECT App_ID FROM appointment WHERE App_ID = ? AND patient_id = ? AND (is_deleted IS NULL OR is_deleted = 0)";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'ii', $app_id, $patient_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        mysqli_stmt_close($stmt_check);
        
        // Soft delete - Use user_id ($uid) instead of patient_id for deleted_by
        $sql_delete = "UPDATE appointment 
                       SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? 
                       WHERE App_ID = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, 'ii', $uid, $app_id);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            header('Location: patient.php?status=deleted');
            exit;
        } else {
            header('Location: patient.php?error=delete');
            exit;
        }
        mysqli_stmt_close($stmt_delete);
        
    } else {
        mysqli_stmt_close($stmt_check);
        header('Location: patient.php?error=delete');
        exit;
    }
}

// ---------------------------------------------------------------------
// 4. FETCH ACTIVE APPOINTMENTS
// ---------------------------------------------------------------------

$appts = [];
$sql2 = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status, a.payment_proof,
                ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization, s.Service_Name, s.Service_Price
         FROM appointment a
         JOIN doctor d ON a.doctor_id=d.doctor_id
         JOIN user ud ON d.user_id=ud.user_id
         JOIN services s ON a.Service_ID = s.Service_ID
         WHERE a.patient_id = ? AND (a.is_deleted IS NULL OR a.is_deleted = 0)
         ORDER BY a.App_Date DESC, a.App_Time DESC";
$stmt2 = mysqli_prepare($conn, $sql2);
mysqli_stmt_bind_param($stmt2, 'i', $patient_id);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);
if ($res2) {
    while ($r = mysqli_fetch_assoc($res2)) {
        $appts[] = $r;
    }
}
mysqli_stmt_close($stmt2);

// ---------------------------------------------------------------------
// 5. FETCH DELETED (HISTORY) APPOINTMENTS
// ---------------------------------------------------------------------

$deleted_appts = [];
$sql3 = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.deleted_at, a.payment_status,
                ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization, s.Service_Name, s.Service_Price
         FROM appointment a
         JOIN doctor d ON a.doctor_id=d.doctor_id
         JOIN user ud ON d.user_id=ud.user_id
         JOIN services s ON a.Service_ID = s.Service_ID
         WHERE a.patient_id = ? AND a.is_deleted = 1
         ORDER BY a.deleted_at DESC";
$stmt3 = mysqli_prepare($conn, $sql3);
mysqli_stmt_bind_param($stmt3, 'i', $patient_id);
mysqli_stmt_execute($stmt3);
$res3 = mysqli_stmt_get_result($stmt3);
if ($res3) {
    while ($r = mysqli_fetch_assoc($res3)) {
        $deleted_appts[] = $r;
    }
}
mysqli_stmt_close($stmt3);

// ---------------------------------------------------------------------
// 6. FETCH CONTACT MESSAGES FOR THE PATIENT
// ---------------------------------------------------------------------

$contact_messages = [];
$sql4 = "SELECT id, name, email, message, reply, created_at, replied_at, parent_id
         FROM contact_messages
         WHERE email = ? AND (parent_id IS NULL OR parent_id = 0)
         ORDER BY created_at DESC";
$stmt4 = mysqli_prepare($conn, $sql4);
mysqli_stmt_bind_param($stmt4, 's', $user_email);
mysqli_stmt_execute($stmt4);
$res4 = mysqli_stmt_get_result($stmt4);
if ($res4) {
    while ($r = mysqli_fetch_assoc($res4)) {
        // Fetch replies for this message
        $replies = [];
        $sql_replies = "SELECT id, name, email, message, created_at
                        FROM contact_messages
                        WHERE parent_id = ? AND email = ?
                        ORDER BY created_at ASC";
        $stmt_replies = mysqli_prepare($conn, $sql_replies);
        mysqli_stmt_bind_param($stmt_replies, 'is', $r['id'], $user_email);
        mysqli_stmt_execute($stmt_replies);
        $res_replies = mysqli_stmt_get_result($stmt_replies);
        if ($res_replies) {
            while ($reply = mysqli_fetch_assoc($res_replies)) {
                $replies[] = $reply;
            }
        }
        mysqli_stmt_close($stmt_replies);
        $r['replies'] = $replies;
        $contact_messages[] = $r;
    }
}
mysqli_stmt_close($stmt4);

// ---------------------------------------------------------------------
// 7. DELETE MESSAGE LOGIC
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_message' && isset($_GET['id'])) {
    $msg_id = (int)$_GET['id'];

    // Verify ownership
    $sql_check = "SELECT id FROM contact_messages WHERE id = ? AND email = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'is', $msg_id, $user_email);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        mysqli_stmt_close($stmt_check);

        // Delete the message
        $sql_delete = "DELETE FROM contact_messages WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, 'i', $msg_id);

        if (mysqli_stmt_execute($stmt_delete)) {
            header('Location: patient.php?tab=messages&status=message_deleted');
            exit;
        } else {
            header('Location: patient.php?tab=messages&error=message_delete');
            exit;
        }
        mysqli_stmt_close($stmt_delete);

    } else {
        mysqli_stmt_close($stmt_check);
        header('Location: patient.php?tab=messages&error=message_delete');
        exit;
    }
}

// ---------------------------------------------------------------------
// 8. SEND REPLY LOGIC
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reply') {
    $parent_id = intval($_POST['parent_id']);
    $message = trim($_POST['message']);
    if (!empty($message) && $parent_id > 0) {
        // Verify the parent message belongs to the patient
        $sql_check = "SELECT id FROM contact_messages WHERE id = ? AND email = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, 'is', $parent_id, $user_email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            // Insert new message
            $sql_insert = "INSERT INTO contact_messages (name, email, message, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, 'sssi', $patient_name, $user_email, $message, $parent_id);
            if (mysqli_stmt_execute($stmt_insert)) {
                header('Location: patient.php?tab=messages&status=reply_sent');
                exit;
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            mysqli_stmt_close($stmt_check);
        }
    }
    header('Location: patient.php?tab=messages&error=reply_failed');
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="brand">PATIENT PORTAL</div>
        <nav class="nav">
            <span class="welcome-text">Welcome, <?=htmlspecialchars($patient_name)?></span>
            <a href="patient.php" class="tab-link <?= $current_tab === 'appointments' ? 'active' : '' ?>">Appointments</a>
            <a href="patient.php?tab=history" class="tab-link <?= $current_tab === 'history' ? 'active' : '' ?>">History</a>
            <a href="patient.php?tab=messages" class="tab-link <?= $current_tab === 'messages' ? 'active' : '' ?>"><strong>My Messages</strong></a>
            <a href="../index.php#contact" class="tab-link"><strong>Send New Message</strong></a>
        <a href="../index.php">Homepage</a>
        <button id="theme-toggle" class="btn btn-secondary theme-toggle-btn">🌙</button>
        <a href="../logout.php" class="btn">Logout</a>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="sidebar">
            <a href="patient.php" class="active">View Appointments</a>
            <a href="../appointment.php">New Appointment</a>
        </div>

        <div class="main-content">
            <h1>Patient Dashboard</h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?= $error_message ?></div>
            <?php endif; ?>

            <!-- Appointments Tab -->
            <?php if ($current_tab === 'appointments'): ?>
                <h2>My Appointments</h2>

            <?php if (empty($appts)): ?>
                <p>You have no scheduled appointments. <a href="../appointment.php">Book one now.</a></p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID</th>
                            <th>Doctor & Specialization</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Symptoms</th>
                            <th>Service & Price</th>
                            <th>Status</th>
                            <th>Payment Status</th>
                            <th>Prescribed Medicines</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $counter = 1; foreach($appts as $a): ?>
                        <tr>
                            <td><?=$counter++?></td>
                            <td><?=htmlspecialchars($a['App_ID'])?></td>
                            <td>Dr. <?=htmlspecialchars($a['doc_fname'].' '.$a['doc_lname'])?> (<?=htmlspecialchars($a['specialization'])?>)</td>
                            <td><?=htmlspecialchars($a['App_Date'])?></td>
                            <td><?=htmlspecialchars($a['App_Time'])?></td>
                            <td><?=htmlspecialchars($a['symptom'])?></td>
                            <td><?=htmlspecialchars($a['Service_Name'])?><br>RM <?=number_format($a['Service_Price'], 2)?></td>
                            <td>
                                <?php $status = strtolower($a['status']);
                                    if ($status=='pending') echo '<span class="badge badge-pending">Pending</span>';
                                    if ($status=='approved') echo '<span class="badge badge-approved">Approved</span>';
                                    if ($status=='disapproved' || $status=='cancel' || $status=='rejected') echo '<span class="badge badge-disapproved">Disapproved</span>';
                                    if ($status=='done') echo '<span class="badge badge-done">Done</span>';
                                ?>
                            </td>
                            <td>
                                <span class="payment-status <?=htmlspecialchars($a['payment_status'])?>">
                                    <?=ucfirst($a['payment_status'])?>
                                </span>
                            </td>
                            <td>
                                <?php
                                // Fetch prescribed medicines
                                $sql_presc = "SELECT m.med_name, m.med_price, p.quantity 
                                              FROM prescription p 
                                              JOIN medicine m ON p.med_id = m.med_id 
                                              WHERE p.App_ID = ?";
                                $stmt_presc = mysqli_prepare($conn, $sql_presc);
                                mysqli_stmt_bind_param($stmt_presc, 'i', $a['App_ID']);
                                mysqli_stmt_execute($stmt_presc);
                                $res_presc = mysqli_stmt_get_result($stmt_presc);
                                
                                if (mysqli_num_rows($res_presc) > 0):
                                    echo '<div style="text-align:left;">';
                                    echo '<ul>';
                                    $total = 0;
                                    while ($presc = mysqli_fetch_assoc($res_presc)) {
                                        $subtotal = $presc['med_price'] * $presc['quantity'];
                                        $total += $subtotal;
                                        echo '<li>' . htmlspecialchars($presc['med_name']) . ' <br><small>Qty: ' . $presc['quantity'] . ' × RM' . number_format($presc['med_price'], 2) . ' = RM' . number_format($subtotal, 2) . '</small></li>';
                                    }
                                    echo '</ul>';
                                    echo '<div class="border-top pt-1 mt-2"><strong class="text-primary">Total: RM' . number_format($total, 2) . '</strong></div>';
                                    echo '</div>';
                                else:
                                    echo '<span class="text-muted" style="font-style:italic;">No prescription</span>';
                                endif;
                                mysqli_stmt_close($stmt_presc);
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick="printAppointment(<?=$a['App_ID']?>)">Print</button>

                                <?php if ($a['payment_status'] === 'unpaid' && $a['status'] === 'approved'): ?>
                                    <button onclick="openPaymentModal(<?=$a['App_ID']?>, <?=$a['Service_Price']?>)" class="btn btn-sm btn-success">Pay</button>
                                <?php elseif ($a['payment_status'] === 'pending'): ?>
                                    <span class="payment-review">Payment Under Review</span>
                                <?php elseif ($a['payment_status'] === 'rejected'): ?>
                                    <button onclick="openPaymentModal(<?=$a['App_ID']?>, <?=$a['Service_Price']?>)" class="btn btn-sm btn-warning">Re-upload Proof</button>
                                <?php endif; ?>

                                <br><br>
                                <a href="patient.php?action=delete&id=<?=htmlspecialchars($a['App_ID'])?>"
                                    onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                    class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Payment Modal -->
            <div id="paymentModal" class="payment-modal-container">
                <div class="payment-modal-content">
                    <h2>Payment Instructions</h2>
                    
                    <!-- QR Code Section - Only shows after clicking Pay -->
                    <div class="qr-code" id="qrCodeSection">
                        <h3>Scan QR Code to Pay</h3>
                        <div id="qrCodeContainer">
                            <!-- QR code will be inserted here by JavaScript -->
                        </div>
                        <p><strong>Amount: RM <span id="paymentAmount">0.00</span></strong></p>
                        <p><small>After payment, take a screenshot and upload the proof below</small></p>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="paymentForm">
                        <input type="hidden" name="action" value="upload_payment">
                        <input type="hidden" name="app_id" id="modalAppId">
                        
                        <div class="form-group">
                            <label for="payment_proof">Upload Payment Proof (Screenshot/Receipt):</label>
                            <input type="file" name="payment_proof" id="payment_proof" accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                            <small>Accepted formats: JPG, PNG, GIF, PDF (Max: 5MB)</small>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-success">Submit Payment Proof</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- History Tab -->
            <?php elseif ($current_tab === 'history'): ?>
                <h2>Deleted Appointment History</h2>
                <?php if (empty($deleted_appts)): ?>
                    <p>No deleted appointments found.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Doctor & Specialization</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Symptoms</th>
                                <th>Service & Price</th>
                                <th>Status</th>
                                <th>Payment Status</th>
                                <th>Deleted At</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $counter2 = 1; foreach($deleted_appts as $a): ?>
                            <tr>
                                <td><?=$counter2++?></td>
                                <td><?=htmlspecialchars($a['App_ID'])?></td>
                                <td>Dr. <?=htmlspecialchars($a['doc_fname'].' '.$a['doc_lname'])?> (<?=htmlspecialchars($a['specialization'])?>)</td>
                                <td><?=htmlspecialchars($a['App_Date'])?></td>
                                <td><?=htmlspecialchars($a['App_Time'])?></td>
                                <td><?=htmlspecialchars($a['symptom'])?></td>
                                <td><?=htmlspecialchars($a['Service_Name'])?><br>RM <?=number_format($a['Service_Price'], 2)?></td>
                                <td>
                                    <?php $status = strtolower($a['status']);
                                        if ($status=='pending') echo '<span class="badge badge-pending">Pending</span>';
                                        if ($status=='approved') echo '<span class="badge badge-approved">Approved</span>';
                                        if ($status=='disapproved' || $status=='cancel' || $status=='rejected') echo '<span class="badge badge-disapproved">Disapproved</span>';
                                        if ($status=='done') echo '<span class="badge badge-done">Done</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="payment-status <?=htmlspecialchars($a['payment_status'])?>">
                                        <?=ucfirst($a['payment_status'])?>
                                    </span>
                                </td>
                                <td><?=htmlspecialchars($a['deleted_at'] ? date('d-M-Y h:i A', strtotime($a['deleted_at'])) : 'N/A')?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <!-- Messages Tab -->
            <?php elseif ($current_tab === 'messages'): ?>
                <h2>My Messages</h2>
                <?php if (empty($contact_messages)): ?>
                    <p>You have no messages. <a href="../index.php#contact">Send a message</a> to contact us.</p>
                <?php else: ?>
                    <div class="messages-list">
                        <?php $counter = 1; foreach($contact_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <strong>Message. <?=$counter++?></strong>
                                    <span class="message-date">Started: <?=htmlspecialchars(date('d-M-Y h:i A', strtotime($msg['created_at'])))?></span>
                                </div>
                                <div class="message-thread">
                                    <!-- Original Message -->
                                    <div class="message-content patient-message">
                                        <p><strong>You:</strong></p>
                                        <p><?=nl2br(htmlspecialchars($msg['message']))?></p>
                                        <small>Sent: <?=htmlspecialchars(date('d-M-Y h:i A', strtotime($msg['created_at'])))?></small>
                                    </div>

                                    <!-- Admin Reply -->
                                    <?php if (!empty($msg['reply'])): ?>
                                        <div class="message-content admin-message">
                                            <p><strong>Admin Reply:</strong></p>
                                            <p><?=nl2br(htmlspecialchars($msg['reply']))?></p>
                                            <small>Replied: <?=htmlspecialchars(date('d-M-Y h:i A', strtotime($msg['replied_at'])))?></small>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Patient Replies -->
                                    <?php if (!empty($msg['replies'])): ?>
                                        <?php foreach($msg['replies'] as $reply): ?>
                                            <div class="message-content patient-message">
                                                <p><strong>You:</strong></p>
                                                <p><?=nl2br(htmlspecialchars($reply['message']))?></p>
                                                <small>Sent: <?=htmlspecialchars(date('d-M-Y h:i A', strtotime($reply['created_at'])))?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- Reply Button -->
                                    <div class="message-actions">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openReplyModal(<?=htmlspecialchars($msg['id'])?>, '<?=htmlspecialchars($msg['name'])?>', '<?=htmlspecialchars($msg['email'])?>', '<?=htmlspecialchars(addslashes($msg['message']))?>')">Reply</button>
                                        <a href="?tab=messages&action=delete_message&id=<?=htmlspecialchars($msg['id'])?>"
                                            onclick="return confirm('Are you sure you want to delete this entire message thread?')"
                                            class="btn btn-sm btn-danger">Delete Thread</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Reply Modal -->
                <div id="replyModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeReplyModal()">&times;</span>
                        <h3>Reply to Message</h3>
                        <div id="messageDetails"></div>
                        <form id="replyForm" method="POST">
                            <input type="hidden" name="action" value="send_reply">
                            <input type="hidden" name="parent_id" id="parent_id">
                            <label for="message">Your Reply:</label><br>
                            <textarea name="message" id="message" rows="6" required></textarea><br>
                            <button type="submit" class="btn btn-primary">Send Reply</button>
                            <button type="button" onclick="closeReplyModal()" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>

                <script>
                function openReplyModal(msgId, name, email, message) {
                    document.getElementById('parent_id').value = msgId;
                    document.getElementById('messageDetails').innerHTML = `
                        <strong>Replying to your message:</strong><br>
                        <div>${message}</div>
                    `;
                    document.getElementById('replyModal').style.display = 'block';
                }

                function closeReplyModal() {
                    document.getElementById('replyModal').style.display = 'none';
                    document.getElementById('replyForm').reset();
                }

                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('replyModal');
                    if (event.target == modal) {
                        closeReplyModal();
                    }
                }
                </script>

            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        &copy; <?= date('Y') ?> Hospital System. Patient Portal.
    </footer>

    <script>
        function printAppointment(appId) {
            // Fetch appointment data via AJAX or use a hidden form to get data
            // For simplicity, we'll create a temporary form to submit and get print data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'print_appointment.php'; // We'll create this file
            form.target = '_self'; // Open in same tab

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'app_id';
            input.value = appId;
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function openPaymentModal(appId, servicePrice) {
            document.getElementById('modalAppId').value = appId;
            document.getElementById('paymentAmount').textContent = servicePrice.toFixed(2);

            // Clear previous QR code
            document.getElementById('qrCodeContainer').innerHTML = '';

            // Create a wrapper div with white background for visibility in dark mode
            const qrWrapper = document.createElement('div');
            qrWrapper.style.backgroundColor = 'white';
            qrWrapper.style.padding = '10px';
            qrWrapper.style.borderRadius = '8px';
            qrWrapper.style.display = 'inline-block';
            qrWrapper.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';

            // Create QR code image
            const qrCodeImg = document.createElement('img');
            qrCodeImg.src = '../assets/image/qr.jpg';
            qrCodeImg.alt = 'Hospital QR Code';
            qrCodeImg.style.maxWidth = '300px';
            qrCodeImg.style.height = 'auto';
            qrCodeImg.style.border = '2px solid #ddd';
            qrCodeImg.style.borderRadius = '8px';

            // Add fallback if QR code doesn't exist
            qrCodeImg.onerror = function() {
                this.style.display = 'none';
                const placeholder = document.createElement('div');
                placeholder.innerHTML = `
                    <div style="width: 300px; height: 300px; background: #007BFF; color: white;
                                display: flex; align-items: center; justify-content: center;
                                margin: 0 auto; border-radius: 8px; text-align: center;">
                        <div>
                            <strong style="font-size: 18px;">HOSPITAL PAYMENT</strong><br>
                            <span style="font-size: 14px;">QR CODE</span><br><br>
                            <span style="font-size: 12px; opacity: 0.8;">
                                Amount: RM ${servicePrice.toFixed(2)}<br>
                                Appointment ID: ${appId}
                            </span>
                        </div>
                    </div>
                `;
                qrWrapper.innerHTML = '';
                qrWrapper.appendChild(placeholder);
            };

            qrWrapper.appendChild(qrCodeImg);
            document.getElementById('qrCodeContainer').appendChild(qrWrapper);
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('paymentForm').reset();
        }
        
        // Close modal when clicking outside
        document.querySelector('.payment-modal-container').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
        
        // File size validation
        document.getElementById('payment_proof').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('File size must be less than 5MB');
                e.target.value = '';
            }
        });
        
        // Prevent form submission if file is too large
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('payment_proof');
            const file = fileInput.files[0];
            
            if (file && file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must be less than 5MB');
                fileInput.value = '';
            }
        });
    </script>
    <script src="../theme.js"></script>
</body>
</html>
