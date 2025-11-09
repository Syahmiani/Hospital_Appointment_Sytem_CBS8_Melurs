<?php
session_start();
require '../config/db.php';

if (empty($_SESSION['user'])) {
    header('Location: ../login.php'); exit;
}

$uid = $_SESSION['user']['user_id'];
$user_type = $_SESSION['user']['user_type'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['app_id'])) {
    die('Invalid request');
}

$app_id = intval($_POST['app_id']);

// Verify ownership based on user type
if ($user_type === 'patient') {
    $sql_check = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status,
                         ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization, d.room_no,
                         up.f_name AS pat_fname, up.l_name AS pat_lname, up.phone AS pat_phone, up.email AS pat_email,
                         s.Service_Name, s.Service_Price
                  FROM appointment a
                  JOIN doctor d ON a.doctor_id = d.doctor_id
                  JOIN user ud ON d.user_id = ud.user_id
                  JOIN patient p ON a.patient_id = p.patient_id
                  JOIN user up ON p.user_id = up.user_id
                  JOIN services s ON a.Service_ID = s.Service_ID
                  WHERE a.App_ID = ? AND p.user_id = ? AND (a.is_deleted IS NULL OR a.is_deleted = 0)";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'ii', $app_id, $uid);
} elseif ($user_type === 'doctor') {
    $sql_check = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status,
                         ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization, d.room_no,
                         up.f_name AS pat_fname, up.l_name AS pat_lname, up.phone AS pat_phone, up.email AS pat_email,
                         s.Service_Name, s.Service_Price
                  FROM appointment a
                  JOIN doctor d ON a.doctor_id = d.doctor_id
                  JOIN user ud ON d.user_id = ud.user_id
                  JOIN patient p ON a.patient_id = p.patient_id
                  JOIN user up ON p.user_id = up.user_id
                  JOIN services s ON a.Service_ID = s.Service_ID
                  WHERE a.App_ID = ? AND d.user_id = ? AND (a.is_deleted IS NULL OR a.is_deleted = 0)";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, 'ii', $app_id, $uid);
} else {
    die('Unauthorized access');
}

mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result) === 0) {
    die('Appointment not found or access denied');
}

$appointment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt_check);

// Fetch prescribed medicines
$prescriptions = [];
$sql_presc = "SELECT m.med_name, m.med_price, p.quantity
              FROM prescription p
              JOIN medicine m ON p.med_id = m.med_id
              WHERE p.App_ID = ?";
$stmt_presc = mysqli_prepare($conn, $sql_presc);
mysqli_stmt_bind_param($stmt_presc, 'i', $app_id);
mysqli_stmt_execute($stmt_presc);
$res_presc = mysqli_stmt_get_result($stmt_presc);
while ($row = mysqli_fetch_assoc($res_presc)) {
    $prescriptions[] = $row;
}
mysqli_stmt_close($stmt_presc);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light dark">
    <title>Appointment Details - ID: <?php echo htmlspecialchars($appointment['App_ID']); ?></title>
    <style>
        /* CSS Variables for Themes */
        :root {
            --bg-color: #f0f8ff;
            --text-color: #333;
            --header-bg: #007BFF;
            --header-text: #fff;
            --nav-bg: #5b9cde;
            --card-bg: #fff;
            --section-bg: rgba(255, 255, 255, 0.5);
            --border-color: #007BFF;
            --footer-bg: #333;
            --footer-text: #eee;
            --btn-primary: #007BFF;
            --btn-secondary: #07830b;
            --btn-danger: #dc3545;
            --alert-success-bg: #d4edda;
            --alert-success-text: #155724;
            --alert-success-border: #c3e6cb;
            --alert-error-bg: #f8d7da;
            --alert-error-text: #721c24;
            --alert-error-border: #f5c6cb;
            --alert-warning-bg: #fff3cd;
            --alert-warning-text: #856404;
            --alert-warning-border: #ffeaa7;
            --alert-info-bg: #d1ecf1;
            --alert-info-text: #0c5460;
            --alert-info-border: #bee5eb;
            --badge-pending-bg: #ffc107;
            --badge-pending-text: #000;
            --badge-approved-bg: #28a745;
            --badge-approved-text: #fff;
            --badge-disapproved-bg: #dc3545;
            --badge-disapproved-text: #fff;
            --badge-done-bg: #17a2b8;
            --badge-done-text: #fff;
            --badge-cancel-bg: #6c757d;
            --badge-cancel-text: #fff;
            --payment-unpaid-bg: #ffc107;
            --payment-unpaid-text: #000;
            --payment-pending-bg: #ffc107;
            --payment-pending-text: #000;
            --payment-paid-bg: #007bff;
            --payment-paid-text: #fff;
            --payment-rejected-bg: #dc3545;
            --payment-rejected-text: #fff;
            --payment-review-bg: #ffb74d;
            --payment-review-text: #000;
            --table-hover-bg: #f5f5f5;
            --table-border: #ddd;
            --contact-form-bg: #fff;
            --about-text-bg: #fff;
            --input-bg: #fff;
        }

        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #fff;
            --header-bg: #1e1e1e;
            --header-text: #fff;
            --nav-bg: #2c2c2c;
            --card-bg: #1e1e1e;
            --section-bg: rgba(30, 30, 30, 0.8);
            --border-color: #007BFF;
            --footer-bg: #1e1e1e;
            --footer-text: #ccc;
            --btn-primary: #007BFF;
            --btn-secondary: #28a745;
            --btn-danger: #dc3545;
            --alert-success-bg: #155724;
            --alert-success-text: #d4edda;
            --alert-success-border: #c3e6cb;
            --alert-error-bg: #721c24;
            --alert-error-text: #f8d7da;
            --alert-error-border: #f5c6cb;
            --alert-warning-bg: #856404;
            --alert-warning-text: #fff3cd;
            --alert-warning-border: #ffeaa7;
            --alert-info-bg: #0c5460;
            --alert-info-text: #d1ecf1;
            --alert-info-border: #bee5eb;
            --badge-pending-bg: #ffc107;
            --badge-pending-text: #000;
            --badge-approved-bg: #28a745;
            --badge-approved-text: #fff;
            --badge-disapproved-bg: #dc3545;
            --badge-disapproved-text: #fff;
            --badge-done-bg: #17a2b8;
            --badge-done-text: #fff;
            --badge-cancel-bg: #6c757d;
            --badge-cancel-text: #fff;
            --payment-unpaid-bg: #ffc107;
            --payment-unpaid-text: #000;
            --payment-pending-bg: #ffc107;
            --payment-pending-text: #000;
            --payment-paid-bg: #007bff;
            --payment-paid-text: #fff;
            --payment-rejected-bg: #dc3545;
            --payment-rejected-text: #fff;
            --payment-review-bg: #ffb74d;
            --payment-review-text: #000;
            --table-hover-bg: #2a2a2a;
            --table-border: #444;
            --contact-form-bg: #1e1e1e;
            --about-text-bg: #1e1e1e;
            --input-bg: #2c2c2c;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            background: var(--bg-color);
            color: var(--text-color);
            transition: background 0.3s ease, color 0.3s ease;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: var(--border-color);
        }
        .details {
            margin-bottom: 20px;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        .details th, .details td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
            color: var(--text-color);
        }
        .details th {
            background-color: var(--btn-primary);
            color: var(--header-text);
            font-weight: bold;
        }
        .medicines {
            margin-top: 20px;
        }
        .medicines h3 {
            color: var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 5px;
        }
        .medicines ul {
            list-style-type: none;
            padding: 0;
        }
        .medicines li {
            padding: 5px 0;
            border-bottom: 1px solid var(--table-border);
            color: var(--text-color);
        }
        .total {
            font-weight: bold;
            color: var(--border-color);
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: var(--footer-text);
        }
        @media print {
            body { margin: 0; background: white; color: black; }
            .no-print { display: none; }
        }
        .go-back-btn {
            background-color: var(--btn-primary);
            color: var(--header-text);
            border: none;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 10px 0;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        .go-back-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <button class="go-back-btn no-print" onclick="history.back()">Go Back</button>
    <div class="header">
        <h1>HOSPITAL APPOINTMENT DETAILS</h1>
        <p>Appointment ID: <?php echo htmlspecialchars($appointment['App_ID']); ?></p>
    </div>

    <div class="details">
        <table>
            <tr>
                <th>Patient Name:</th>
                <td><?php echo htmlspecialchars($appointment['pat_fname'] . ' ' . $appointment['pat_lname']); ?></td>
            </tr>
            <tr>
                <th>Doctor:</th>
                <td>Dr. <?php echo htmlspecialchars($appointment['doc_fname'] . ' ' . $appointment['doc_lname']); ?> (<?php echo htmlspecialchars($appointment['specialization']); ?>)</td>
            </tr>
            <tr>
                <th>Room:</th>
                <td><?php echo htmlspecialchars($appointment['room_no']); ?></td>
            </tr>
            <tr>
                <th>Appointment Date:</th>
                <td><?php echo htmlspecialchars(date('d-M-Y', strtotime($appointment['App_Date']))); ?></td>
            </tr>
            <tr>
                <th>Appointment Time:</th>
                <td><?php echo htmlspecialchars($appointment['App_Time']); ?></td>
            </tr>
            <tr>
                <th>Symptoms:</th>
                <td><?php echo htmlspecialchars($appointment['symptom']); ?></td>
            </tr>
            <tr>
                <th>Service:</th>
                <td><?php echo htmlspecialchars($appointment['Service_Name']); ?> - RM <?php echo number_format($appointment['Service_Price'], 2); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></td>
            </tr>
            <tr>
                <th>Payment Status:</th>
                <td><?php echo htmlspecialchars(ucfirst($appointment['payment_status'])); ?></td>
            </tr>
            <tr>
                <th>Contact:</th>
                <td>Email: <?php echo htmlspecialchars($appointment['pat_email']); ?><br>Phone: <?php echo htmlspecialchars($appointment['pat_phone']); ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($prescriptions)): ?>
    <div class="medicines">
        <h3>Prescribed Medicines</h3>
        <ul>
            <?php
            $total = 0;
            foreach ($prescriptions as $med) {
                $subtotal = $med['med_price'] * $med['quantity'];
                $total += $subtotal;
                echo '<li>' . htmlspecialchars($med['med_name']) . ' - Qty: ' . intval($med['quantity']) . ' × RM' . number_format($med['med_price'], 2) . ' = RM' . number_format($subtotal, 2) . '</li>';
            }
            ?>
        </ul>
        <div class="total">Total Medicine Cost: RM <?php echo number_format($total, 2); ?></div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Generated on: <?php echo date('d-M-Y H:i:s'); ?></p>
        <p>&copy; <?php echo date('Y'); ?> Hospital System</p>
    </div>

    <script>
        // Automatically open print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
