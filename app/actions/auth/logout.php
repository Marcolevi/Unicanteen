<?php
session_start();
session_unset();
session_destroy();
// Optionally clear cookies or other login tokens if used
header("Location: login_register.php");
exit;
?>
