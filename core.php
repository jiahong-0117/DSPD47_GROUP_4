<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "las_db";

$COMPANY_NAME = "NVDIA";
$SYSTEM_NAME = $COMPANY_NAME . " Staff Leave Application System";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

function bindPreparedParams($stmt, $types, $params) {
    if ($types === "" || count($params) === 0) {
        return true;
    }

    $refs = array();
    $refs[] = $types;

    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }

    return call_user_func_array(array($stmt, "bind_param"), $refs);
}

function dbExecute($conn, $sql, $types = "", $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    if (!bindPreparedParams($stmt, $types, $params)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    return $stmt;
}

function dbSelectOne($conn, $sql, $types = "", $params = array()) {
    $stmt = dbExecute($conn, $sql, $types, $params);

    if (!$stmt) {
        return null;
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row;
}

function dbSelectAll($conn, $sql, $types = "", $params = array()) {
    $stmt = dbExecute($conn, $sql, $types, $params);

    if (!$stmt) {
        return array();
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = array();

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function dbRun($conn, $sql, $types = "", $params = array()) {
    $stmt = dbExecute($conn, $sql, $types, $params);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_close($stmt);
    return true;
}

function cleanInput($conn, $value) {
    return trim($value ?? "");
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function passwordLooksHashed($password) {
    $info = password_get_info($password);
    return isset($info["algoName"]) && $info["algoName"] !== "unknown";
}

function verifyLoginPassword($plainPassword, $storedPassword) {
    if (passwordLooksHashed($storedPassword)) {
        return password_verify($plainPassword, $storedPassword);
    }

    return hash_equals((string)$storedPassword, (string)$plainPassword);
}

function savePasswordHashIfNeeded($conn, $staff_id, $plainPassword, $storedPassword) {
    $staff_id = (int)$staff_id;

    if (!passwordLooksHashed($storedPassword) || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        dbRun($conn, "UPDATE staff SET password = ? WHERE staff_id = ?", "si", array($newHash, $staff_id));
    }
}

function refreshCurrentUserSession($conn) {
    if (!isset($_SESSION["staff_id"])) {
        return;
    }

    $staff_id = (int)$_SESSION["staff_id"];
    $row = dbSelectOne(
        $conn,
        "SELECT s.staff_id, s.staff_name, s.email, s.department_id, d.department_name, r.role_id, r.role_name
         FROM staff s
         INNER JOIN `role` r ON s.role_id = r.role_id
         INNER JOIN department d ON s.department_id = d.department_id
         WHERE s.staff_id = ? AND s.is_active = 1
         LIMIT 1",
        "i",
        array($staff_id)
    );

    if (!$row) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $_SESSION["staff_id"] = (int)$row["staff_id"];
    $_SESSION["staff_name"] = $row["staff_name"];
    $_SESSION["email"] = $row["email"];
    $_SESSION["role_id"] = (int)$row["role_id"];
    $_SESSION["role"] = $row["role_name"];
    $_SESSION["department_id"] = (int)$row["department_id"];
    $_SESSION["department_name"] = $row["department_name"];
}

refreshCurrentUserSession($conn);

$current_staff_id = isset($_SESSION["staff_id"]) ? (int)$_SESSION["staff_id"] : 0;
$current_staff_name = $_SESSION["staff_name"] ?? "";
$current_email = $_SESSION["email"] ?? "";
$current_role_id = isset($_SESSION["role_id"]) ? (int)$_SESSION["role_id"] : 0;
$current_role = $_SESSION["role"] ?? "";
$current_department_id = isset($_SESSION["department_id"]) ? (int)$_SESSION["department_id"] : 0;
$current_department_name = $_SESSION["department_name"] ?? "";

function requireLogin() {
    if (!isset($_SESSION["staff_id"])) {
        header("Location: login.php");
        exit();
    }
}

function isValidRoleName($role) {
    return in_array($role, array("staff", "superior", "hr", "admin"), true);
}

function isValidLeaveType($leave_type) {
    return in_array($leave_type, array("Annual Leave", "Medical Leave", "Emergency Leave", "Unpaid Leave"), true);
}

function isValidDate($date) {
    if ($date == "") {
        return false;
    }

    $parts = explode("-", $date);
    if (count($parts) != 3) {
        return false;
    }

    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function getStatusClass($status) {
    if ($status == "Approved") {
        return "badge badge-approved";
    }

    if ($status == "Rejected") {
        return "badge badge-rejected";
    }

    return "badge badge-pending";
}

function allowStaffOnly($role) {
    if ($role != "staff" && $role != "admin") {
        header("Location: dashboard.php");
        exit();
    }
}

function allowSuperiorOnly($role) {
    if ($role != "superior" && $role != "admin") {
        header("Location: dashboard.php");
        exit();
    }
}

function allowHrOnly($role) {
    if ($role != "hr" && $role != "admin") {
        header("Location: dashboard.php");
        exit();
    }
}

function getRoleById($conn, $role_id) {
    return dbSelectOne($conn, "SELECT * FROM `role` WHERE role_id = ? LIMIT 1", "i", array((int)$role_id));
}

function getDepartmentById($conn, $department_id) {
    return dbSelectOne($conn, "SELECT * FROM department WHERE department_id = ? LIMIT 1", "i", array((int)$department_id));
}

function getAllRoles($conn) {
    return dbSelectAll($conn, "SELECT * FROM `role` ORDER BY role_id ASC");
}

function getAllDepartments($conn) {
    return dbSelectAll($conn, "SELECT * FROM department ORDER BY department_name ASC");
}

function emailExists($conn, $email, $exclude_staff_id = 0) {
    $exclude_staff_id = (int)$exclude_staff_id;

    if ($exclude_staff_id > 0) {
        $row = dbSelectOne($conn, "SELECT staff_id FROM staff WHERE email = ? AND staff_id <> ? LIMIT 1", "si", array($email, $exclude_staff_id));
    } else {
        $row = dbSelectOne($conn, "SELECT staff_id FROM staff WHERE email = ? LIMIT 1", "s", array($email));
    }

    return $row ? true : false;
}

function validSuperiorId($conn, $superior_id, $current_staff_id = 0) {
    $superior_id = (int)$superior_id;
    $current_staff_id = (int)$current_staff_id;

    if ($superior_id == 0) {
        return true;
    }

    if ($current_staff_id > 0 && $superior_id == $current_staff_id) {
        return false;
    }

    $row = dbSelectOne(
        $conn,
        "SELECT s.staff_id
         FROM staff s
         INNER JOIN `role` r ON s.role_id = r.role_id
         WHERE s.staff_id = ?
         AND s.is_active = 1
         AND r.role_name IN ('superior', 'admin')
         LIMIT 1",
        "i",
        array($superior_id)
    );

    return $row ? true : false;
}

function staffRoleNeedsSuperior($role) {
    return $role == "staff";
}

function validateSuperiorRequirement($role, $superior_id) {
    if (staffRoleNeedsSuperior($role) && (int)$superior_id <= 0) {
        return false;
    }

    return true;
}

function hasSubordinates($conn, $staff_id) {
    $row = dbSelectOne($conn, "SELECT staff_id FROM staff WHERE superior_id = ? LIMIT 1", "i", array((int)$staff_id));
    return $row ? true : false;
}

function roleCanHaveSubordinates($role) {
    return $role == "superior" || $role == "admin";
}

function sqlNullableId($id) {
    $id = (int)$id;
    return $id > 0 ? (string)$id : "NULL";
}

function syncRoleTables($conn, $staff_id, $role_name, $department_id) {
    $staff_id = (int)$staff_id;
    $department_id = (int)$department_id;

    dbRun($conn, "DELETE FROM superior WHERE staff_id = ?", "i", array($staff_id));
    dbRun($conn, "DELETE FROM hr WHERE staff_id = ?", "i", array($staff_id));
    dbRun($conn, "DELETE FROM `admin` WHERE staff_id = ?", "i", array($staff_id));

    if ($role_name == "superior") {
        return dbRun($conn, "INSERT INTO superior (staff_id, department_id, assigned_date) VALUES (?, ?, CURDATE())", "ii", array($staff_id, $department_id));
    }

    if ($role_name == "hr") {
        return dbRun($conn, "INSERT INTO hr (staff_id, assigned_date) VALUES (?, CURDATE())", "i", array($staff_id));
    }

    if ($role_name == "admin") {
        return dbRun($conn, "INSERT INTO `admin` (staff_id, assigned_date) VALUES (?, CURDATE())", "i", array($staff_id));
    }

    return true;
}

function canHandleLeave($conn, $leave_id, $current_staff_id, $current_role) {
    $leave_id = (int)$leave_id;
    $current_staff_id = (int)$current_staff_id;

    if ($current_role == "admin") {
        $row = dbSelectOne($conn, "SELECT leave_id FROM leave_application WHERE leave_id = ? LIMIT 1", "i", array($leave_id));
    } else {
        $row = dbSelectOne(
            $conn,
            "SELECT l.leave_id
             FROM leave_application l
             INNER JOIN staff s ON l.staff_id = s.staff_id
             WHERE l.leave_id = ?
             AND s.superior_id = ?
             LIMIT 1",
            "ii",
            array($leave_id, $current_staff_id)
        );
    }

    return $row ? true : false;
}

function createEmailLog($conn, $leave_id, $status, $remark) {
    $leave_id = (int)$leave_id;

    $row = dbSelectOne(
        $conn,
        "SELECT s.email, s.staff_name, l.start_date, l.end_date
         FROM leave_application l
         INNER JOIN staff s ON l.staff_id = s.staff_id
         WHERE l.leave_id = ?
         LIMIT 1",
        "i",
        array($leave_id)
    );

    if ($row) {
        $receiver_email = $row["email"];
        $staff_name = $row["staff_name"];
        $start_date = $row["start_date"];
        $end_date = $row["end_date"];

        $subject = "Leave Application " . $status;
        $message = "Dear " . $staff_name . ", your leave application from " . $start_date . " to " . $end_date . " has been " . $status . ". Remark: " . $remark;

        dbRun(
            $conn,
            "INSERT INTO email_log (leave_id, receiver_email, subject, message, send_date) VALUES (?, ?, ?, ?, CURDATE())",
            "isss",
            array($leave_id, $receiver_email, $subject, $message)
        );
    }
}

function renderPageHeader($pageTitle = "") {
    global $COMPANY_NAME, $SYSTEM_NAME, $current_staff_name, $current_role, $current_department_name;

    if ($pageTitle == "") {
        $pageTitle = $SYSTEM_NAME;
    }

    $currentFile = basename($_SERVER["PHP_SELF"]);
    $listView = $_GET["view"] ?? "date";
    $profileInitial = $current_staff_name != "" ? strtoupper(substr($current_staff_name, 0, 1)) : "U";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle . " - " . $COMPANY_NAME); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="global-header">
    <div class="global-brand">
        <div class="logo-mark" aria-hidden="true"><span></span></div>
        <div class="global-title"><?php echo e(strtoupper($SYSTEM_NAME)); ?></div>
    </div>

    <div class="global-user">
        <span><?php echo e($current_staff_name); ?></span>
        <strong><?php echo e(strtoupper($current_role)); ?></strong>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-profile">
            <div class="profile-circle"><?php echo e($profileInitial); ?></div>
            <div>
                <h3><?php echo e($current_staff_name); ?></h3>
                <p><?php echo e(ucfirst($current_role)); ?> Account</p>
                <p class="help-text"><?php echo e($current_department_name); ?></p>
            </div>
        </div>

        <nav class="menu">
            <a class="<?php if ($currentFile == 'dashboard.php') echo 'active'; ?>" href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
            <a class="<?php if ($currentFile == 'update_staff.php') echo 'active'; ?>" href="update_staff.php"><span class="nav-icon">○</span>My Profile</a>

            <?php if ($current_role == "staff" || $current_role == "admin") { ?>
                <div class="menu-section">Staff</div>
                <a class="<?php if ($currentFile == 'apply_leave.php') echo 'active'; ?>" href="apply_leave.php"><span class="nav-icon">＋</span>Apply Leave</a>
                <a class="<?php if ($currentFile == 'leave_status.php') echo 'active'; ?>" href="leave_status.php"><span class="nav-icon">◷</span>Leave Status</a>
                <a class="<?php if ($currentFile == 'leave_history.php') echo 'active'; ?>" href="leave_history.php"><span class="nav-icon">☰</span>Leave History</a>
            <?php } ?>

            <?php if ($current_role == "superior" || $current_role == "admin") { ?>
                <div class="menu-section">Superior</div>
                <a class="<?php if ($currentFile == 'leave_list.php' && $listView != 'staff') echo 'active'; ?>" href="leave_list.php?view=date"><span class="nav-icon">◇</span>Applications by Date</a>
                <a class="<?php if ($currentFile == 'leave_list.php' && $listView == 'staff') echo 'active'; ?>" href="leave_list.php?view=staff"><span class="nav-icon">◎</span>Applications by Staff</a>
                <a class="<?php if ($currentFile == 'email_log.php') echo 'active'; ?>" href="email_log.php"><span class="nav-icon">✉</span>Email Logs</a>
            <?php } ?>

            <?php if ($current_role == "hr" || $current_role == "admin") { ?>
                <div class="menu-section">HR</div>
                <a class="<?php if ($currentFile == 'staff_list.php') echo 'active'; ?>" href="staff_list.php"><span class="nav-icon">▦</span>Manage Staff</a>
                <a class="<?php if ($currentFile == 'add_staff.php') echo 'active'; ?>" href="add_staff.php"><span class="nav-icon">⊕</span>Add Staff</a>
            <?php } ?>

            <div class="menu-section">Account</div>
            <a href="logout.php"><span class="nav-icon">↗</span>Sign Out</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-heading">
            <div>
                <p class="eyebrow"><?php echo e($COMPANY_NAME); ?> Leave Management</p>
                <h1><?php echo e($pageTitle); ?></h1>
            </div>
            <div class="status-dot-card"><span></span>Online</div>
        </div>
<?php
}

function renderPageFooter() {
?>
    </main>
</div>
<script>
function validateLeaveForm() {
    var leaveType = document.getElementById("leave_type").value;
    var startDate = document.getElementById("start_date").value;
    var endDate = document.getElementById("end_date").value;
    var reason = document.getElementById("reason").value;

    if (leaveType == "") {
        alert("Please select a leave type.");
        return false;
    }

    if (startDate == "") {
        alert("Please select a start date.");
        return false;
    }

    if (endDate == "") {
        alert("Please select an end date.");
        return false;
    }

    if (startDate > endDate) {
        alert("Start date cannot be later than end date.");
        return false;
    }

    if (reason.trim() == "") {
        alert("Please enter a reason.");
        return false;
    }

    return true;
}

function validateStaffForm() {
    var roleField = document.querySelector("select[name='role_id']");
    var superiorField = document.querySelector("select[name='superior_id']");

    if (roleField && superiorField) {
        var selectedRole = roleField.options[roleField.selectedIndex].getAttribute("data-role");
        if (selectedRole == "staff" && superiorField.value == "0") {
            alert("Staff role must have one superior. Please select a superior.");
            return false;
        }
    }

    return true;
}

function confirmDelete() {
    return confirm("Are you sure you want to delete this staff? Related leave records and email logs for this staff will also be deleted.");
}
</script>
</body>
</html>
<?php
}
?>
