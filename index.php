<?php
require_once("core.php");

if (isset($_SESSION["staff_id"])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
