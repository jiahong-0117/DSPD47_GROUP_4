<?php
require_once("core.php");
requireLogin();

$totalLeave = 0;
$pendingLeave = 0;
$approvedLeave = 0;

if ($current_role == "staff") {
    $totalRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application WHERE staff_id = ?", "i", array($current_staff_id));
    $pendingRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application WHERE staff_id = ? AND status = 'Pending'", "i", array($current_staff_id));
    $approvedRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application WHERE staff_id = ? AND status = 'Approved'", "i", array($current_staff_id));
} else if ($current_role == "superior") {
    $totalRow = dbSelectOne(
        $conn,
        "SELECT COUNT(*) AS total FROM leave_application l INNER JOIN staff s ON l.staff_id = s.staff_id WHERE s.superior_id = ?",
        "i",
        array($current_staff_id)
    );
    $pendingRow = dbSelectOne(
        $conn,
        "SELECT COUNT(*) AS total FROM leave_application l INNER JOIN staff s ON l.staff_id = s.staff_id WHERE s.superior_id = ? AND l.status = 'Pending'",
        "i",
        array($current_staff_id)
    );
    $approvedRow = dbSelectOne(
        $conn,
        "SELECT COUNT(*) AS total FROM leave_application l INNER JOIN staff s ON l.staff_id = s.staff_id WHERE s.superior_id = ? AND l.status = 'Approved'",
        "i",
        array($current_staff_id)
    );
} else {
    $totalRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application");
    $pendingRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application WHERE status = 'Pending'");
    $approvedRow = dbSelectOne($conn, "SELECT COUNT(*) AS total FROM leave_application WHERE status = 'Approved'");
}

$totalLeave = $totalRow ? $totalRow["total"] : 0;
$pendingLeave = $pendingRow ? $pendingRow["total"] : 0;
$approvedLeave = $approvedRow ? $approvedRow["total"] : 0;

$pageTitle = "Dashboard";
renderPageHeader($pageTitle);
?>

<div class="grid">
    <div class="stat-card">
        <h3>Total Leave Applications</h3>
        <strong><?php echo e($totalLeave); ?></strong>
    </div>

    <div class="stat-card">
        <h3>Pending Applications</h3>
        <strong><?php echo e($pendingLeave); ?></strong>
    </div>

    <div class="stat-card">
        <h3>Approved Applications</h3>
        <strong><?php echo e($approvedLeave); ?></strong>
    </div>
</div>

<div class="card">
    <h3>System Overview</h3>
    <p>
        This <?php echo e($SYSTEM_NAME); ?> uses role-based access control. Staff can apply for leave,
        Superior users can review their own subordinates, HR can maintain staff records, and Admin can control the full system.
    </p>
</div>

<div class="card">
    <h3>Quick Actions</h3>
    <div class="action-row">
        <?php if ($current_role == "staff" || $current_role == "admin") { ?>
            <a class="btn" href="apply_leave.php">Apply Leave</a>
            <a class="btn btn-secondary" href="leave_status.php">Check Status</a>
        <?php } ?>

        <?php if ($current_role == "superior" || $current_role == "admin") { ?>
            <a class="btn" href="leave_list.php?view=date">Review by Date</a>
            <a class="btn btn-secondary" href="leave_list.php?view=staff">Review by Staff</a>
        <?php } ?>

        <?php if ($current_role == "hr" || $current_role == "admin") { ?>
            <a class="btn" href="staff_list.php">Staff List</a>
            <a class="btn btn-secondary" href="add_staff.php">Add Staff</a>
        <?php } ?>
    </div>
</div>

<?php renderPageFooter(); ?>
