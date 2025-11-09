<?php
session_start();
require 'config/db.php'; 

// --- Authentication and Authorization Check ---
if (empty($_SESSION['user'])) {
    header('Location: login.php'); exit;
}
if ($_SESSION['user']['user_type'] !== 'patient') {
    die('Only patients can create appointments.');
}

$uid = $_SESSION['user']['user_id'];

// --- Get Patient ID ---
$sql = "SELECT patient_id FROM patient WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $patient_id);
if (!mysqli_stmt_fetch($stmt)) {
    die('Patient profile not found. Please contact admin.');
}
mysqli_stmt_close($stmt);

// --- Load Doctors ---
$doctors = [];
$qr = "SELECT d.doctor_id, u.f_name, u.l_name, d.specialization FROM doctor d JOIN `user` u ON d.user_id = u.user_id";
$res = mysqli_query($conn, $qr);
while ($r = mysqli_fetch_assoc($res)) {
    $doctors[] = $r;
}

$errors = [];
$success = '';
$appt = null;

// --- Handle Appointment Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $symptom = mysqli_real_escape_string($conn, trim($_POST['symptom'] ?? ''));
    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');
    
    // FIX START: Set a default Service_ID as the form does not allow selection
    // Assumes Service_ID 1 ('General Consultation') exists in the services table.
    $service_id = 1; 
    // FIX END
    
    // Basic validation
    if ($doctor_id === 0) $errors[] = 'Please select a doctor.';
    if (empty($date)) $errors[] = 'Date is required.';
    if (empty($time)) $errors[] = 'Time is required.';

    if (empty($errors)) {
        // FIX: Added Service_ID to the column list
        $sql_insert = "INSERT INTO appointment (patient_id, doctor_id, Service_ID, App_Date, App_Time, symptom, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        
        // FIX: Added 'i' for Service_ID and $service_id to the parameters
        mysqli_stmt_bind_param($stmt_insert, 'iiisssss', $patient_id, $doctor_id, $service_id, $date, $time, $symptom, $status, $created_at);
        
        $inserted = mysqli_stmt_execute($stmt_insert);
        $new_appt_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_insert);

        if ($inserted) {
            $success = 'Appointment request submitted successfully. It is currently pending review.';
            
            $q = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization,
                         up.f_name AS pat_fname, up.l_name AS pat_lname
                  FROM appointment a
                  JOIN doctor d ON a.doctor_id=d.doctor_id
                  JOIN `user` ud ON d.user_id=ud.user_id
                  JOIN patient p ON a.patient_id=p.patient_id
                  JOIN `user` up ON p.user_id=up.user_id
                  WHERE a.App_ID = ?";
            $stmt_fetch = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt_fetch, 'i', $new_appt_id);
            mysqli_stmt_execute($stmt_fetch);
            $res_q = mysqli_stmt_get_result($stmt_fetch);
            $appt = mysqli_fetch_assoc($res_q);
            mysqli_stmt_close($stmt_fetch);

        } else {
            $errors[] = 'Failed to submit appointment request: ' . mysqli_error($conn);
        }
    }
}

// --- Fetch Latest Appointment (If no POST occurred) ---
if (!$appt) {
    $q = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, ud.f_name AS doc_fname, ud.l_name AS doc_lname, d.specialization,
                 up.f_name AS pat_fname, up.l_name AS pat_lname
          FROM appointment a
          JOIN doctor d ON a.doctor_id=d.doctor_id
          JOIN `user` ud ON d.user_id=ud.user_id
          JOIN patient p ON a.patient_id=p.patient_id
          JOIN `user` up ON p.user_id=up.user_id
          WHERE a.patient_id = ?
          ORDER BY a.created_at DESC LIMIT 1";
    $stmt_fetch_latest = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt_fetch_latest, 'i', $patient_id);
    mysqli_stmt_execute($stmt_fetch_latest);
    $res_q = mysqli_stmt_get_result($stmt_fetch_latest);
    $appt = mysqli_fetch_assoc($res_q);
    mysqli_stmt_close($stmt_fetch_latest);
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="header">
    <div class="brand">Hospital System</div>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="dashboard/patient.php">Dashboard</a>
      <a href="logout.php" class="btn">Logout</a>
    </nav>
  </header>

  <div class="container">
    <div class="card">
      <h2>Request New Appointment</h2>
      
      <?php 
      if ($success) echo "<div class='alert alert-success'>".htmlspecialchars($success)."</div>";
      if ($errors) foreach($errors as $e) echo "<div class='text-error'>".htmlspecialchars($e)."</div>"; 
      ?>

      <form method="post">
        <div class="form-group">
          <label>Select Doctor</label>
          <select name="doctor_id" required>
            <option value="">-- Select a Doctor --</option>
            <?php foreach($doctors as $d): ?>
              <option value="<?=htmlspecialchars($d['doctor_id'])?>">
                Dr. <?=htmlspecialchars($d['f_name'].' '.$d['l_name'])?> (<?=htmlspecialchars($d['specialization'])?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Preferred Date</label>
                <input type="date" name="appointment_date" required>
            </div>
            <div class="form-group">
                <label>Preferred Time</label>
                <input type="time" name="appointment_time" required>
            </div>
        </div>

        <div class="form-group">
          <label>Symptoms/Reason for Visit</label>
          <textarea name="symptom" rows="4" placeholder="Briefly describe your symptoms or reason for the appointment"></textarea>
        </div>

        <button class="btn" type="submit">Request Appointment</button>
      </form>

      <?php if ($appt): ?>
        <hr class="hr-margin">
        <h3>Latest Appointment Summary</h3>
        <table class="table">
          <tr><th>ID</th><td><?=htmlspecialchars($appt['App_ID'])?></td></tr>
          <tr><th>Doctor</th><td>Dr. <?=htmlspecialchars($appt['doc_fname'].' '.$appt['doc_lname'])?></td></tr>
          <tr><th>Date</th><td><?=htmlspecialchars($appt['App_Date'])?></td></tr>
          <tr><th>Time</th><td><?=htmlspecialchars($appt['App_Time'])?></td></tr>
          <tr><th>Symptoms</th><td><?=htmlspecialchars($appt['symptom'])?></td></tr>
          <tr><th>Status</th>
            <td>
              <?php
                    $status_lower = strtolower($appt['status']);
                    if ($status_lower === 'pending') echo '<span class="badge badge-pending">Pending</span>';
                    elseif ($status_lower === 'approved') echo '<span class="badge badge-approved">Approved</span>';
                    elseif ($status_lower === 'disapproved') echo '<span class="badge badge-disapproved">Disapproved</span>';
                    else echo '<span class="badge badge-cancel">N/A</span>';
              ?>
            </td>
          </tr>
        </table>
        <p class="mt-sm"><button class="btn" onclick="printAppointment()">Print Confirmation</button></p>
      <?php endif; ?>

      <!-- Hidden Print Container -->
      <?php if ($appt): ?>
      <div id="print-container" style="display: none;">
        <div class="card">
          <h2 class="text-center">Appointment Confirmation Slip</h2>
          <h3>Appointment ID: <?=htmlspecialchars($appt['App_ID'])?></h3>

          <table class="table">
            <tr><th>Appointment ID</th><td><?=htmlspecialchars($appt['App_ID'])?></td></tr>
            <tr><th>Patient</th><td><?=htmlspecialchars($appt['pat_fname'].' '.$appt['pat_lname'])?></td></tr>
            <tr><th>Doctor</th><td>Dr. <?=htmlspecialchars($appt['doc_fname'].' '.$appt['doc_lname'])?></td></tr>
            <tr><th>Date</th><td><?=htmlspecialchars($appt['App_Date'])?></td></tr>
            <tr><th>Time</th><td><?=htmlspecialchars($appt['App_Time'])?></td></tr>
            <tr><th>Symptoms</th><td><?=htmlspecialchars($appt['symptom'])?></td></tr>
            <tr><th>Status</th>
              <td>
                <?php
                  $status = strtolower($appt['status']);
                  if ($status=='pending') echo '<span class="badge badge-pending">Pending</span>';
                  elseif ($status=='approved') echo '<span class="badge badge-approved">Approved</span>';
                  elseif ($status=='disapproved') echo '<span class="badge badge-disapproved">Disapproved</span>';
                  else echo '<span class="badge badge-cancel">N/A</span>';
                ?>
              </td>
            </tr>
          </table>

          <div class="mt-4">
            <p>Please arrive 15 minutes prior to your scheduled time.</p>
            <p>This document serves as your official appointment confirmation.</p>
          </div>

          <div class="text-center text-muted border-top pt-2" style="margin-top: 50px; font-size: 0.9em;">
            123 Health St, Wellness City | Phone: +60 12-345 6789
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <footer class="footer">
    &copy; <?= date('Y') ?> Hospital System. All rights reserved. | <a href="index.php">Home</a>
  </footer>

  <script>
    function printAppointment() {
      var printContent = document.getElementById('print-container').innerHTML;
      var originalContent = document.body.innerHTML;
      document.body.innerHTML = printContent;
      window.print();
      document.body.innerHTML = originalContent;
    }
  </script>
</body>
</html>
