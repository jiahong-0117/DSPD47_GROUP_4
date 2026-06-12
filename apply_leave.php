<?php
require_once("core.php");
requireLogin();

allowStaffOnly($current_role);

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $leave_type = cleanInput($conn, $_POST["leave_type"] ?? "");
    $start_date = cleanInput($conn, $_POST["start_date"] ?? "");
    $end_date = cleanInput($conn, $_POST["end_date"] ?? "");
    $reason = cleanInput($conn, $_POST["reason"] ?? "");

    if ($leave_type == "" || $start_date == "" || $end_date == "" || $reason == "") {
        $error = "Please fill in all fields.";
    } else if (!isValidLeaveType($leave_type)) {
        $error = "Invalid leave type selected.";
    } else if (!isValidDate($start_date) || !isValidDate($end_date)) {
        $error = "Please select valid leave dates.";
    } else if ($start_date > $end_date) {
        $error = "Start date cannot be later than end date.";
    } else {
        $ok = dbRun(
            $conn,
            "INSERT INTO leave_application (staff_id, leave_type, start_date, end_date, reason, status, apply_date)
             VALUES (?, ?, ?, ?, ?, 'Pending', CURDATE())",
            "issss",
            array($current_staff_id, $leave_type, $start_date, $end_date, $reason)
        );

        if ($ok) {
            $message = "Leave application submitted successfully.";
        } else {
            $error = "Failed to submit leave application: " . mysqli_error($conn);
        }
    }
}

$pageTitle = "Apply Leave";
renderPageHeader($pageTitle);
?>

<div class="card">
    <h3>Leave Application Form</h3>

    <?php if ($message != "") { ?>
        <div class="alert alert-success"><?php echo e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php } ?>

    <form method="post" onsubmit="return validateLeaveForm();">
        <div class="form-group">
            <label>Leave Type</label>
            <select name="leave_type" id="leave_type" required>
                <option value="">Select leave type</option>
                <option value="Annual Leave">Annual Leave</option>
                <option value="Medical Leave">Medical Leave</option>
                <option value="Emergency Leave">Emergency Leave</option>
                <option value="Unpaid Leave">Unpaid Leave</option>
            </select>
        </div>

        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" id="start_date" required>
        </div>

        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" id="end_date" required>
        </div>

        <div class="form-group">
            <label>Reason</label>
            <textarea name="reason" id="reason" placeholder="Enter your leave reason" required></textarea>
        </div>

        <button type="submit" class="btn">Submit Application</button>
    </form>
</div>

<?php renderPageFooter(); ?>
