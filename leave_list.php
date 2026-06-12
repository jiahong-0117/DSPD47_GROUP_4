<?php
require_once("core.php");
requireLogin();

allowSuperiorOnly($current_role);

$view = $_GET["view"] ?? "date";
if ($view != "date" && $view != "staff") {
    $view = "date";
}

$from_date = isset($_GET["from_date"]) ? cleanInput($conn, $_GET["from_date"]) : "";
$to_date = isset($_GET["to_date"]) ? cleanInput($conn, $_GET["to_date"]) : "";
$selected_staff_id = isset($_GET["staff_id"]) ? (int)$_GET["staff_id"] : 0;
$error = "";
$leaveRows = array();

if ($view == "date") {
    if ($from_date != "" && !isValidDate($from_date)) {
        $error = "Invalid from date.";
    }

    if ($to_date != "" && !isValidDate($to_date)) {
        $error = "Invalid to date.";
    }

    if ($from_date != "" && $to_date != "" && $from_date > $to_date) {
        $error = "From date cannot be later than to date.";
    }

    if ($error == "") {
        $sql = "SELECT l.*, s.staff_name, d.department_name
                FROM leave_application l
                INNER JOIN staff s ON l.staff_id = s.staff_id
                INNER JOIN department d ON s.department_id = d.department_id
                WHERE 1 = 1";
        $types = "";
        $params = array();

        if ($current_role == "superior") {
            $sql .= " AND s.superior_id = ?";
            $types .= "i";
            $params[] = $current_staff_id;
        }

        if ($from_date != "") {
            $sql .= " AND l.start_date >= ?";
            $types .= "s";
            $params[] = $from_date;
        }

        if ($to_date != "") {
            $sql .= " AND l.end_date <= ?";
            $types .= "s";
            $params[] = $to_date;
        }

        $sql .= " ORDER BY l.leave_id DESC";
        $leaveRows = dbSelectAll($conn, $sql, $types, $params);
    }
} else {
    if ($selected_staff_id > 0) {
        $sql = "SELECT l.*, s.staff_name, d.department_name
                FROM leave_application l
                INNER JOIN staff s ON l.staff_id = s.staff_id
                INNER JOIN department d ON s.department_id = d.department_id
                WHERE l.staff_id = ?";
        $types = "i";
        $params = array($selected_staff_id);

        if ($current_role == "superior") {
            $sql .= " AND s.superior_id = ?";
            $types .= "i";
            $params[] = $current_staff_id;
        }

        $sql .= " ORDER BY l.leave_id DESC";
        $leaveRows = dbSelectAll($conn, $sql, $types, $params);
    }
}

$pageTitle = $view == "staff" ? "Leave Applications by Staff" : "Leave Applications by Date";
renderPageHeader($pageTitle);
?>

<div class="card">
    <h3>Search Leave Applications</h3>

    <div class="action-row" style="margin-bottom: 16px;">
        <a class="btn <?php if ($view == 'date') echo 'btn-secondary'; ?>" href="leave_list.php?view=date">Search by Date</a>
        <a class="btn <?php if ($view == 'staff') echo 'btn-secondary'; ?>" href="leave_list.php?view=staff">Search by Staff</a>
    </div>

    <?php if ($error != "") { ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php } ?>

    <?php if ($view == "date") { ?>
        <form method="get">
            <input type="hidden" name="view" value="date">

            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="<?php echo e($from_date); ?>">
            </div>

            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="to_date" value="<?php echo e($to_date); ?>">
            </div>

            <button type="submit" class="btn">Search</button>
        </form>
    <?php } else { ?>
        <form method="get">
            <input type="hidden" name="view" value="staff">

            <div class="form-group">
                <label>Staff</label>
                <select name="staff_id">
                    <option value="0">Select staff</option>
                    <?php
                    if ($current_role == "admin") {
                        $staffOptions = dbSelectAll(
                            $conn,
                            "SELECT s.staff_id, s.staff_name, d.department_name
                             FROM staff s
                             INNER JOIN `role` r ON s.role_id = r.role_id
                             INNER JOIN department d ON s.department_id = d.department_id
                             WHERE r.role_name = 'staff'
                             AND s.is_active = 1
                             ORDER BY s.staff_name ASC"
                        );
                    } else {
                        $staffOptions = dbSelectAll(
                            $conn,
                            "SELECT s.staff_id, s.staff_name, d.department_name
                             FROM staff s
                             INNER JOIN department d ON s.department_id = d.department_id
                             WHERE s.superior_id = ?
                             AND s.is_active = 1
                             ORDER BY s.staff_name ASC",
                            "i",
                            array($current_staff_id)
                        );
                    }
                    ?>

                    <?php foreach ($staffOptions as $staffRow) { ?>
                        <option value="<?php echo e($staffRow["staff_id"]); ?>" <?php if ($selected_staff_id == $staffRow["staff_id"]) echo "selected"; ?>>
                            <?php echo e($staffRow["staff_name"] . " / " . $staffRow["department_name"]); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <button type="submit" class="btn">Search</button>
        </form>
    <?php } ?>
</div>

<div class="card">
    <h3>Leave Application List</h3>

    <table class="table">
        <tr>
            <th>Leave ID</th>
            <th>Staff Name</th>
            <th>Department</th>
            <th>Leave Type</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php if (count($leaveRows) > 0) { ?>
            <?php foreach ($leaveRows as $row) { ?>
                <tr>
                    <td><?php echo e($row["leave_id"]); ?></td>
                    <td><?php echo e($row["staff_name"]); ?></td>
                    <td><?php echo e($row["department_name"]); ?></td>
                    <td><?php echo e($row["leave_type"]); ?></td>
                    <td><?php echo e($row["start_date"] . " to " . $row["end_date"]); ?></td>
                    <td><span class="<?php echo e(getStatusClass($row["status"])); ?>"><?php echo e($row["status"]); ?></span></td>
                    <td>
                        <?php if ($row["status"] == "Pending") { ?>
                            <div class="action-row">
                                <a class="btn btn-success" href="leave_decision.php?action=approve&leave_id=<?php echo e($row["leave_id"]); ?>">Approve</a>
                                <a class="btn btn-danger" href="leave_decision.php?action=reject&leave_id=<?php echo e($row["leave_id"]); ?>">Reject</a>
                            </div>
                        <?php } else { ?>
                            Completed
                        <?php } ?>
                    </td>
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
