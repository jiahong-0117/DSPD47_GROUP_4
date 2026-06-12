<?php
require_once("core.php");
requireLogin();

allowHrOnly($current_role);

$pageTitle = "Manage Staff";
renderPageHeader($pageTitle);

$staffRows = dbSelectAll(
    $conn,
    "SELECT s.staff_id, s.staff_name, s.email, d.department_name, r.role_label, r.role_name,
            sp.staff_name AS superior_name
     FROM staff s
     INNER JOIN department d ON s.department_id = d.department_id
     INNER JOIN `role` r ON s.role_id = r.role_id
     LEFT JOIN staff sp ON s.superior_id = sp.staff_id
     WHERE s.is_active = 1
     ORDER BY s.staff_id ASC"
);
?>

<div class="card">
    <?php if (isset($_GET["delete"])) { ?>
        <?php if ($_GET["delete"] == "self") { ?>
            <div class="alert alert-error">You cannot delete your own account while logged in.</div>
        <?php } else if ($_GET["delete"] == "invalid" || $_GET["delete"] == "notfound") { ?>
            <div class="alert alert-error">Staff record not found.</div>
        <?php } else if ($_GET["delete"] == "failed") { ?>
            <div class="alert alert-error">Staff could not be deleted. Please check the database record and try again.</div>
        <?php } else if ($_GET["delete"] == "success_reassigned") { ?>
            <div class="alert alert-success">Staff deleted successfully. Existing subordinates were reassigned to another superior.</div>
        <?php } else if ($_GET["delete"] == "success") { ?>
            <div class="alert alert-success">Staff deleted successfully. Related leave records and email logs were handled safely.</div>
        <?php } ?>
    <?php } ?>

    <div class="action-row" style="justify-content: space-between; align-items: center;">
        <h3>Staff List</h3>
        <a class="btn" href="add_staff.php">Add Staff</a>
    </div>

    <table class="table">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Role</th>
            <th>Superior</th>
            <th>Action</th>
        </tr>

        <?php if (count($staffRows) > 0) { ?>
            <?php foreach ($staffRows as $row) { ?>
                <tr>
                    <td><?php echo e($row["staff_id"]); ?></td>
                    <td><?php echo e($row["staff_name"]); ?></td>
                    <td><?php echo e($row["email"]); ?></td>
                    <td><?php echo e($row["department_name"]); ?></td>
                    <td><?php echo e($row["role_label"]); ?></td>
                    <td><?php echo e($row["superior_name"] ?: "-"); ?></td>
                    <td>
                        <div class="action-row">
                            <a class="btn btn-secondary" href="update_staff.php?staff_id=<?php echo e($row["staff_id"]); ?>">Edit</a>
                            <form method="post" action="delete_staff.php" onsubmit="return confirmDelete();" style="display:inline; margin:0;">
                                <input type="hidden" name="staff_id" value="<?php echo e($row["staff_id"]); ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="7">No staff found.</td>
            </tr>
        <?php } ?>
    </table>
</div>

<?php renderPageFooter(); ?>
