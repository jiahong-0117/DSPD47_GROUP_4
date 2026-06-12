<?php
require_once("core.php");

if (isset($_SESSION["staff_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = cleanInput($conn, $_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email == "" || $password == "") {
        $error = "Please enter email and password.";
    } else {
        $row = dbSelectOne(
            $conn,
            "SELECT s.staff_id, s.staff_name, s.email, s.password, s.department_id, d.department_name, r.role_id, r.role_name
             FROM staff s
             INNER JOIN `role` r ON s.role_id = r.role_id
             INNER JOIN department d ON s.department_id = d.department_id
             WHERE s.email = ? AND s.is_active = 1
             LIMIT 1",
            "s",
            array($email)
        );

        if ($row && verifyLoginPassword($password, $row["password"])) {
            session_regenerate_id(true);

            $_SESSION["staff_id"] = (int)$row["staff_id"];
            $_SESSION["staff_name"] = $row["staff_name"];
            $_SESSION["email"] = $row["email"];
            $_SESSION["role_id"] = (int)$row["role_id"];
            $_SESSION["role"] = $row["role_name"];
            $_SESSION["department_id"] = (int)$row["department_id"];
            $_SESSION["department_name"] = $row["department_name"];

            savePasswordHashIfNeeded($conn, (int)$row["staff_id"], $password, $row["password"]);

            header("Location: dashboard.php");
            exit();
        }

        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo e($SYSTEM_NAME); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo-wrap">
            <div class="logo-mark logo-mark-large" aria-hidden="true"><span></span></div>
        </div>

        <div class="login-heading">
            <p class="eyebrow"><?php echo e($COMPANY_NAME); ?> Staff Portal</p>
            <h1><?php echo e($SYSTEM_NAME); ?></h1>
            <p>Sign in to manage <?php echo e($COMPANY_NAME); ?> leave applications.</p>
        </div>

        <?php if ($error != "") { ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php } ?>

        <form method="post">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-full">Sign In</button>
        </form>

        <p class="help-text login-help">Demo accounts are listed in README.md after importing database.sql.</p>
    </div>
</div>
</body>
</html>
