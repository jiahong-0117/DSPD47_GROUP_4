<?php
require_once("core.php");
requireLogin();

allowHrOnly($current_role);

$message = "";
$error = "";
$roles = getAllRoles($conn);
$departments = getAllDepartments($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_name = cleanInput($conn, $_POST["staff_name"] ?? "");
    $email = cleanInput($conn, $_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $department_id = (int)($_POST["department_id"] ?? 0);
    $role_id = (int)($_POST["role_id"] ?? 0);
    $superior_id = (int)($_POST["superior_id"] ?? 0);

    $roleRow = getRoleById($conn, $role_id);
    $departmentRow = getDepartmentById($conn, $department_id);
    $role = $roleRow ? $roleRow["role_name"] : "";

    if ($staff_name == "" || $email == "" || $password == "" || $department_id <= 0 || $role_id <= 0) {
        $error = "Please fill in all required fields.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else if (!$roleRow || !isValidRoleName($role)) {
        $error = "Invalid role selected.";
    } else if (!$departmentRow) {
        $error = "Invalid department selected.";
    } else {
        if (!staffRoleNeedsSuperior($role)) {
            $superior_id = 0;
        }

        if (!validateSuperiorRequirement($role, $superior_id)) {
            $error = "Staff role must have one superior. Please select a superior.";
        } else if (!validSuperiorId($conn, $superior_id)) {
            $error = "Invalid superior selected.";
        } else if (emailExists($conn, $email)) {
            $error = "This email is already used by another staff.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $superiorValue = $superior_id > 0 ? $superior_id : null;

            $ok = dbRun(
                $conn,
                "INSERT INTO staff (staff_name, email, password, department_id, role_id, superior_id, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())",
                "sssiii",
                array($staff_name, $email, $passwordHash, $department_id, $role_id, $superiorValue)
            );

            if ($ok) {
                $new_staff_id = mysqli_insert_id($conn);
                syncRoleTables($conn, $new_staff_id, $role, $department_id);
                $message = "Staff added successfully.";
            } else {
                $error = "Failed to add staff: " . mysqli_error($conn);
            }
        }
    }
}

$pageTitle = "Add Staff";
renderPageHeader($pageTitle);
?>

<div class="card">
    <h3>Add Staff</h3>

    <?php if ($message != "") { ?>
        <div class="alert alert-success"><?php echo e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php } ?>

    <form method="post" onsubmit="return validateStaffForm();">
        <div class="form-group">
            <label>Staff Name</label>
            <input type="text" name="staff_name" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Department</label>
            <select name="department_id" required>
                <option value="0">Select department</option>
                <?php foreach ($departments as $department) { ?>
                    <option value="<?php echo e($department["department_id"]); ?>"><?php echo e($department["department_name"]); ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role_id" required>
                <?php foreach ($roles as $roleRow) { ?>
                    <option value="<?php echo e($roleRow["role_id"]); ?>" data-role="<?php echo e($roleRow["role_name"]); ?>">
                        <?php echo e($roleRow["role_label"]); ?>
                    </option>
                <?php } ?>
            </select>
            <p class="help-text">Role controls which sidebar modules and actions the account can access.</p>
        </div>

        <div class="form-group">
            <label>Superior</label>
            <select name="superior_id">
                <option value="0">No superior / Not required for admin, HR, or superior</option>
                <?php
                $superiors = dbSelectAll(
                    $conn,
                    "SELECT s.staff_id, s.staff_name, d.department_name, r.role_label
                     FROM staff s
                     INNER JOIN `role` r ON s.role_id = r.role_id
                     INNER JOIN department d ON s.department_id = d.department_id
                     WHERE r.role_name IN ('superior', 'admin') AND s.is_active = 1
                     ORDER BY s.staff_name ASC"
                );
                ?>
                <?php foreach ($superiors as $row) { ?>
                    <option value="<?php echo e($row["staff_id"]); ?>"><?php echo e($row["staff_name"] . " - " . $row["role_label"] . " / " . $row["department_name"]); ?></option>
                <?php } ?>
            </select>
            <p class="help-text">Required when the selected role is Staff, because every staff member must have one superior.</p>
        </div>

        <button type="submit" class="btn">Add Staff</button>
    </form>
</div>

<?php renderPageFooter(); ?>
