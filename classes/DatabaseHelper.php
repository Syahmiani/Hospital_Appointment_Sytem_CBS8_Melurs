<?php
/**
 * Database Helper Class
 * Provides reusable database query functions
 */

class DatabaseHelper {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get all active doctors with optional specialization filter
     */
    public function getDoctors($specialization = null) {
        $query = "SELECT d.doctor_id, u.f_name, u.l_name, d.specialization
                  FROM doctor d
                  JOIN `user` u ON d.user_id = u.user_id
                  WHERE (d.is_deleted = 0 OR d.is_deleted IS NULL)";

        if ($specialization) {
            $query .= " AND d.specialization = ?";
        }

        $query .= " ORDER BY u.f_name";

        $stmt = mysqli_prepare($this->conn, $query);

        if ($specialization) {
            mysqli_stmt_bind_param($stmt, 's', $specialization);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($this->conn, $query);
        }

        $doctors = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $doctors[] = $row;
            }
        }

        return $doctors;
    }

    /**
     * Get distinct specializations
     */
    public function getSpecializations() {
        $query = "SELECT DISTINCT specialization FROM doctor
                  WHERE (is_deleted = 0 OR is_deleted IS NULL) AND specialization IS NOT NULL
                  ORDER BY specialization";

        $result = mysqli_query($this->conn, $query);
        $specializations = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $specializations[] = $row['specialization'];
            }
        }

        return $specializations;
    }

    /**
     * Get active patients with optional name filter
     */
    public function getPatients($name_filter = null) {
        $query = "SELECT p.patient_id, u.f_name, u.l_name, u.email, u.phone
                  FROM patient p
                  JOIN `user` u ON p.user_id = u.user_id
                  WHERE (p.is_deleted = 0 OR p.is_deleted IS NULL)";

        if ($name_filter) {
            $query .= " AND CONCAT(u.f_name, ' ', u.l_name) = ?";
        }

        $query .= " ORDER BY u.f_name";

        $stmt = mysqli_prepare($this->conn, $query);

        if ($name_filter) {
            mysqli_stmt_bind_param($stmt, 's', $name_filter);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($this->conn, $query);
        }

        $patients = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $patients[] = $row;
            }
        }

        return $patients;
    }

    /**
     * Get distinct patient names for filter dropdown
     */
    public function getPatientNames() {
        $query = "SELECT DISTINCT CONCAT(u.f_name, ' ', u.l_name) as full_name
                  FROM patient p
                  JOIN `user` u ON p.user_id = u.user_id
                  WHERE p.is_deleted = 0 OR p.is_deleted IS NULL
                  ORDER BY u.f_name";

        $result = mysqli_query($this->conn, $query);
        $names = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $names[] = $row['full_name'];
            }
        }

        return $names;
    }

    /**
     * Get appointments with filters
     */
    public function getAppointments($status_filter = null, $limit = null, $offset = null) {
        $query = "SELECT
                    a.App_ID, a.App_Date, a.App_Time, a.symptom, a.status, a.payment_status, a.payment_proof, a.payment_time,
                    up.f_name AS patient_fname, up.l_name AS patient_lname,
                    ud.f_name AS doctor_fname, ud.l_name AS doctor_lname,
                    s.Service_Price
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
                  JOIN
                    services s ON a.Service_ID = s.Service_ID
                  WHERE (a.is_deleted = 0 OR a.is_deleted IS NULL)";

        if ($status_filter) {
            $query .= " AND a.status = ?";
        }

        $query .= " ORDER BY a.App_Date ASC, a.App_Time ASC";

        if ($limit) {
            $query .= " LIMIT ?";
            if ($offset) {
                $query .= " OFFSET ?";
            }
        }

        $stmt = mysqli_prepare($this->conn, $query);

        if ($status_filter && $limit && $offset) {
            mysqli_stmt_bind_param($stmt, 'sii', $status_filter, $limit, $offset);
        } elseif ($status_filter && $limit) {
            mysqli_stmt_bind_param($stmt, 'si', $status_filter, $limit);
        } elseif ($status_filter) {
            mysqli_stmt_bind_param($stmt, 's', $status_filter);
        } elseif ($limit && $offset) {
            mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
        } elseif ($limit) {
            mysqli_stmt_bind_param($stmt, 'i', $limit);
        }

        if ($status_filter || $limit) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($this->conn, $query);
        }

        $appointments = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $appointments[] = $row;
            }
        }

        return $appointments;
    }

    /**
     * Get appointment statuses for filter dropdown
     */
    public function getAppointmentStatuses() {
        $query = "SELECT DISTINCT status FROM appointment
                  WHERE (is_deleted = 0 OR is_deleted IS NULL) AND status IS NOT NULL
                  ORDER BY status";

        $result = mysqli_query($this->conn, $query);
        $statuses = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $statuses[] = $row['status'];
            }
        }

        return $statuses;
    }

    /**
     * Get statistics for dashboard
     */
    public function getStats() {
        $stats = [];

        $queries = [
            'doctors' => "SELECT COUNT(*) AS total FROM doctor WHERE is_deleted = 0 OR is_deleted IS NULL",
            'patients' => "SELECT COUNT(*) AS total FROM patient WHERE is_deleted = 0 OR is_deleted IS NULL",
            'appointments' => "SELECT COUNT(*) AS total FROM appointment WHERE is_deleted = 0 OR is_deleted IS NULL"
        ];

        foreach ($queries as $key => $query) {
            $result = mysqli_query($this->conn, $query);
            if ($result) {
                $stats[$key] = mysqli_fetch_assoc($result)['total'];
            } else {
                $stats[$key] = 0;
            }
        }

        return $stats;
    }

    /**
     * Soft delete record with logging
     */
    public function softDelete($table, $id_column, $id_value, $user_id) {
        $query = "UPDATE $table SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE $id_column = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $id_value);

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success;
    }

    /**
     * Get paginated results with total count
     */
    public function getPaginatedResults($query, $count_query, $params = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;

        // Get total count
        $stmt = mysqli_prepare($this->conn, $count_query);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total = mysqli_fetch_assoc($result)['total'];
        mysqli_stmt_close($stmt);

        // Get paginated data
        $query .= " LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($this->conn, $query);
        $params[] = $per_page;
        $params[] = $offset;
        $types = str_repeat('s', count($params) - 2) . 'ii';
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }
}
?>
