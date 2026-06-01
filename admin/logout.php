<?php
require_once '../config.php';

// Destroy session
session_destroy();

// Redirect to admin login
redirect('login.php');
?>