<?php

require_once 'config.php';

// Destroy all session data
session_destroy();

// Redirect to home page
redirect('index.php');
?>