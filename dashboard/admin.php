<?php
session_start();

// ---------------------------------------------------------------------
// 0. DATABASE CONNECTION
// ---------------------------------------------------------------------
require '../config/db.php';
require '../classes/DatabaseHelper.php';

$dbHelper = new DatabaseHelper($conn);

// ---------------------------------------------------------------------
// 1. ACCESS CONTROL
// ---------------------------------------------------------------------

// Check if user is logged in and is an admin
if (empty($_SESSION['user']) || $_SESSION['user']['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$admin_name = $_SESSION['user']['f_name'];
$current_tab = $_GET['tab'] ?? 'dashboard';

// Get filter values from URL (only for active records, not history)
$filter_specialization = $_GET['filter_specialization'] ?? '';
$filter_patient_name = $_GET['filter_patient_name'] ?? '';
$filter_appointment_status = $_GET['filter_appointment_status'] ?? '';
$filter_message_status = $_GET['filter_message_status'] ?? '';
$filter_date_range = $_GET['filter_date_range'] ?? '';
$filter_user_type = $_GET['filter_user_type'] ?? '';

// Pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ---------------------------------------------------------------------
// 2. DOCTOR DELETION LOGIC (Handles ?action=delete_doctor)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_doctor' && isset($_GET['id'])) {
    $doctor_id = (int)$_GET['id'];
    
    mysqli_begin_transaction($conn);
    
    try {
        // STEP 1: SOFT DELETE RELATED APPOINTMENTS
        $sql_appts = "UPDATE appointment 
                     SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                     WHERE doctor_id = ?";
        $stmt_appts = mysqli_prepare($conn, $sql_appts);
        mysqli_stmt_bind_param($stmt_appts, 'ii', $_SESSION['user']['user_id'], $doctor_id);
        mysqli_stmt_execute($stmt_appts);
        mysqli_stmt_close($stmt_appts);

        // STEP 2: MARK DOCTOR AS DELETED
        $sql_doctor = "UPDATE doctor 
                      SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                      WHERE doctor_id = ?";
        $stmt_doctor = mysqli_prepare($conn, $sql_doctor);
        mysqli_stmt_bind_param($stmt_doctor, 'ii', $_SESSION['user']['user_id'], $doctor_id);
        mysqli_stmt_execute($stmt_doctor);
        mysqli_stmt_close($stmt_doctor);

        mysqli_commit($conn);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Record successfully deleted and moved to history.'];
        header('Location: admin.php?tab=doctors');
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Doctor deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the doctor deletion operation.'];
        header('Location: admin.php?tab=doctors');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3. PATIENT DELETION LOGIC (Handles ?action=delete_patient)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_patient' && isset($_GET['id'])) {
    $patient_id = (int)$_GET['id'];
    
    mysqli_begin_transaction($conn);
    
    try {
        // MARK PATIENT AS DELETED
        $sql_mark_deleted = "UPDATE patient 
                            SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                            WHERE patient_id = ?";
        $stmt_mark = mysqli_prepare($conn, $sql_mark_deleted);
        mysqli_stmt_bind_param($stmt_mark, 'ii', $_SESSION['user']['user_id'], $patient_id);
        mysqli_stmt_execute($stmt_mark);
        mysqli_stmt_close($stmt_mark);

        mysqli_commit($conn);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Record successfully deleted and moved to history.'];
        header('Location: admin.php?tab=patients');
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Patient deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the patient deletion operation.'];
        header('Location: admin.php?tab=patients');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3b. APPOINTMENT SOFT DELETION LOGIC (Handles ?action=delete_appointment)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_appointment' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    
    try {
        // Soft delete instead of hard delete
        $sql_appt = "UPDATE appointment
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE App_ID = ?";
        $stmt_appt = mysqli_prepare($conn, $sql_appt);
        mysqli_stmt_bind_param($stmt_appt, 'ii', $_SESSION['user']['user_id'], $app_id);
        mysqli_stmt_execute($stmt_appt);
        mysqli_stmt_close($stmt_appt);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Record successfully deleted and moved to history.'];
        header('Location: admin.php?tab=appointments');
        exit;

    } catch (Exception $e) {
        error_log("Appointment deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the appointment deletion operation.'];
        header('Location: admin.php?tab=appointments');
        exit;
    }
}
// ---------------------------------------------------------------------
// 3c. MEDICINE SOFT DELETION LOGIC (Handles ?action=delete_medicine)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_medicine' && isset($_GET['id'])) {
    $med_id = (int)$_GET['id'];
    
    try {
        $sql_med = "UPDATE medicine
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE med_id = ?";
        $stmt_med = mysqli_prepare($conn, $sql_med);
        mysqli_stmt_bind_param($stmt_med, 'ii', $_SESSION['user']['user_id'], $med_id);
        mysqli_stmt_execute($stmt_med);
        mysqli_stmt_close($stmt_med);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Medicine successfully deleted and moved to history.'];
        header('Location: admin.php?tab=medicines');
        exit;

    } catch (Exception $e) {
        error_log("Medicine deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the medicine deletion operation.'];
        header('Location: admin.php?tab=medicines');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3g. SERVICE SOFT DELETION LOGIC (Handles ?action=delete_service)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_service' && isset($_GET['id'])) {
    $service_id = (int)$_GET['id'];

    try {
        $sql_srv = "UPDATE services
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE Service_ID = ?";
        $stmt_srv = mysqli_prepare($conn, $sql_srv);
        mysqli_stmt_bind_param($stmt_srv, 'ii', $_SESSION['user']['user_id'], $service_id);
        mysqli_stmt_execute($stmt_srv);
        mysqli_stmt_close($stmt_srv);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Service successfully deleted and moved to history.'];
        header('Location: admin.php?tab=services');
        exit;

    } catch (Exception $e) {
        error_log("Service deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the service deletion operation.'];
        header('Location: admin.php?tab=services');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3d. ADD NEW MEDICINE LOGIC (Handles POST from add medicine form)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_medicine') {
    $med_name = trim($_POST['med_name']);
    $med_price = floatval($_POST['med_price']);

    if (!empty($med_name) && $med_price > 0) {
        $sql_add = "INSERT INTO medicine (med_name, med_price) VALUES (?, ?)";
        $stmt_add = mysqli_prepare($conn, $sql_add);
        mysqli_stmt_bind_param($stmt_add, 'sd', $med_name, $med_price);

        if (mysqli_stmt_execute($stmt_add)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Medicine successfully added.'];
            header('Location: admin.php?tab=medicines');
            exit;
        }
        mysqli_stmt_close($stmt_add);
    }
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred with the medicine operation.'];
    header('Location: admin.php?tab=medicines');
    exit;
}

// ---------------------------------------------------------------------
// 3e. ADD NEW SERVICE LOGIC (Handles POST from add service form)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $service_name = trim($_POST['service_name']);
    $service_price = floatval($_POST['service_price']);
    $available = $_POST['available'];

    if (!empty($service_name) && $service_price > 0 && isset($available) && ($available === '1' || $available === '0')) {
        $sql_add = "INSERT INTO services (Service_Name, Service_Price, Available) VALUES (?, ?, ?)";
        $stmt_add = mysqli_prepare($conn, $sql_add);
        $available_int = intval($available);
        mysqli_stmt_bind_param($stmt_add, 'sdi', $service_name, $service_price, $available_int);

        if (mysqli_stmt_execute($stmt_add)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Service successfully added.'];
            header('Location: admin.php?tab=services');
            exit;
        }
        mysqli_stmt_close($stmt_add);
    }
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred with the service operation.'];
    header('Location: admin.php?tab=services');
    exit;
}

// ---------------------------------------------------------------------
// 3f. PAYMENT STATUS UPDATE LOGIC
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    $app_id = intval($_POST['app_id']);
    $payment_status = $_POST['payment_status'];

    // Validate status
    $allowed_statuses = ['paid', 'rejected'];
    if (!in_array($payment_status, $allowed_statuses)) {
        header('Location: admin.php?tab=payment_verification&error=invalid_status');
        exit;
    }

    // Update payment status
    $admin_user_id = $_SESSION['user']['user_id'];
    $sql = "UPDATE appointment SET payment_status = ?, payment_updated_by = ? WHERE App_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $payment_status, $admin_user_id, $app_id);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: admin.php?tab=payment_verification&status=payment_updated');
    } else {
        header('Location: admin.php?tab=payment_verification&error=payment_update_failed');
    }
    mysqli_stmt_close($stmt);
    exit;
}

// ---------------------------------------------------------------------
// 3f. CONTACT MESSAGE DELETION LOGIC (Handles ?action=delete_contact_message)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_contact_message' && isset($_GET['id'])) {
    $msg_id = (int)$_GET['id'];

    try {
        $sql_msg = "UPDATE contact_messages
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE id = ?";
        $stmt_msg = mysqli_prepare($conn, $sql_msg);
        mysqli_stmt_bind_param($stmt_msg, 'ii', $_SESSION['user']['user_id'], $msg_id);
        mysqli_stmt_execute($stmt_msg);
        mysqli_stmt_close($stmt_msg);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contact message successfully deleted and moved to history.'];
        header('Location: admin.php?tab=contact_messages');
        exit;

    } catch (Exception $e) {
        error_log("Contact message deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the contact message deletion operation.'];
        header('Location: admin.php?tab=contact_messages');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3g. CONTACT MESSAGE REPLY LOGIC (Handles POST from reply form)
// ---------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_message') {
    $msg_id = intval($_POST['msg_id']);
    $reply = trim($_POST['reply']);

    if (!empty($reply) && $msg_id > 0) {
        $sql_reply = "UPDATE contact_messages
                      SET reply = ?, replied_at = NOW(), replied_by = ?
                      WHERE id = ?";
        $stmt_reply = mysqli_prepare($conn, $sql_reply);
        mysqli_stmt_bind_param($stmt_reply, 'sii', $reply, $_SESSION['user']['user_id'], $msg_id);

        if (mysqli_stmt_execute($stmt_reply)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully.'];
            header('Location: admin.php?tab=contact_messages');
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred while sending the reply.'];
            header('Location: admin.php?tab=contact_messages');
            exit;
        }
        mysqli_stmt_close($stmt_reply);
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid reply data.'];
        header('Location: admin.php?tab=contact_messages');
        exit;
    }
}

// ---------------------------------------------------------------------
// 3h. USER DELETION LOGIC (Handles ?action=delete_user)
// ---------------------------------------------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    try {
        $sql_user = "UPDATE `user`
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE user_id = ?";
        $stmt_user = mysqli_prepare($conn, $sql_user);
        mysqli_stmt_bind_param($stmt_user, 'ii', $_SESSION['user']['user_id'], $user_id);
        mysqli_stmt_execute($stmt_user);
        mysqli_stmt_close($stmt_user);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User successfully deleted and moved to history.'];
        header('Location: admin.php?tab=users');
        exit;

    } catch (Exception $e) {
        error_log("User deletion failed: " . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred during the user deletion operation.'];
        header('Location: admin.php?tab=users');
        exit;
    }
}

// ---------------------------------------------------------------------
// 4. DATA FETCHING
// ---------------------------------------------------------------------

$stats = $dbHelper->getStats();

// Fetch DISTINCT specializations for doctor filter
$specializations = [];
$qr_specs = "SELECT DISTINCT specialization FROM doctor WHERE is_deleted = 0 OR is_deleted IS NULL ORDER BY specialization";
$res_specs = mysqli_query($conn, $qr_specs);
if ($res_specs) {
    while ($r = mysqli_fetch_assoc($res_specs)) {
        if (!empty($r['specialization'])) {
            $specializations[] = $r['specialization'];
        }
    }
}

// Fetch total count of ACTIVE doctors with filter
$total_doctors_query = "SELECT COUNT(*) as total FROM doctor d JOIN `user` u ON d.user_id = u.user_id WHERE (d.is_deleted = 0 OR d.is_deleted IS NULL)";
if (!empty($filter_specialization)) {
    $total_doctors_query .= " AND d.specialization = ?";
}
if (!empty($filter_specialization)) {
    $stmt_total = mysqli_prepare($conn, $total_doctors_query);
    mysqli_stmt_bind_param($stmt_total, 's', $filter_specialization);
    mysqli_stmt_execute($stmt_total);
    $total_result = mysqli_stmt_get_result($stmt_total);
} else {
    $total_result = mysqli_query($conn, $total_doctors_query);
}
$total_doctors = mysqli_fetch_assoc($total_result)['total'];
$total_doctor_pages = ceil($total_doctors / $per_page);

// Fetch list of ACTIVE doctors with filter and pagination
$doctors = [];
$qr_doctors = "SELECT d.doctor_id, u.f_name, u.l_name, d.specialization 
                FROM doctor d 
                JOIN `user` u ON d.user_id = u.user_id
                WHERE (d.is_deleted = 0 OR d.is_deleted IS NULL)";
if (!empty($filter_specialization)) {
    $qr_doctors .= " AND d.specialization = ?";
}
$qr_doctors .= " ORDER BY u.f_name LIMIT ? OFFSET ?";

if (!empty($filter_specialization)) {
    $stmt_doctors = mysqli_prepare($conn, $qr_doctors);
    mysqli_stmt_bind_param($stmt_doctors, 'sii', $filter_specialization, $per_page, $offset);
    mysqli_stmt_execute($stmt_doctors);
    $res_doctors = mysqli_stmt_get_result($stmt_doctors);
} else {
    $stmt_doctors = mysqli_prepare($conn, $qr_doctors);
    mysqli_stmt_bind_param($stmt_doctors, 'ii', $per_page, $offset);
    mysqli_stmt_execute($stmt_doctors);
    $res_doctors = mysqli_stmt_get_result($stmt_doctors);
}

if ($res_doctors) {
    while ($r = mysqli_fetch_assoc($res_doctors)) {
        $doctors[] = $r;
    }
}

// Fetch DISTINCT patient names for patient filter
$patient_names = [];
$qr_pnames = "SELECT DISTINCT CONCAT(u.f_name, ' ', u.l_name) as full_name 
              FROM patient p 
              JOIN `user` u ON p.user_id = u.user_id 
              WHERE p.is_deleted = 0 OR p.is_deleted IS NULL
              ORDER BY u.f_name";
$res_pnames = mysqli_query($conn, $qr_pnames);
if ($res_pnames) {
    while ($r = mysqli_fetch_assoc($res_pnames)) {
        if (!empty($r['full_name'])) {
            $patient_names[] = $r['full_name'];
        }
    }
}

// Fetch DISTINCT user types for user filter
$user_types = [];
$qr_types = "SELECT DISTINCT user_type FROM `user` ORDER BY user_type";
$res_types = mysqli_query($conn, $qr_types);
if ($res_types) {
    while ($r = mysqli_fetch_assoc($res_types)) {
        if (!empty($r['user_type'])) {
            $user_types[] = $r['user_type'];
        }
    }
}

// Fetch list of ACTIVE patients with filter
$patients = [];
$qr_patients = "SELECT p.patient_id, u.f_name, u.l_name, u.email, u.phone 
                 FROM patient p 
                 JOIN `user` u ON p.user_id = u.user_id 
                 WHERE (p.is_deleted = 0 OR p.is_deleted IS NULL)";
if (!empty($filter_patient_name)) {
    $qr_patients .= " AND CONCAT(u.f_name, ' ', u.l_name) = ?";
}
$qr_patients .= " ORDER BY u.f_name";

if (!empty($filter_patient_name)) {
    $stmt_patients = mysqli_prepare($conn, $qr_patients);
    mysqli_stmt_bind_param($stmt_patients, 's', $filter_patient_name);
    mysqli_stmt_execute($stmt_patients);
    $res_patients = mysqli_stmt_get_result($stmt_patients);
} else {
    $res_patients = mysqli_query($conn, $qr_patients);
}

if ($res_patients) {
    while ($r = mysqli_fetch_assoc($res_patients)) {
        $patients[] = $r;
    }
}

// Fetch DISTINCT statuses for appointment filter
$appointment_statuses = [];
$qr_statuses = "SELECT DISTINCT status FROM appointment WHERE is_deleted = 0 OR is_deleted IS NULL ORDER BY status";
$res_statuses = mysqli_query($conn, $qr_statuses);
if ($res_statuses) {
    while ($r = mysqli_fetch_assoc($res_statuses)) {
        if (!empty($r['status'])) {
            $appointment_statuses[] = $r['status'];
        }
    }
}

// Fetch list of ACTIVE appointments with filter
$appointments = [];
$qr_appts = "SELECT
                a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status, a.payment_proof, a.payment_time,
                up.f_name AS patient_fname, up.l_name AS patient_lname,
                ud.f_name AS doctor_fname, ud.l_name AS doctor_lname,
                COALESCE((SELECT SUM(s.Service_Price) FROM appointment_services as2 JOIN services s ON as2.Service_ID = s.Service_ID WHERE as2.App_ID = a.App_ID), 0) AS service_total,
                COALESCE((SELECT SUM(m.med_price * p.quantity) FROM prescription p JOIN medicine m ON p.med_id = m.med_id WHERE p.App_ID = a.App_ID), 0) AS medicine_total,
                (SELECT GROUP_CONCAT(s.Service_Name SEPARATOR ', ') FROM appointment_services as2 JOIN services s ON as2.Service_ID = s.Service_ID WHERE as2.App_ID = a.App_ID) AS services_list,
                (SELECT GROUP_CONCAT(CONCAT(m.med_name, ' (', p.quantity, ')') SEPARATOR ', ') FROM prescription p JOIN medicine m ON p.med_id = m.med_id WHERE p.App_ID = a.App_ID) AS medicines_list
            FROM
                appointment a
            JOIN
                patient p ON a.patient_id = p.patient_id
            JOIN
                doctor d ON a.doctor_id = d.doctor_id
            JOIN
                `user` up ON p.user_id = up.user_id
            JOIN
                `user` ud ON d.user_id = ud.user_id
            WHERE (a.is_deleted = 0 OR a.is_deleted IS NULL)";
if (!empty($filter_appointment_status)) {
    $qr_appts .= " AND a.status = ?";
}
$qr_appts .= " ORDER BY a.App_Date ASC, a.App_Time ASC";

if (!empty($filter_appointment_status)) {
    $stmt_appts = mysqli_prepare($conn, $qr_appts);
    mysqli_stmt_bind_param($stmt_appts, 's', $filter_appointment_status);
    mysqli_stmt_execute($stmt_appts);
    $res_appts = mysqli_stmt_get_result($stmt_appts);
} else {
    $res_appts = mysqli_query($conn, $qr_appts);
}

if ($res_appts) {
    while ($r = mysqli_fetch_assoc($res_appts)) {
        $appointments[] = $r;
    }
}

// Fetch DELETED doctor history (NO FILTER)
$doctor_history = [];
$qr_doctor_history = "SELECT 
                        d.doctor_id, u.f_name, u.l_name, u.email, u.phone, d.specialization,
                        d.deleted_at, ud.f_name AS deleted_by_name
                      FROM 
                        doctor d
                      JOIN 
                        `user` u ON d.user_id = u.user_id
                      LEFT JOIN 
                        `user` ud ON d.deleted_by = ud.user_id
                      WHERE d.is_deleted = 1
                      ORDER BY d.deleted_at DESC";

$res_doctor_history = mysqli_query($conn, $qr_doctor_history);

if ($res_doctor_history) {
    while ($r = mysqli_fetch_assoc($res_doctor_history)) {
        $doctor_history[] = $r;
    }
}

// Fetch DELETED patient history (NO FILTER)
$patient_history = [];
$qr_history = "SELECT p.patient_id, u.f_name, u.l_name, u.email, u.phone, 
                      p.deleted_at, ud.f_name AS deleted_by_name
               FROM patient p
               JOIN `user` u ON p.user_id = u.user_id
               LEFT JOIN `user` ud ON p.deleted_by = ud.user_id
               WHERE p.is_deleted = 1
               ORDER BY p.deleted_at DESC";

$res_history = mysqli_query($conn, $qr_history);

if ($res_history) {
    while ($r = mysqli_fetch_assoc($res_history)) {
        $patient_history[] = $r;
    }
}

// Fetch DELETED appointment history (NO FILTER)
$appointment_history = [];
$qr_appt_history = "SELECT 
                        a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status,
                        up.f_name AS patient_fname, up.l_name AS patient_lname,
                        ud.f_name AS doctor_fname, ud.l_name AS doctor_lname,
                        a.deleted_at, ux.f_name AS deleted_by_name
                    FROM 
                        appointment a
                    JOIN 
                        patient p ON a.patient_id = p.patient_id
                    JOIN 
                        doctor d ON a.doctor_id = d.doctor_id
                    JOIN 
                        `user` up ON p.user_id = up.user_id
                    JOIN 
                        `user` ud ON d.user_id = ud.user_id
                    LEFT JOIN 
                        `user` ux ON a.deleted_by = ux.user_id
                    WHERE a.is_deleted = 1
                    ORDER BY a.deleted_at DESC";

$res_appt_history = mysqli_query($conn, $qr_appt_history);

if ($res_appt_history) {
    while ($r = mysqli_fetch_assoc($res_appt_history)) {
        $appointment_history[] = $r;
    }
}

// Fetch list of ACTIVE medicines
$medicines = [];
$qr_medicines = "SELECT med_id, med_name, med_price
                 FROM medicine
                 WHERE (is_deleted = 0 OR is_deleted IS NULL)
                 ORDER BY med_name";
$res_medicines = mysqli_query($conn, $qr_medicines);
if ($res_medicines) {
    while ($r = mysqli_fetch_assoc($res_medicines)) {
        $medicines[] = $r;
    }
}

// Fetch list of ACTIVE services
$services = [];
$qr_services = "SELECT Service_ID, Service_Name, Service_Price, Available
                FROM services
                WHERE (is_deleted = 0 OR is_deleted IS NULL)
                ORDER BY Service_Name";
$res_services = mysqli_query($conn, $qr_services);
if ($res_services) {
    while ($r = mysqli_fetch_assoc($res_services)) {
        $services[] = $r;
    }
}

// Fetch DELETED service history
$service_history = [];
$qr_service_history = "SELECT s.Service_ID, s.Service_Name, s.Service_Price, s.Available, s.deleted_at,
                              u.f_name AS deleted_by_name
                       FROM services s
                       LEFT JOIN `user` u ON s.deleted_by = u.user_id
                       WHERE s.is_deleted = 1
                       ORDER BY s.deleted_at DESC";
$res_service_history = mysqli_query($conn, $qr_service_history);
if ($res_service_history) {
    while ($r = mysqli_fetch_assoc($res_service_history)) {
        $service_history[] = $r;
    }
}

// Fetch DELETED medicine history
$medicine_history = [];
$qr_med_history = "SELECT m.med_id, m.med_name, m.med_price, m.deleted_at,
                          u.f_name AS deleted_by_name
                   FROM medicine m
                   LEFT JOIN `user` u ON m.deleted_by = u.user_id
                   WHERE m.is_deleted = 1
                   ORDER BY m.deleted_at DESC";
$res_med_history = mysqli_query($conn, $qr_med_history);
if ($res_med_history) {
    while ($r = mysqli_fetch_assoc($res_med_history)) {
        $medicine_history[] = $r;
    }
}

// Fetch appointments with pending payments for verification
$pending_payments = [];
$qr_payments = "SELECT a.App_ID, a.App_Date, a.App_Time, a.payment_status, a.payment_proof, a.payment_time,
                       up.f_name AS patient_fname, up.l_name AS patient_lname, up.email AS patient_email,
                       ud.f_name AS doctor_fname, ud.l_name AS doctor_lname, s.Service_Price
                FROM appointment a
                JOIN patient p ON a.patient_id = p.patient_id
                JOIN doctor d ON a.doctor_id = d.doctor_id
                JOIN services s ON a.Service_ID = s.Service_ID
                JOIN user up ON p.user_id = up.user_id
                JOIN user ud ON d.user_id = ud.user_id
                WHERE a.payment_status IN ('pending', 'rejected') AND (a.is_deleted = 0 OR a.is_deleted IS NULL)
                ORDER BY a.payment_time ASC";
$res_payments = mysqli_query($conn, $qr_payments);
if ($res_payments) {
    while ($row = mysqli_fetch_assoc($res_payments)) {
        $pending_payments[] = $row;
    }
}

// Fetch payment history (paid appointments)
$payment_history = [];
$qr_payment_history = "SELECT a.App_ID, a.App_Date, a.App_Time, a.payment_time, a.payment_proof,
                       up.f_name AS patient_fname, up.l_name AS patient_lname,
                       s.Service_Price,
                       ua.f_name AS approved_by_fname
                FROM appointment a
                JOIN patient p ON a.patient_id = p.patient_id
                JOIN services s ON a.Service_ID = s.Service_ID
                JOIN user up ON p.user_id = up.user_id
                LEFT JOIN user ua ON a.payment_updated_by = ua.user_id
                WHERE a.payment_status = 'paid' AND (a.is_deleted = 0 OR a.is_deleted IS NULL)
                ORDER BY a.payment_time DESC";
$res_payment_history = mysqli_query($conn, $qr_payment_history);
if ($res_payment_history) {
    while ($row = mysqli_fetch_assoc($res_payment_history)) {
        $payment_history[] = $row;
    }
}


// Fetch contact messages (excluding deleted) with filters
$contact_messages = [];
$qr_messages = "SELECT id, name, email, message, created_at, reply, replied_at, replied_by FROM contact_messages WHERE (is_deleted = 0 OR is_deleted IS NULL)";
if (!empty($filter_message_status)) {
    if ($filter_message_status == 'replied') {
        $qr_messages .= " AND reply IS NOT NULL";
    } elseif ($filter_message_status == 'unreplied') {
        $qr_messages .= " AND reply IS NULL";
    }
}
if (!empty($filter_date_range)) {
    $days = intval($filter_date_range);
    if ($days > 0) {
        $qr_messages .= " AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    }
}
$qr_messages .= " ORDER BY created_at DESC";
$res_messages = mysqli_query($conn, $qr_messages);
if ($res_messages) {
    while ($row = mysqli_fetch_assoc($res_messages)) {
        $contact_messages[] = $row;
    }
}

// Fetch list of all users with filter by user_type
$users = [];
$qr_users = "SELECT user_id, f_name, l_name, email, phone, user_type FROM `user` WHERE 1=1";
if (!empty($filter_user_type)) {
    $qr_users .= " AND user_type = ?";
}
$qr_users .= " ORDER BY f_name";

if (!empty($filter_user_type)) {
    $stmt_users = mysqli_prepare($conn, $qr_users);
    mysqli_stmt_bind_param($stmt_users, 's', $filter_user_type);
    mysqli_stmt_execute($stmt_users);
    $res_users = mysqli_stmt_get_result($stmt_users);
} else {
    $res_users = mysqli_query($conn, $qr_users);
}

if ($res_users) {
    while ($r = mysqli_fetch_assoc($res_users)) {
        $users[] = $r;
    }
}

// ---------------------------------------------------------------------
// 5. HTML OUTPUT
// ---------------------------------------------------------------------
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="brand">ADMIN PANEL</div>
        <nav class="nav">
            <span class="welcome-text">Welcome, <?=htmlspecialchars($admin_name)?></span>
        <a href="../index.php">View Site</a>
        <button id="theme-toggle" class="btn btn-secondary theme-toggle-btn">🌙</button>
        <a href="../logout.php" class="btn">Logout</a>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="sidebar">
            <a href="?tab=dashboard" class="<?= $current_tab == 'dashboard' ? 'active' : '' ?>">Dashboard Overview</a>
            <a href="?tab=doctors" class="<?= $current_tab == 'doctors' ? 'active' : '' ?>">Manage Doctors</a>
            <a href="?tab=patients" class="<?= $current_tab == 'patients' ? 'active' : '' ?>">Manage Patients</a>
            <a href="?tab=users" class="<?= $current_tab == 'users' ? 'active' : '' ?>">Manage Users</a>
            <a href="?tab=appointments" class="<?= $current_tab == 'appointments' ? 'active' : '' ?>">View Appointments</a>
            <a href="?tab=payment_verification" class="<?= $current_tab == 'payment_verification' ? 'active' : '' ?>">Payment Verification</a>
            <a href="?tab=payment_history" class="<?= $current_tab == 'payment_history' ? 'active' : '' ?>">Payment History</a>
            <a href="?tab=contact_messages" class="<?= $current_tab == 'contact_messages' ? 'active' : '' ?>">Contact Messages</a>
            <a href="?tab=doctor_history" class="<?= $current_tab == 'doctor_history' ? 'active' : '' ?>">Doctor History</a>
            <a href="?tab=patient_history" class="<?= $current_tab == 'patient_history' ? 'active' : '' ?>">Patient History</a>
            <a href="?tab=appointment_history" class="<?= $current_tab == 'appointment_history' ? 'active' : '' ?>">Appointment History</a>
            <a href="?tab=medicines" class="<?= $current_tab == 'medicines' ? 'active' : '' ?>">Manage Medicines</a>
            <a href="?tab=medicine_history" class="<?= $current_tab == 'medicine_history' ? 'active' : '' ?>">Medicine History</a>
            <a href="?tab=services" class="<?= $current_tab == 'services' ? 'active' : '' ?>">Manage Services</a>
            <a href="?tab=service_history" class="<?= $current_tab == 'service_history' ? 'active' : '' ?>">Service History</a>
        </div>

        <div class="main-content">
            <h1>Admin Dashboard - <?=ucwords(str_replace('_', ' ', $current_tab))?></h1>

            <?php
            // FLASH MESSAGE HANDLER
            if (isset($_SESSION['flash'])) {
                $flash = $_SESSION['flash'];
                $alert_class = $flash['type'] === 'success' ? 'alert-success' : 'alert-error';
                echo "<div class='alert {$alert_class}'>{$flash['message']}</div>";
                unset($_SESSION['flash']); // Unset the message so it doesn't show again
            }
            ?>

            <?php 
            // Status/Error Messages
            if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
                echo '<div class="alert alert-success">Record successfully deleted and moved to history.</div>';
            }
            if (isset($_GET['status']) && $_GET['status'] === 'added') {
                echo '<div class="alert alert-success">Medicine successfully added.</div>';
            }
            if (isset($_GET['status']) && $_GET['status'] === 'service_added') {
                echo '<div class="alert alert-success">Service successfully added.</div>';
            }
            if (isset($_GET['status']) && $_GET['status'] === 'payment_updated') {
                echo '<div class="alert alert-success">Payment status updated successfully.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '1') {
                echo '<div class="alert alert-error">An error occurred during the doctor deletion operation.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '2') {
                echo '<div class="alert alert-error">An error occurred during the patient deletion operation.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '3') {
                echo '<div class="alert alert-error">An error occurred during the appointment deletion operation.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '4') {
                echo '<div class="alert alert-error">An error occurred with the medicine operation.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === 'invalid_status') {
                echo '<div class="alert alert-error">Invalid payment status.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === 'payment_update_failed') {
                echo '<div class="alert alert-error">Failed to update payment status.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '5') {
                echo '<div class="alert alert-error">An error occurred during the contact message deletion operation.</div>';
            }

            // Flash Messages
            if (isset($_SESSION['flash'])) {
                $flash = $_SESSION['flash'];
                echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . '">' . htmlspecialchars($flash['message']) . '</div>';
                unset($_SESSION['flash']);
            }
            if (isset($_GET['status']) && $_GET['status'] === 'replied') {
                echo '<div class="alert alert-success">Reply sent successfully.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '6') {
                echo '<div class="alert alert-error">An error occurred while sending the reply.</div>';
            }
            if (isset($_GET['error']) && $_GET['error'] === '7') {
                echo '<div class="alert alert-error">Invalid reply data.</div>';
            }
            ?>

            <?php if ($current_tab === 'dashboard'): ?>
                <div class="stat-cards">
                    <div class="card">
                        <h3>Total Doctors</h3>
                        <p><?=htmlspecialchars($stats['doctors'] ?? 0)?></p>
                    </div>
                    <div class="card">
                        <h3>Total Patients</h3>
                        <p><?=htmlspecialchars($stats['patients'] ?? 0)?></p> 
                    </div>
                    <div class="card">
                        <h3>Total Appointments</h3>
                        <p><?=htmlspecialchars($stats['appointments'] ?? 0)?></p>
                    </div>
                </div>
                
                <p>More dashboard charts and summary information goes here.</p>

            <?php elseif ($current_tab === 'doctors'): ?>
              <h2>Doctor Management</h2>
              <?php $row_number = 1; ?>
              
              <!-- Filter Bar -->
              <form method="GET" class="filter-bar">
                  <input type="hidden" name="tab" value="doctors">
                  <label for="filter_specialization">Filter by Specialization:</label>
                  <select name="filter_specialization" id="filter_specialization">
                      <option value="">All Specializations</option>
                      <?php foreach($specializations as $spec): ?>
                          <option value="<?=htmlspecialchars($spec)?>" <?=$filter_specialization === $spec ? 'selected' : ''?>>
                              <?=htmlspecialchars($spec)?>
                          </option>
                      <?php endforeach; ?>
                  </select>
                  <button type="submit">Apply Filter</button>
                  <a href="?tab=doctors" class="clear-btn">Clear</a>
              </form>
              
              <div class="doctor-add-btn">
                  <a href="../register.php?type=doctor" class="btn btn-success">+ Add New Doctor</a>
              </div>
    
              <table class="table">
                  <thead>
                      <tr>
                          <th>No.</th>
                          <th>ID</th>
                          <th>Name</th>
                          <th>Specialization</th>
                          <th>Action</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (empty($doctors)): ?>
                          <tr><td colspan="5">No doctor records found.</td></tr>
                      <?php else: ?>
                          <?php foreach($doctors as $doc): ?>
                              <tr>
                                  <td><?= $row_number ?></td>
                                  <td><?=htmlspecialchars($doc['doctor_id'])?></td>
                                  <td>Dr. <?=htmlspecialchars($doc['f_name'] . ' ' . $doc['l_name'])?></td>
                                  <td><?=htmlspecialchars($doc['specialization'])?></td>
                                  <td>
                                      <a href="../edit_doctor.php?id=<?=htmlspecialchars($doc['doctor_id'])?>" class="btn btn-sm">Edit</a>
                                      
                                      <a href="?tab=doctors&action=delete_doctor&id=<?=htmlspecialchars($doc['doctor_id'])?>" 
                                          onclick="return confirm('Are you sure you want to delete this doctor? They will be moved to history along with their appointments.')" 
                                          class="btn btn-sm btn-danger">Delete</a>
                                  </td>
                              </tr>
                              <?php $row_number++; ?>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </tbody>
              </table>

            <?php elseif ($current_tab === 'patients'): ?>
                <h2>Patient Management</h2>
                <p>List of all registered patients.</p>
                <?php $row_number = 1; ?>
                
                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="patients">
                    <label for="filter_patient_name">Filter by Name:</label>
                    <select name="filter_patient_name" id="filter_patient_name">
                        <option value="">All Patients</option>
                        <?php foreach($patient_names as $pname): ?>
                            <option value="<?=htmlspecialchars($pname)?>" <?=$filter_patient_name === $pname ? 'selected' : ''?>>
                                <?=htmlspecialchars($pname)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply Filter</button>
                    <a href="?tab=patients" class="clear-btn">Clear</a>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="6">No patient records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($patients as $pat): ?>
                                <tr>
                                    <td><?= $row_number ?></td>
                                    <td><?=htmlspecialchars($pat['patient_id'])?></td>
                                    <td><?=htmlspecialchars($pat['f_name'] . ' ' . $pat['l_name'])?></td>
                                    <td><?=htmlspecialchars($pat['email'])?></td>
                                    <td><?=htmlspecialchars($pat['phone'])?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-secondary">View</a>
                                        <a href="?tab=patients&action=delete_patient&id=<?=htmlspecialchars($pat['patient_id'])?>" 
                                          onclick="return confirm('Are you sure you want to delete this patient? They will be moved to history.')" 
                                            class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                                <?php $row_number++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'users'): ?>
                <h2>User Management</h2>
                <p>List of all registered users.</p>
                <?php $row_number = 1; ?>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="users">
                    <label for="filter_user_type">Filter by User Type:</label>
                    <select name="filter_user_type" id="filter_user_type">
                        <option value="">All Types</option>
                        <?php foreach($user_types as $type): ?>
                            <option value="<?=htmlspecialchars($type)?>" <?=$filter_user_type === $type ? 'selected' : ''?>>
                                <?=htmlspecialchars(ucfirst($type))?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply Filter</button>
                    <a href="?tab=users" class="clear-btn">Clear</a>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>User Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7">No user records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($users as $usr): ?>
                                <tr>
                                    <td><?= $row_number ?></td>
                                    <td><?=htmlspecialchars($usr['user_id'])?></td>
                                    <td><?=htmlspecialchars($usr['f_name'] . ' ' . $usr['l_name'])?></td>
                                    <td><?=htmlspecialchars($usr['email'])?></td>
                                    <td><?=htmlspecialchars($usr['phone'])?></td>
                                    <td><?=htmlspecialchars(ucfirst($usr['user_type']))?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-secondary">View</a>
                                        <a href="?tab=users&action=delete_user&id=<?=htmlspecialchars($usr['user_id'])?>"
                                          onclick="return confirm('Are you sure you want to delete this user? They will be moved to history.')"
                                            class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                                <?php $row_number++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'appointments'): ?>
                <h2>Appointment Management</h2>
                <p>View and manage all system appointments here.</p>
                <?php $row_number = 1; ?>
                
                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="appointments">
                    <label for="filter_appointment_status">Filter by Status:</label>
                    <select name="filter_appointment_status" id="filter_appointment_status">
                        <option value="">All Statuses</option>
                        <?php foreach($appointment_statuses as $status): ?>
                            <option value="<?=htmlspecialchars($status)?>" <?=$filter_appointment_status === $status ? 'selected' : ''?>>
                                <?=htmlspecialchars(ucfirst($status))?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply Filter</button>
                    <a href="?tab=appointments" class="clear-btn">Clear</a>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Symptoms</th>
                            <th>Services</th>
                            <th>Medicines</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Payment Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="13">No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach($appointments as $appt): ?>
                                <tr>
                                    <td><?= $row_number ?></td>
                                    <td><?=htmlspecialchars($appt['App_ID'])?></td>
                                    <td><?=htmlspecialchars($appt['patient_fname'] . ' ' . $appt['patient_lname'])?></td>
                                    <td>Dr. <?=htmlspecialchars($appt['doctor_fname'] . ' ' . $appt['doctor_lname'])?></td>
                                    <td><?=htmlspecialchars($appt['App_Date'])?></td>
                                    <td><?=htmlspecialchars($appt['App_Time'])?></td>
                                    <td><?=htmlspecialchars($appt['symptom'])?></td>
                                    <td><?php echo htmlspecialchars($appt['services_list'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($appt['medicines_list'] ?? 'N/A'); ?></td>
                                    <td>RM <?php echo number_format(($appt['service_total'] + $appt['medicine_total']), 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        if ($appt['status'] == 'pending') $status_class = 'badge-pending';
                                        elseif ($appt['status'] == 'approved') $status_class = 'badge-approved';
                                        elseif ($appt['status'] == 'disapproved') $status_class = 'badge-disapproved';
                                        else $status_class = 'badge-secondary';
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?=htmlspecialchars(ucfirst($appt['status']))?></span>
                                    </td>
                                    <td>
                                        <span class="payment-status <?=htmlspecialchars($appt['payment_status'])?>">
                                            <?=ucfirst($appt['payment_status'])?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?tab=appointments&action=delete_appointment&id=<?=htmlspecialchars($appt['App_ID'])?>"
                                            onclick="return confirm('Are you sure you want to delete appointment ID: <?=htmlspecialchars($appt['App_ID'])?>? It will be moved to history.')"
                                            class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                                <?php $row_number++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'payment_verification'): ?>
                <h2>Payment Verification</h2>
                <p>Review and verify patient payment proofs.</p>
                
                <?php if (empty($pending_payments)): ?>
                    <div class="alert alert-success">No pending payments to verify.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Payment Proof</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_payments as $payment): ?>
                            <tr>
                                <td><?=htmlspecialchars($payment['App_ID'])?></td>
                                <td><?=htmlspecialchars($payment['patient_fname'].' '.$payment['patient_lname'])?><br>
                                    <small><?=htmlspecialchars($payment['patient_email'])?></small>
                                </td>
                                <td>Dr. <?=htmlspecialchars($payment['doctor_fname'].' '.$payment['doctor_lname'])?></td>
                                <td><?=htmlspecialchars($payment['App_Date'])?> <?=htmlspecialchars($payment['App_Time'])?></td>
<td>RM <?=number_format($payment['Service_Price'], 2)?></td>
                                <td style="text-align: center !important;">
                                    <div class="proof-link-container">
                                        <?php if ($payment['payment_proof']): ?>
                                            <div class="view-proof-container">
                                                <a href="#" onclick="openProofModal('../uploads/payments/<?=htmlspecialchars($payment['payment_proof'])?>')">View Proof</a>
                                            </div>
                                        <?php else: ?>
                                            No proof uploaded
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?=htmlspecialchars($payment['payment_time'])?></td>
                                <td>
                                    <span class="status-badge <?=htmlspecialchars($payment['payment_status'])?>">
                                        <?=ucfirst($payment['payment_status'])?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_payment_status">
                                        <input type="hidden" name="app_id" value="<?=htmlspecialchars($payment['App_ID'])?>">
                                        <select name="payment_status">
                                            <option value="pending" <?= $payment['payment_status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="paid" <?= $payment['payment_status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="rejected" <?= $payment['payment_status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php elseif ($current_tab === 'payment_history'): ?>
                <h2>Approved Payment History</h2>
                <p>This page shows a history of all payments that have been approved.</p>

                <?php if (empty($payment_history)): ?>
                    <div class="alert alert-info">No approved payment history found.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Appointment Date</th>
                                <th>Amount</th>
                                <th>Payment Proof</th>
                                <th>Submitted At</th>
                                <th>Approved By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payment_history as $payment): ?>
                            <tr>
                                <td><?=htmlspecialchars($payment['App_ID'])?></td>
                                <td><?=htmlspecialchars($payment['patient_fname'].' '.$payment['patient_lname'])?></td>
                                <td><?=htmlspecialchars($payment['App_Date'])?></td>
                                <td>RM <?=number_format($payment['Service_Price'], 2)?></td>
                                <td style="text-align: center !important;">
                                    <div class="proof-link-container">
                                        <?php if ($payment['payment_proof']): ?>
                                            <a href="#" onclick="openProofModal('../uploads/payments/<?=htmlspecialchars($payment['payment_proof'])?>')">View Proof</a>
                                        <?php else: ?>
                                            No proof uploaded
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?=htmlspecialchars($payment['payment_time'])?></td>
                                <td><?=htmlspecialchars($payment['approved_by_fname'] ?? 'N/A')?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php elseif ($current_tab === 'contact_messages'): ?>
                <h2>Contact Messages</h2>
                <p>View and reply to messages submitted through the contact form.</p>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="contact_messages">
                    <label for="filter_message_status">Filter by Status:</label>
                    <select name="filter_message_status" id="filter_message_status">
                        <option value="">All Messages</option>
                        <option value="replied" <?=$filter_message_status === 'replied' ? 'selected' : ''?>>Replied</option>
                        <option value="unreplied" <?=$filter_message_status === 'unreplied' ? 'selected' : ''?>>Unreplied</option>
                    </select>
                    <label for="filter_date_range">Filter by Date:</label>
                    <select name="filter_date_range" id="filter_date_range">
                        <option value="">All Time</option>
                        <option value="7" <?=$filter_date_range === '7' ? 'selected' : ''?>>Last 7 Days</option>
                        <option value="30" <?=$filter_date_range === '30' ? 'selected' : ''?>>Last 30 Days</option>
                        <option value="90" <?=$filter_date_range === '90' ? 'selected' : ''?>>Last 90 Days</option>
                    </select>
                    <button type="submit">Apply Filter</button>
                    <a href="?tab=contact_messages" class="clear-btn">Clear</a>
                </form>

                <?php if (empty($contact_messages)): ?>
                    <div class="alert alert-info">No contact messages found.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Reply</th>
                                <th>Created At</th>
                                <th>Replied At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($contact_messages as $msg): ?>
                            <tr>
                                <td><?=htmlspecialchars($msg['id'])?></td>
                                <td><?=htmlspecialchars($msg['name'])?></td>
                                <td><?=htmlspecialchars($msg['email'])?></td>
                                <td><?=htmlspecialchars($msg['message'])?></td>
                                <td>
                                    <?php if (!empty($msg['reply'])): ?>
                                        <div class="reply-text"><?=htmlspecialchars($msg['reply'])?></div>
                                    <?php else: ?>
                                        <em>No reply yet</em>
                                    <?php endif; ?>
                                </td>
                                <td><?=htmlspecialchars($msg['created_at'])?></td>
                                <td>
                                    <?php if (!empty($msg['replied_at'])): ?>
                                        <?=htmlspecialchars($msg['replied_at'])?>
                                    <?php else: ?>
                                        <em>Not replied</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($msg['reply'])): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="openReplyModal(<?=htmlspecialchars($msg['id'])?>, '<?=htmlspecialchars($msg['name'])?>', '<?=htmlspecialchars($msg['email'])?>', '<?=htmlspecialchars(addslashes($msg['message']))?>')">Reply</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="openReplyModal(<?=htmlspecialchars($msg['id'])?>, '<?=htmlspecialchars($msg['name'])?>', '<?=htmlspecialchars($msg['email'])?>', '<?=htmlspecialchars(addslashes($msg['message']))?>')">Edit Reply</button>
                                    <?php endif; ?>
                                    <a href="?tab=contact_messages&action=delete_contact_message&id=<?=htmlspecialchars($msg['id'])?>"
                                        onclick="return confirm('Are you sure you want to delete this contact message?')"
                                        class="btn btn-sm btn-danger">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Reply Modal -->
                <div id="replyModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeReplyModal()">&times;</span>
                        <h3>Reply to Contact Message</h3>
                        <div id="messageDetails"></div>
                        <form id="replyForm" method="POST">
                            <input type="hidden" name="action" value="reply_message">
                            <input type="hidden" name="msg_id" id="msg_id">
                            <label for="reply">Your Reply:</label><br>
                            <textarea name="reply" id="reply" rows="6" required></textarea><br>
                            <button type="submit" class="btn btn-primary">Send Reply</button>
                            <button type="button" onclick="closeReplyModal()" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>

                <script>
                function openReplyModal(msgId, name, email, message) {
                    document.getElementById('msg_id').value = msgId;
                    document.getElementById('messageDetails').innerHTML = `
                        <strong>From:</strong> ${name} (${email})<br>
                        <strong>Message:</strong>
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


            <?php elseif ($current_tab === 'doctor_history'): ?>
                <h2>Doctor Deletion History</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Specialization</th>
                            <th>Deleted At</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctor_history)): ?>
                            <tr><td colspan="7">No deleted doctor records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($doctor_history as $doc): ?>
                                <tr>
                                    <td><?=htmlspecialchars($doc['doctor_id'])?></td>
                                    <td>Dr. <?=htmlspecialchars($doc['f_name'] . ' ' . $doc['l_name'])?></td>
                                    <td><?=htmlspecialchars($doc['email'])?></td>
                                    <td><?=htmlspecialchars($doc['phone'])?></td>
                                    <td><?=htmlspecialchars($doc['specialization'])?></td>
                                    <td><?=htmlspecialchars($doc['deleted_at'])?></td>
                                    <td><?=htmlspecialchars($doc['deleted_by_name'])?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'patient_history'): ?>
                <h2>Patient Deletion History</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Deleted At</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patient_history)): ?>
                            <tr><td colspan="6">No deleted patient records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($patient_history as $pat): ?>
                                <tr>
                                    <td><?=htmlspecialchars($pat['patient_id'])?></td>
                                    <td><?=htmlspecialchars($pat['f_name'] . ' ' . $pat['l_name'])?></td>
                                    <td><?=htmlspecialchars($pat['email'])?></td>
                                    <td><?=htmlspecialchars($pat['phone'])?></td>
                                    <td><?=htmlspecialchars($pat['deleted_at'])?></td>
                                    <td><?=htmlspecialchars($pat['deleted_by_name'])?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'appointment_history'): ?>
                <h2>Appointment Deletion History</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Symptoms</th>
                            <th>Status</th>
                            <th>Payment Status</th>
                            <th>Deleted At</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointment_history)): ?>
                            <tr><td colspan="11">No deleted appointment records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($appointment_history as $appt): ?>
                                <tr>
                                    <td><?=htmlspecialchars($appt['App_ID'])?></td>
                                    <td><?=htmlspecialchars($appt['patient_fname'] . ' ' . $appt['patient_lname'])?></td>
                                    <td>Dr. <?=htmlspecialchars($appt['doctor_fname'] . ' ' . $appt['doctor_lname'])?></td>
                                    <td><?=htmlspecialchars($appt['App_Date'])?></td>
                                    <td><?=htmlspecialchars($appt['App_Time'])?></td>
                                    <td><?=htmlspecialchars($appt['symptom'])?></td>
                                    <td>
                                        <span class="badge <?= $appt['status'] == 'pending' ? 'badge-pending' : ($appt['status'] == 'approved' ? 'badge-approved' : 'badge-secondary') ?>">
                                            <?=htmlspecialchars(ucfirst($appt['status']))?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="payment-status <?=htmlspecialchars($appt['payment_status'])?>">
                                            <?=ucfirst($appt['payment_status'])?>
                                        </span>
                                    </td>
                                    <td><?=htmlspecialchars($appt['deleted_at'])?></td>
                                    <td><?=htmlspecialchars($appt['deleted_by_name'])?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'medicines'): ?>
                <h2>Medicine Management</h2>
                <div class="medicine-add-btn">
                    <button type="button" class="btn btn-success" onclick="document.getElementById('add-medicine-form').style.display='block'">+ Add New Medicine</button>
                </div>
                <div id="add-medicine-form" class="add-medicine-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_medicine">
                        <label for="med_name">Medicine Name:</label>
                        <input type="text" name="med_name" required>
                        <label for="med_price">Price (RM):</label>
                        <input type="number" step="0.01" name="med_price" required>
                        <button type="submit" class="btn">Add Medicine</button>
                        <button type="button" onclick="document.getElementById('add-medicine-form').style.display='none'" class="btn btn-secondary">Cancel</button>
                    </form>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr><td colspan="4">No medicine records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($medicines as $med): ?>
                                <tr>
                                    <td><?=htmlspecialchars($med['med_id'])?></td>
                                    <td><?=htmlspecialchars($med['med_name'])?></td>
                                    <td>RM <?=number_format($med['med_price'], 2)?></td>
                                    <td>
                                        <a href="?tab=medicines&action=delete_medicine&id=<?=htmlspecialchars($med['med_id'])?>"
                                            onclick="return confirm('Are you sure you want to delete this medicine?')"
                                            class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'medicine_history'): ?>
                <h2>Medicine Deletion History</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Deleted At</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicine_history)): ?>
                            <tr><td colspan="5">No deleted medicine records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($medicine_history as $med): ?>
                                <tr>
                                    <td><?=htmlspecialchars($med['med_id'])?></td>
                                    <td><?=htmlspecialchars($med['med_name'])?></td>
                                    <td>RM <?=number_format($med['med_price'], 2)?></td>
                                    <td><?=htmlspecialchars($med['deleted_at'])?></td>
                                    <td><?=htmlspecialchars($med['deleted_by_name'])?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'services'): ?>
                <h2>Service Management</h2>
                <div class="service-add-btn">
                    <button type="button" class="btn btn-success" onclick="document.getElementById('add-service-form').style.display='block'">+ Add New Service</button>
                </div>
                <div id="add-service-form" class="add-service-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_service">
                        <label for="service_name">Service Name:</label>
                        <input type="text" name="service_name" required>
                        <label for="service_price">Price (RM):</label>
                        <input type="number" step="0.01" name="service_price" required>
                        <label for="available">Available:</label>
                        <select name="available" required>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <button type="submit" class="btn">Add Service</button>
                        <button type="button" onclick="document.getElementById('add-service-form').style.display='none'" class="btn btn-secondary">Cancel</button>
                    </form>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Available</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="5">No service records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($services as $srv): ?>
                                <tr>
                                    <td><?=htmlspecialchars($srv['Service_ID'])?></td>
                                    <td><?=htmlspecialchars($srv['Service_Name'])?></td>
                                    <td>RM <?=number_format($srv['Service_Price'], 2)?></td>
                                    <td><?=htmlspecialchars($srv['Available'] ? 'Yes' : 'No')?></td>
                                    <td>
                                        <a href="../edit_services.php?id=<?=htmlspecialchars($srv['Service_ID'])?>" class="btn btn-sm">Edit</a>
                                        <a href="?tab=services&action=delete_service&id=<?=htmlspecialchars($srv['Service_ID'])?>"
                                            onclick="return confirm('Are you sure you want to delete this service?')"
                                            class="btn btn-sm btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($current_tab === 'service_history'): ?>
                <h2>Service Deletion History</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Available</th>
                            <th>Deleted At</th>
                            <th>Deleted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($service_history)): ?>
                            <tr><td colspan="6">No deleted service records found.</td></tr>
                        <?php else: ?>
                            <?php foreach($service_history as $srv): ?>
                                <tr>
                                    <td><?=htmlspecialchars($srv['Service_ID'])?></td>
                                    <td><?=htmlspecialchars($srv['Service_Name'])?></td>
                                    <td>RM <?=number_format($srv['Service_Price'], 2)?></td>
                                    <td><?=htmlspecialchars($srv['Available'] ? 'Yes' : 'No')?></td>
                                    <td><?=htmlspecialchars($srv['deleted_at'])?></td>
                                    <td><?=htmlspecialchars($srv['deleted_by_name'])?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <p>Invalid tab selected.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Proof Modal -->
    <div id="proofModal" class="modal">
        <div class="modal-content proof-modal-content">
            <span class="close" onclick="closeProofModal()">&times;</span>
            <h3>Payment Proof</h3>
            <div id="proofContent"></div>
        </div>
    </div>

    <script>
    function openProofModal(proofUrl) {
        const modal = document.getElementById('proofModal');
        const content = document.getElementById('proofContent');

        // Check if it's an image or PDF
        const fileExtension = proofUrl.split('.').pop().toLowerCase();
        if (fileExtension === 'pdf') {
            content.innerHTML = `<iframe src="${proofUrl}" width="100%" height="600px" style="border: none; display: block; margin: 0 auto;"></iframe>`;
        } else {
            content.innerHTML = `<img src="${proofUrl}" alt="Payment Proof" style="max-width: 100%; max-height: 600px; display: block; margin: 0 auto;">`;
        }

        modal.style.display = 'block';
    }

    function closeProofModal() {
        document.getElementById('proofModal').style.display = 'none';
        document.getElementById('proofContent').innerHTML = '';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('proofModal');
        if (event.target == modal) {
            closeProofModal();
        }
    }
    </script>

    <script src="../theme.js"></script>
</body>
</html>
