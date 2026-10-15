<?php
session_start();
session_unset(); // Removes all session variables
session_destroy(); // Destroys the session completely
header("Location: login.php"); // Send them back to the login page
exit();
?>