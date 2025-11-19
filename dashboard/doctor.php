<?php
session_start();
require '../config/db.php';

// --- Authentication and Authorization Check ---
if (empty($_SESSION['user']) || $_SESSION['user']['user_type'] !== 'doctor') {
    header('Location: ../login.php'); exit;
}

$uid = $_SESSION['user']['user_id'];
$doctor_name = $_SESSION['user']['f_name'];

// ---------------------------------------------------------------------
// 1. GET DOCTOR INFO 
// ---------------------------------------------------------------------

$sql = "SELECT doctor_id, specialization, room_no FROM doctor WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $doctor_id, $specialization, $room_no);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Fetch available medicines for prescription
$available_medicines = [];
$sql_meds = "SELECT med_id, med_name, med_price FROM medicine WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY med_name";
$res_meds = mysqli_query($conn, $sql_meds);
if ($res_meds) {
    while ($m = mysqli_fetch_assoc($res_meds)) {
        $available_medicines[] = $m;
    }
}

// Fetch available services for selection
$available_services = [];
$sql_services = "SELECT Service_ID, Service_Name, Service_Price FROM services ORDER BY Service_Name";
$res_services = mysqli_query($conn, $sql_services);
if ($res_services) {
    while ($s = mysqli_fetch_assoc($res_services)) {
        $available_services[] = $s;
    }
}

// Status/Error Messages
$success_message = '';
$error_message = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'approved') {
        $success_message = 'Appointment successfully approved.';
    } elseif ($_GET['status'] === 'disapproved') {
        $success_message = 'Appointment successfully disapproved.';
    } elseif ($_GET['status'] === 'deleted') {
        $success_message = 'Appointment successfully deleted and moved to history.';
    } elseif ($_GET['status'] === 'prescription_added') {
        $success_message = 'Prescription successfully added and appointment approved.';
    }
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'update') {
        $error_message = 'Error: Could not update the appointment status. Please check if the appointment exists and belongs to you.';
    } elseif ($_GET['error'] === 'delete') {
        $error_message = 'Error: Could not delete the appointment. Please check the ID.';
    } elseif ($_GET['error'] === 'prescription') {
        $error_message = 'Error: Could not add prescription. Please try again.';
    } elseif ($_GET['error'] === 'services_required') {
        $error_message = 'Error: At least one service must be selected to approve the appointment.';
    }
}

// ---------------------------------------------------------------------
// 2. PRESCRIPTION LOGIC (Handles POST from prescription form)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_prescription') {
    $app_id = intval($_POST['app_id']);
    $selected_services = $_POST['services'] ?? [];
    $selected_medicines = $_POST['medicines'] ?? [];

    // Validate that at least one service is selected
    if (empty($selected_services)) {
        header('Location: doctor.php?view=' . $_POST['current_view'] . '&error=services_required');
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        // First, approve the appointment
        $sql_approve = "UPDATE appointment SET status = 'approved' WHERE App_ID = ? AND doctor_id = ?";
        $stmt_approve = mysqli_prepare($conn, $sql_approve);
        mysqli_stmt_bind_param($stmt_approve, 'ii', $app_id, $doctor_id);
        mysqli_stmt_execute($stmt_approve);
        mysqli_stmt_close($stmt_approve);

        // Then add selected services to appointment_services table
        if (!empty($selected_services) && is_array($selected_services)) {
            foreach ($selected_services as $service_id) {
                $service_id = intval($service_id);
                if ($service_id > 0) {
                    $sql_service = "INSERT INTO appointment_services (App_ID, Service_ID, created_at)
                                    VALUES (?, ?, NOW())";
                    $stmt_service = mysqli_prepare($conn, $sql_service);
                    mysqli_stmt_bind_param($stmt_service, 'ii', $app_id, $service_id);
                    mysqli_stmt_execute($stmt_service);
                    mysqli_stmt_close($stmt_service);
                }
            }
        }

        // Then add prescriptions if any medicines selected
        if (!empty($selected_medicines) && is_array($selected_medicines)) {
            foreach ($selected_medicines as $med_info) {
                list($med_id, $quantity) = explode(':', $med_info);
                $med_id = intval($med_id);
                $quantity = intval($quantity);

                if ($med_id > 0 && $quantity > 0) {
                    $sql_presc = "INSERT INTO prescription (App_ID, med_id, quantity, created_at)
                                  VALUES (?, ?, ?, NOW())";
                    $stmt_presc = mysqli_prepare($conn, $sql_presc);
                    mysqli_stmt_bind_param($stmt_presc, 'iii', $app_id, $med_id, $quantity);
                    mysqli_stmt_execute($stmt_presc);
                    mysqli_stmt_close($stmt_presc);
                }
            }
        }

        mysqli_commit($conn);
        header('Location: doctor.php?view=' . $_POST['current_view'] . '&status=prescription_added');
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Prescription error: " . $e->getMessage());
        header('Location: doctor.php?view=' . $_POST['current_view'] . '&error=prescription');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3. APPOINTMENT STATUS UPDATE LOGIC (Handles POST from forms)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aid'], $_POST['action'])) {
    $aid = intval($_POST['aid']);
    
    if ($_POST['action'] === 'disapprove') {
        $db_status_value = 'disapproved';
        
        $u = "UPDATE appointment SET status = ? WHERE App_ID = ? AND doctor_id = ?";
        $st = mysqli_prepare($conn, $u);

        if (!$st) {
             header('Location: doctor.php?view=appointments&error=update'); exit;
        }

        mysqli_stmt_bind_param($st, 'sii', $db_status_value, $aid, $doctor_id);
        
        if (mysqli_stmt_execute($st)) {
            $affected_rows = mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st); 
            
            $valid_views = ['appointments', 'pending', 'approve', 'disapproved', 'symptoms', 'history'];
            $redirect_view = 'disapproved'; // Redirect to disapproved appointments view
            if (isset($_POST['current_view']) && in_array($_POST['current_view'], $valid_views)) {
                $redirect_view = $_POST['current_view'];
            } elseif (isset($_GET['view']) && in_array($_GET['view'], $valid_views)) {
                $redirect_view = $_GET['view'];
            }
            
            if ($affected_rows > 0) {
                header('Location: doctor.php?view=' . $redirect_view . '&status=disapproved');
                exit;
            } else {
                header('Location: doctor.php?view=' . $redirect_view . '&error=update');
                exit;
            }
        } else {
            mysqli_stmt_close($st);
            header('Location: doctor.php?view=appointments&error=update');
            exit;
        }
    }
}

// ---------------------------------------------------------------------
// 4. APPOINTMENT SOFT DELETION LOGIC (Handles ?action=delete&id=...)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    
    $valid_views = ['appointments', 'pending', 'approve', 'disapproved', 'symptoms', 'history'];
    $redirect_view = 'appointments';
    if (isset($_GET['view']) && in_array($_GET['view'], $valid_views)) {
        $redirect_view = $_GET['view'];
    }

    // Soft delete - mark as deleted
    $sql_delete = "UPDATE appointment 
                   SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                   WHERE App_ID = ? AND doctor_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);

    if (!$stmt_delete) {
        header('Location: doctor.php?view=' . $redirect_view . '&error=delete'); exit;
    }
    
    mysqli_stmt_bind_param($stmt_delete, 'iii', $uid, $app_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt_delete) && mysqli_stmt_affected_rows($stmt_delete) > 0) {
        mysqli_stmt_close($stmt_delete);
        header('Location: doctor.php?view=' . $redirect_view . '&status=deleted');
        exit;
    } else {
        mysqli_stmt_close($stmt_delete);
        header('Location: doctor.php?view=' . $redirect_view . '&error=delete');
        exit;
    }
}

// ---------------------------------------------------------------------
// 5. FETCH APPOINTMENTS 
// ---------------------------------------------------------------------

$valid_views = ['appointments', 'pending', 'approve', 'disapproved', 'symptoms', 'history'];
$current_view = 'appointments';
if (isset($_GET['view']) && in_array($_GET['view'], $valid_views)) {
    $current_view = $_GET['view'];
}

$appts = [];

// For history view, fetch deleted appointments
if ($current_view === 'history') {
    $sql_history = "SELECT a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.deleted_at,
                           up.f_name AS pat_fname, up.l_name AS pat_lname, 
                           up.phone AS pat_phone, up.email AS pat_email
                    FROM appointment a 
                    JOIN patient p ON a.patient_id = p.patient_id 
                    JOIN `user` up ON p.user_id = up.user_id
                    WHERE a.doctor_id = ? AND a.is_deleted = 1
                    ORDER BY a.deleted_at DESC";
    
    $stmt_history = mysqli_prepare($conn, $sql_history);
    mysqli_stmt_bind_param($stmt_history, 'i', $doctor_id);
    mysqli_stmt_execute($stmt_history);
    $res_history = mysqli_stmt_get_result($stmt_history);
    
    if ($res_history) {
        while ($r = mysqli_fetch_assoc($res_history)) {
            $appts[] = $r;
        }
    }
    mysqli_stmt_close($stmt_history);
    
} else {
    // For all other views, fetch active appointments
    $select_cols = "a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, 
                    up.f_name AS pat_fname, up.l_name AS pat_lname, up.phone AS pat_phone, up.email AS pat_email";
    $from_tables = "FROM appointment a 
                    JOIN patient p ON a.patient_id = p.patient_id 
                    JOIN `user` up ON p.user_id = up.user_id";
    $where_condition = "WHERE a.doctor_id = ? AND (a.is_deleted = 0 OR a.is_deleted IS NULL)";
    $order_by = "ORDER BY a.App_Date ASC, a.App_Time ASC";

    if ($current_view === 'pending') {
        $where_condition .= " AND LOWER(a.status) = 'pending'";
    } elseif ($current_view === 'approve') {
        $where_condition .= " AND LOWER(a.status) = 'approved'";
    } elseif ($current_view === 'disapproved') {
        $where_condition .= " AND LOWER(a.status) = 'disapproved'";
    }

    $sql_appts = "SELECT {$select_cols} {$from_tables} {$where_condition} {$order_by}";

    $stmt_appts = mysqli_prepare($conn, $sql_appts);

    if (!$stmt_appts) {
        die("Database Error during fetch prepare: " . mysqli_error($conn)); 
    }

    mysqli_stmt_bind_param($stmt_appts, 'i', $doctor_id);
    mysqli_stmt_execute($stmt_appts);
    $res_appts = mysqli_stmt_get_result($stmt_appts);

    if ($res_appts) {
        while ($r = mysqli_fetch_assoc($res_appts)) {
            $appts[] = $r;
        }
    }
    mysqli_stmt_close($stmt_appts);

    $app_ids = array_column($appts, 'App_ID');
$prescriptions_by_appt = [];
if (!empty($app_ids)) {
    $ids_str = implode(",", array_map('intval', $app_ids));
    $sql_presc = "SELECT pr.App_ID, m.med_name, pr.quantity
                  FROM prescription pr
                  JOIN medicine m ON pr.med_id = m.med_id
                  WHERE pr.App_ID IN ($ids_str)";
    $res_presc = mysqli_query($conn, $sql_presc);
    if ($res_presc) {
        while ($row = mysqli_fetch_assoc($res_presc)) {
            $prescriptions_by_appt[$row['App_ID']][] = [
                'med_name' => $row['med_name'],
                'quantity' => $row['quantity'],
            ];
        }
    }
}
}

// Function to render status badge with inline styles
function renderStatusBadge($status) {
    $status_lower = strtolower(trim($status));
    
    if ($status_lower === 'pending') {
        return '<span style="display:inline-block;padding:6px 14px;border-radius:12px;font-size:0.85em;font-weight:500;background-color:#ffc107;color:#000;text-transform:capitalize;">Pending</span>';
    } elseif ($status_lower === 'approved') {
        return '<span style="display:inline-block;padding:6px 14px;border-radius:12px;font-size:0.85em;font-weight:500;background-color:#28a745;color:#fff;text-transform:capitalize;">Approved</span>';
    } elseif ($status_lower === 'disapproved') {
        return '<span style="display:inline-block;padding:6px 14px;border-radius:12px;font-size:0.85em;font-weight:500;background-color:#dc3545;color:#fff;text-transform:capitalize;">Disapproved</span>';
    } elseif ($status_lower === 'done') {
        return '<span style="display:inline-block;padding:6px 14px;border-radius:12px;font-size:0.85em;font-weight:500;background-color:#17a2b8;color:#fff;text-transform:capitalize;">Done</span>';
    } else {
        return '<span style="display:inline-block;padding:6px 14px;border-radius:12px;font-size:0.85em;font-weight:500;background-color:#6c757d;color:#fff;text-transform:capitalize;">'.htmlspecialchars(ucfirst($status)).'</span>';
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="brand">DOCTOR PORTAL</div>
        <nav class="nav">
            <span class="welcome-text">Dr. <?=htmlspecialchars($doctor_name)?>, <?=htmlspecialchars($specialization)?> (Room: <?=htmlspecialchars($room_no)?>)</span>
            <button id="theme-toggle" class="btn btn-secondary theme-toggle-btn">🌙</button>
            <a href="../logout.php" class="btn">Logout</a>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="sidebar">
            <a href="?view=appointments" class="<?= $current_view === 'appointments' ? 'active' : '' ?>">View Appointments</a>
            <a href="?view=pending" class="<?= $current_view === 'pending' ? 'active' : '' ?>">Pending Appointments</a>
            <a href="?view=approve" class="<?= $current_view === 'approve' ? 'active' : '' ?>">Approved Appointments</a>
            <a href="?view=disapproved" class="<?= $current_view === 'disapproved' ? 'active' : '' ?>">Disapproved Appointments</a>
            <a href="?view=symptoms" class="<?= $current_view === 'symptoms' ? 'active' : '' ?>">View Symptoms</a>
            <a href="?view=history" class="<?= $current_view === 'history' ? 'active' : '' ?>">Appointment History</a>
        </div>

        <div class="main-content">
            <?php 
            $title = 'Appointments Scheduled With You';
            if ($current_view === 'pending') $title = 'Pending Appointments';
            elseif ($current_view === 'approve') $title = 'Approved Appointments';
            elseif ($current_view === 'disapproved') $title = 'Disapproved Appointments';
            elseif ($current_view === 'symptoms') $title = "Patient's Symptoms";
            elseif ($current_view === 'history') $title = "Deleted Appointment History";
            ?>
            <h1><?= $title ?></h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?= $error_message ?></div>
            <?php endif; ?>

            <?php if ($current_view === 'history'): ?>
                <?php if (empty($appts)): ?>
                    <p>No deleted appointment history found.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Patient Name</th>
                                <th>Date & Time</th>
                                <th>Symptoms</th>
                                <th>Status Before Deletion</th>
                                <th>Contact</th>
                                <th>Deleted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter_history = 1; foreach($appts as $a): ?>
                            <tr>
                                <td><?=$counter_history++?></td>
                                <td><?=htmlspecialchars($a['App_ID'])?></td>
                                <td><?=htmlspecialchars($a['pat_fname'].' '.$a['pat_lname'])?></td>
                                <td><?=htmlspecialchars($a['App_Date'])?> at <?=htmlspecialchars($a['App_Time'])?></td>
                                <td><?=htmlspecialchars($a['symptom'])?></td>
                                <td><?= renderStatusBadge($a['status']) ?></td>
                                <td>
                                    Email: <?=htmlspecialchars($a['pat_email'])?><br>
                                    Phone: <?=htmlspecialchars($a['pat_phone'])?>
                                </td>
                                <td><?=htmlspecialchars($a['deleted_at'] ? date('d-M-Y h:i A', strtotime($a['deleted_at'])) : 'N/A')?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php elseif ($current_view === 'symptoms'): ?>
                <?php if (empty($appts)): ?>
                    <p>No patient symptoms found.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Patient Name</th>
                                <th>Date & Time</th>
                                <th>Symptoms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter_symptoms = 1; foreach($appts as $a): ?>
                            <tr>
                                <td><?=$counter_symptoms++?></td>
                                <td><?=htmlspecialchars($a['App_ID'])?></td>
                                <td><?=htmlspecialchars($a['pat_fname'].' '.$a['pat_lname'])?></td>
                                <td><?=htmlspecialchars($a['App_Date'])?> at <?=htmlspecialchars($a['App_Time'])?></td>
                                <td><?=htmlspecialchars($a['symptom'])?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php else: ?>
                
                <?php if (empty($appts)): ?>
                    <p>
                        <?php 
                        if ($current_view === 'appointments') echo 'You currently have no appointments scheduled.';
                        elseif ($current_view === 'pending') echo 'No pending appointments waiting for approval.';
                        elseif ($current_view === 'approve') echo 'No approved appointments found.';
                        elseif ($current_view === 'disapproved') echo 'No disapproved appointments.';
                        ?>
                    </p>
                <?php else: ?>
                    <p>
                        <?php 
                        if ($current_view === 'appointments') echo 'Below is a list of all appointments, including those pending review.';
                        elseif ($current_view === 'pending') echo 'Below is a list of appointments pending your approval.';
                        elseif ($current_view === 'approve') echo 'Below is a list of approved appointments.';
                        elseif ($current_view === 'disapproved') echo 'Below is a list of disapproved appointments.';
                        ?>
                    </p>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Patient Name</th>
                                <th>Date & Time</th>
                                <th>Symptoms</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Prescribed Medicines</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php $counter_main = 1; foreach($appts as $a): ?>
        <tr>
            <td><?=$counter_main++?></td>
            <td><?=htmlspecialchars($a['App_ID'])?></td>
            <td><?=htmlspecialchars($a['pat_fname'].' '.$a['pat_lname'])?></td>
            <td><?=htmlspecialchars($a['App_Date'])?> at <?=htmlspecialchars($a['App_Time'])?></td>
            <td><?=htmlspecialchars($a['symptom'])?></td>
            <td>
                Email: <?=htmlspecialchars($a['pat_email'])?><br>
                Phone: <?=htmlspecialchars($a['pat_phone'])?>
            </td>
            <td><?= renderStatusBadge($a['status']) ?></td>
            <td>
            <?php 
                $app_id = $a['App_ID'];
                if (!empty($prescriptions_by_appt[$app_id])) {
                    foreach ($prescriptions_by_appt[$app_id] as $med) {
                        echo htmlspecialchars($med['med_name']) . " &times; " . intval($med['quantity']) . "<br>";
                    }
                } else {
                    echo "<em>None</em>";
                }
            ?>
            </td>
            <td>
                                    <?php 
                                    $status = strtolower(trim($a['status']));
                                    if ($status === 'pending'): ?>
                                        <button type="button" onclick="openPrescriptionModal(<?=$a['App_ID']?>, '<?=htmlspecialchars($a['pat_fname'].' '.$a['pat_lname'])?>', '<?=htmlspecialchars($a['App_Date'])?>')" class="btn btn-sm">Approve & Prescribe</button>
                                        
                                        <form method="post" class="d-inline ml-1" onsubmit="return confirm('Confirm Appointment Disapproval? This will reject the appointment.')">
                                          <input type="hidden" name="aid" value="<?=htmlspecialchars($a['App_ID'])?>">
                                          <input type="hidden" name="current_view" value="<?=htmlspecialchars($current_view)?>">
                                          <button name="action" value="disapprove" class="btn btn-sm btn-danger">Disapprove</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" onclick="printAppointment(<?=$a['App_ID']?>)">Print</button>
                                        
                                        <a href="doctor.php?view=<?=htmlspecialchars($current_view)?>&action=delete&id=<?=htmlspecialchars($a['App_ID'])?>" 
                                            onclick="return confirm('Are you sure you want to delete this appointment? It will be moved to history.')"
                                            class="btn btn-sm btn-danger ml-1">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prescription Modal -->
    <div id="prescriptionModal" class="prescription-modal">
        <div class="prescription-modal-content">
            <h2 style="color:#007BFF; margin-bottom:20px;">Add Prescription & Approve</h2>
            <form method="POST" id="prescriptionForm">
                <input type="hidden" name="action" value="add_prescription">
                <input type="hidden" name="app_id" id="modal_app_id">
                <input type="hidden" name="current_view" value="<?=htmlspecialchars($current_view)?>">
                
                <div style="margin-bottom:20px;">
                    <p><strong>Patient:</strong> <span id="modal_patient_name"></span></p>
                    <p><strong>Date:</strong> <span id="modal_app_date"></span></p>
                </div>
                
                <h3>Select Services (Required):</h3>
                <p class="optional-text">Please select at least one service for this appointment.</p>

                <div id="serviceList" class="medicine-list">
                    <?php if (empty($available_services)): ?>
                        <p style="color:#999; text-align:center;">No services available. Contact admin to add services.</p>
                    <?php else: ?>
                        <?php foreach($available_services as $service): ?>
                            <div class="medicine-item">
                                <label>
                                    <input type="checkbox" name="services[]" value="<?=$service['Service_ID']?>">
                                    <span class="medicine-details">
                                        <strong><?=htmlspecialchars($service['Service_Name'])?></strong><br>
                                        <small>RM <?=number_format($service['Service_Price'], 2)?></small>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3>Select Medicines (Optional):</h3>
                <p class="optional-text">You can approve without prescribing medicines by clicking "Approve" without selecting any.</p>

                <div id="medicineList" class="medicine-list">
                    <?php if (empty($available_medicines)): ?>
                        <p style="color:#999; text-align:center;">No medicines available. Contact admin to add medicines.</p>
                    <?php else: ?>
                        <?php foreach($available_medicines as $med): ?>
                            <div class="medicine-item">
                                <label>
                                    <input type="checkbox" name="medicines[]" value="<?=$med['med_id']?>:1">
                                    <span class="medicine-details">
                                        <strong><?=htmlspecialchars($med['med_name'])?></strong><br>
                                        <small>RM <?=number_format($med['med_price'], 2)?></small>
                                    </span>
                                    <input type="number" min="1" value="1" class="quantity-input" onchange="updateQuantity(this, <?=$med['med_id']?>)">
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Appointment</button>
                </div>
            </form>
        </div>
    </div>
    
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

    function openPrescriptionModal(appId, patientName, appDate) {
        document.getElementById('modal_app_id').value = appId;
        document.getElementById('modal_patient_name').textContent = patientName;
        document.getElementById('modal_app_date').textContent = appDate;
        document.getElementById('prescriptionModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('prescriptionModal').style.display = 'none';
        document.getElementById('prescriptionForm').reset();
    }

    function updateQuantity(input, medId) {
        const checkbox = input.parentElement.querySelector('input[type="checkbox"]');
        const quantity = input.value;
        checkbox.value = medId + ':' + quantity;
    }

    // Close modal when clicking outside
    document.getElementById('prescriptionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    </script>

    <footer class="footer">
        &copy; <?= date('Y') ?> Hospital System. Doctor Portal.
    </footer>
    <script src="../theme.js"></script>
</body>
</html>
