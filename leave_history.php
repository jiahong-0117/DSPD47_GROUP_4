<?php
require_once("core.php");
requireLogin();

allowStaffOnly($current_role);

$pageTitle = "Leave History";
renderPageHeader($pageTitle);

$leaveRows = dbSelectAll(
    $conn,
    "SELECT * FROM leave_application WHERE staff_id = ? ORDER BY start_date DESC",
    "i",
    array($current_staff_id)
);
?>

<div class="card">
    <h3>My Leave History</h3>

    <table class="table">
        <tr>
            <th>Leave ID</th>
            <th>Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Decision Date</th>
        </tr>

        <?php if (count($leaveRows) > 0) { ?>
            <?php foreach ($leaveRows as $row) { ?>
                <tr>
                    <td><?php echo e($row["leave_id"]); ?></td>
                    <td><?php echo e($row["leave_type"]); ?></td>
                    <td><?php echo e($row["start_date"]); ?></td>
                    <td><?php echo e($row["end_date"]); ?></td>
                    <td><?php echo e($row["reason"]); ?></td>
                    <td><span class="<?php echo e(getStatusClass($row["status"])); ?>"><?php echo e($row["status"]); ?></span></td>
                    <td><?php echo e($row["decision_date"]); ?></td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="7">No leave history found.</td>
            </tr>
        <?php } ?>
    </table>
</div>

<?php renderPageFooter(); ?>
