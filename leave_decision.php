<?php
require_once("core.php");
requireLogin();

allowSuperiorOnly($current_role);

$leave_id = isset($_GET["leave_id"]) ? (int)$_GET["leave_id"] : 0;
$action = $_GET["action"] ?? "";
$error = "";

if ($action != "approve" && $action != "reject") {
    header("Location: leave_list.php?view=date");
    exit();
}

if ($leave_id <= 0 || !canHandleLeave($conn, $leave_id, $current_staff_id, $current_role)) {
    header("Location: leave_list.php?view=date");
    exit();
}

$info = dbSelectOne(
    $conn,
    "SELECT l.*, s.staff_name, d.department_name
     FROM leave_application l
     INNER JOIN staff s ON l.staff_id = s.staff_id
     INNER JOIN department d ON s.department_id = d.department_id
     WHERE l.leave_id = ?
     LIMIT 1",
    "i",
    array($leave_id)
);

if (!$info) {
    header("Location: leave_list.php?view=date");
    exit();
}

if ($info["status"] != "Pending") {
    header("Location: leave_list.php?view=date");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $remark = cleanInput($conn, $_POST["remark"] ?? "");

    if ($action == "approve" && $remark == "") {
        $remark = "Approved";
    }

    if ($action == "reject" && $remark == "") {
        $error = "Please enter a rejection remark.";
    }

    if ($error == "") {
        $newStatus = $action == "approve" ? "Approved" : "Rejected";

        $ok = dbRun(
            $conn,
            "UPDATE leave_application
             SET status = ?, approved_by = ?, decision_date = CURDATE(), superior_remark = ?
             WHERE leave_id = ? AND status = 'Pending'",
            "sisi",
            array($newStatus, $current_staff_id, $remark, $leave_id)
        );

        if ($ok) {
            createEmailLog($conn, $leave_id, $newStatus, $remark);
            header("Location: leave_list.php?view=date");
            exit();
        } else {
            $error = "Failed to update leave application: " . mysqli_error($conn);
        }
    }
}

$isApprove = $action == "approve";
$pageTitle = $isApprove ? "Approve Leave" : "Reject Leave";
renderPageHeader($pageTitle);
?>

<div class="card">
    <h3><?php echo $isApprove ? "Approve Leave Application" : "Reject Leave Application"; ?></h3>

    <?php if ($error != "") { ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php } ?>

    <p><strong>Staff:</strong> <?php echo e($info["staff_name"]); ?></p>
    <p><strong>Department:</strong> <?php echo e($info["department_name"]); ?></p>
    <p><strong>Leave Type:</strong> <?php echo e($info["leave_type"]); ?></p>
    <p><strong>Date:</strong> <?php echo e($info["start_date"] . " to " . $info["end_date"]); ?></p>
    <p><strong>Reason:</strong> <?php echo e($info["reason"]); ?></p>

    <form method="post">
        <div class="form-group">
            <label><?php echo $isApprove ? "Remark" : "Rejection Remark"; ?></label>
            <textarea name="remark" <?php if (!$isApprove) echo "required"; ?>><?php if ($isApprove) echo "Approved"; ?></textarea>
        </div>

        <?php if ($isApprove) { ?>
            <button type="submit" class="btn btn-success">Confirm Approve</button>
        <?php } else { ?>
            <button type="submit" class="btn btn-danger">Confirm Reject</button>
        <?php } ?>

        <a href="leave_list.php?view=date" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php renderPageFooter(); ?>
