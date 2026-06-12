<?php
require_once("core.php");
requireLogin();

allowStaffOnly($current_role);

$pageTitle = "Leave Approval Status";
renderPageHeader($pageTitle);

$leaveRows = dbSelectAll(
    $conn,
    "SELECT l.*, approver.staff_name AS approved_by_name
     FROM leave_application l
     LEFT JOIN staff approver ON l.approved_by = approver.staff_id
     WHERE l.staff_id = ?
     ORDER BY l.leave_id DESC",
    "i",
    array($current_staff_id)
);
?>

<div class="card">
    <h3>My Leave Status</h3>

    <table class="table">
        <tr>
            <th>Leave ID</th>
            <th>Leave Type</th>
            <th>Date</th>
            <th>Status</th>
            <th>Apply Date</th>
            <th>Approved By</th>
            <th>Remark</th>
        </tr>

        <?php if (count($leaveRows) > 0) { ?>
            <?php foreach ($leaveRows as $row) { ?>
                <tr>
                    <td><?php echo e($row["leave_id"]); ?></td>
                    <td><?php echo e($row["leave_type"]); ?></td>
                    <td><?php echo e($row["start_date"] . " to " . $row["end_date"]); ?></td>
                    <td><span class="<?php echo e(getStatusClass($row["status"])); ?>"><?php echo e($row["status"]); ?></span></td>
                    <td><?php echo e($row["apply_date"]); ?></td>
                    <td><?php echo e($row["approved_by_name"] ?: "-"); ?></td>
                    <td><?php echo e($row["superior_remark"]); ?></td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="7">No leave application found.</td>
            </tr>
        <?php } ?>
    </table>
</div>

<?php renderPageFooter(); ?>
