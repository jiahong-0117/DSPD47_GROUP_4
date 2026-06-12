<?php
require_once("core.php");
requireLogin();

$staff_id = isset($_GET["staff_id"]) ? (int)$_GET["staff_id"] : $current_staff_id;
$error = "";
$message = "";
$roles = getAllRoles($conn);
$departments = getAllDepartments($conn);

if ($current_role != "hr" && $current_role != "admin" && $staff_id != $current_staff_id) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_name = cleanInput($conn, $_POST["staff_name"] ?? "");
    $email = cleanInput($conn, $_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($staff_name == "" || $email == "") {
        $error = "Please fill in all required fields.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else if (emailExists($conn, $email, $staff_id)) {
        $error = "This email is already used by another staff.";
    } else if ($current_role == "hr" || $current_role == "admin") {
        $department_id = (int)($_POST["department_id"] ?? 0);
        $role_id = (int)($_POST["role_id"] ?? 0);
        $superior_id = (int)($_POST["superior_id"] ?? 0);

        $roleRow = getRoleById($conn, $role_id);
        $departmentRow = getDepartmentById($conn, $department_id);
        $role = $roleRow ? $roleRow["role_name"] : "";

        if ($department_id <= 0 || $role_id <= 0) {
            $error = "Please fill in all required fields.";
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
            } else if (!validSuperiorId($conn, $superior_id, $staff_id)) {
                $error = "Invalid superior selected.";
            } else if (hasSubordinates($conn, $staff_id) && !roleCanHaveSubordinates($role)) {
                $error = "This account currently has subordinates, so the role must remain superior or admin until the subordinates are reassigned.";
            } else {
                $superiorValue = $superior_id > 0 ? $superior_id : null;

                if ($password != "") {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $sqlUpdate = "UPDATE staff
                                  SET staff_name = ?, email = ?, password = ?, department_id = ?, role_id = ?, superior_id = ?, updated_at = NOW()
                                  WHERE staff_id = ?";
                    $types = "sssiiii";
                    $params = array($staff_name, $email, $passwordHash, $department_id, $role_id, $superiorValue, $staff_id);
                } else {
                    $sqlUpdate = "UPDATE staff
                                  SET staff_name = ?, email = ?, department_id = ?, role_id = ?, superior_id = ?, updated_at = NOW()
                                  WHERE staff_id = ?";
                    $types = "ssiiii";
                    $params = array($staff_name, $email, $department_id, $role_id, $superiorValue, $staff_id);
                }
            }
        }
    } else {
        if ($password != "") {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sqlUpdate = "UPDATE staff SET staff_name = ?, email = ?, password = ?, updated_at = NOW() WHERE staff_id = ?";
            $types = "sssi";
            $params = array($staff_name, $email, $passwordHash, $staff_id);
        } else {
            $sqlUpdate = "UPDATE staff SET staff_name = ?, email = ?, updated_at = NOW() WHERE staff_id = ?";
            $types = "ssi";
            $params = array($staff_name, $email, $staff_id);
        }
    }

    if ($error == "" && isset($sqlUpdate)) {
        if (dbRun($conn, $sqlUpdate, $types, $params)) {
            if (($current_role == "hr" || $current_role == "admin") && isset($role)) {
                syncRoleTables($conn, $staff_id, $role, $department_id);
            }

            $message = "Staff information updated successfully.";

            if ($staff_id == $current_staff_id) {
                refreshCurrentUserSession($conn);
            }
        } else {
            $error = "Failed to update staff: " . mysqli_error($conn);
        }
    }
}

$staff = dbSelectOne(
    $conn,
    "SELECT s.*, r.role_name, r.role_label, d.department_name
     FROM staff s
     INNER JOIN `role` r ON s.role_id = r.role_id
     INNER JOIN department d ON s.department_id = d.department_id
     WHERE s.staff_id = ?
     LIMIT 1",
    "i",
    array($staff_id)
);

if (!$staff) {
    header("Location: staff_list.php");
    exit();
}

$pageTitle = "Update Staff Information";
renderPageHeader($pageTitle);
?>

<div class="card">
    <h3>Update Staff Information</h3>

    <?php if ($message != "") { ?>
        <div class="alert alert-success"><?php echo e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php } ?>

    <form method="post" onsubmit="return validateStaffForm();">
        <div class="form-group">
            <label>Staff Name</label>
            <input type="text" name="staff_name" value="<?php echo e($staff["staff_name"]); ?>" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo e($staff["email"]); ?>" required>
        </div>

        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" placeholder="Leave blank to keep current password">
            <p class="help-text">For security, the existing password is not displayed. Enter a new password only when you want to change it.</p>
        </div>

        <?php if ($current_role == "hr" || $current_role == "admin") { ?>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id" required>
                    <?php foreach ($departments as $department) { ?>
                        <option value="<?php echo e($department["department_id"]); ?>" <?php if ((int)$staff["department_id"] == (int)$department["department_id"]) echo "selected"; ?>>
                            <?php echo e($department["department_name"]); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role_id" required>
                    <?php foreach ($roles as $roleRow) { ?>
                        <option value="<?php echo e($roleRow["role_id"]); ?>" data-role="<?php echo e($roleRow["role_name"]); ?>" <?php if ((int)$staff["role_id"] == (int)$roleRow["role_id"]) echo "selected"; ?>>
                            <?php echo e($roleRow["role_label"]); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="help-text">Changing this role immediately changes what this user can see and do after login.</p>
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
                         WHERE r.role_name IN ('superior', 'admin')
                         AND s.is_active = 1
                         AND s.staff_id <> ?
                         ORDER BY s.staff_name ASC",
                        "i",
                        array($staff_id)
                    );
                    ?>
                    <?php foreach ($superiors as $row) { ?>
                        <option value="<?php echo e($row["staff_id"]); ?>" <?php if ((int)$staff["superior_id"] == (int)$row["staff_id"]) echo "selected"; ?>>
                            <?php echo e($row["staff_name"] . " - " . $row["role_label"] . " / " . $row["department_name"]); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="help-text">Required when the selected role is Staff, because every staff member must have one superior.</p>
            </div>
        <?php } ?>

        <button type="submit" class="btn">Update Staff</button>
        <a href="staff_list.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php renderPageFooter(); ?>
