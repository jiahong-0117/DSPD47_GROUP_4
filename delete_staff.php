<?php
require_once("core.php");
requireLogin();

allowHrOnly($current_role);

$staff_id = 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_id = isset($_POST["staff_id"]) ? (int)$_POST["staff_id"] : 0;
} else {
    $staff_id = isset($_GET["staff_id"]) ? (int)$_GET["staff_id"] : 0;
}

if ($staff_id <= 0) {
    header("Location: staff_list.php?delete=invalid");
    exit();
}

if ($staff_id == $current_staff_id) {
    header("Location: staff_list.php?delete=self");
    exit();
}

$staff = dbSelectOne(
    $conn,
    "SELECT s.staff_id, s.staff_name, s.email, r.role_name
     FROM staff s
     INNER JOIN `role` r ON s.role_id = r.role_id
     WHERE s.staff_id = ?
     LIMIT 1",
    "i",
    array($staff_id)
);

if (!$staff) {
    header("Location: staff_list.php?delete=notfound");
    exit();
}

/*
    Safe delete logic:
    1. Reassign subordinates if the deleted staff is a superior/admin.
    2. Delete email logs related to the deleted staff's own leave records.
    3. Clear approval references if this staff approved/rejected other leave records.
    4. Delete this staff's own leave records.
    5. Delete the staff account. Role tables use ON DELETE CASCADE.
*/

mysqli_begin_transaction($conn);
$success = true;
$reassigned = false;

$subRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM staff WHERE superior_id = ?", "i", array($staff_id));
$subCount = $subRow ? (int)$subRow["total"] : 0;

if ($subCount > 0) {
    $replacement = dbSelectOne(
        $conn,
        "SELECT s.staff_id
         FROM staff s
         INNER JOIN `role` r ON s.role_id = r.role_id
         WHERE s.staff_id <> ?
         AND s.is_active = 1
         AND r.role_name IN ('superior', 'admin')
         ORDER BY FIELD(r.role_name, 'superior', 'admin'), s.staff_id ASC
         LIMIT 1",
        "i",
        array($staff_id)
    );

    if ($replacement) {
        $replacement_id = (int)$replacement["staff_id"];
        $success = dbRun($conn, "UPDATE staff SET superior_id = ?, updated_at = NOW() WHERE superior_id = ?", "ii", array($replacement_id, $staff_id));
        $reassigned = true;
    } else {
        $success = false;
    }
}

if ($success) {
    $success = dbRun(
        $conn,
        "DELETE e FROM email_log e
         INNER JOIN leave_application l ON e.leave_id = l.leave_id
         WHERE l.staff_id = ?",
        "i",
        array($staff_id)
    );
}

if ($success) {
    $success = dbRun($conn, "UPDATE leave_application SET approved_by = NULL WHERE approved_by = ?", "i", array($staff_id));
}

if ($success) {
    $success = dbRun($conn, "DELETE FROM leave_application WHERE staff_id = ?", "i", array($staff_id));
}

if ($success) {
    $success = dbRun($conn, "DELETE FROM staff WHERE staff_id = ?", "i", array($staff_id));
}

if ($success) {
    mysqli_commit($conn);

    if ($reassigned) {
        header("Location: staff_list.php?delete=success_reassigned");
    } else {
        header("Location: staff_list.php?delete=success");
    }
    exit();
}

mysqli_rollback($conn);
header("Location: staff_list.php?delete=failed");
exit();
?>
