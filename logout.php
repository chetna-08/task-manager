<?php
require_once 'session.php';

// Destroy session and redirect to login
session_start();
session_destroy();
header("Location: login.php");
exit;
?>
