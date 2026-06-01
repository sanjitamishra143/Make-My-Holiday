<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
function getConnection() {
    $conn = new mysqli("localhost", "root", "", "make_my_holiday");

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($input) {
    return htmlspecialchars(trim($input));
}

// Tourist Auth Helpers
function isTouristLoggedIn() {
    return isset($_SESSION['tourist_id']);
}

// Admin Auth Helpers
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        redirect('login.php');
    }
}

// Messages Helpers
function setSuccessMessage($msg) {
    $_SESSION['success_msg'] = $msg;
}

function getSuccessMessage() {
    if (isset($_SESSION['success_msg'])) {
        $msg = $_SESSION['success_msg'];
        unset($_SESSION['success_msg']);
        return $msg;
    }
    return '';
}

function setErrorMessage($msg) {
    $_SESSION['error_msg'] = $msg;
}

function getErrorMessage() {
    if (isset($_SESSION['error_msg'])) {
        $msg = $_SESSION['error_msg'];
        unset($_SESSION['error_msg']);
        return $msg;
    }
    return '';
}
function getMessages() {
    $messages = [];
    $success = getSuccessMessage();
    $error = getErrorMessage();
    if ($success) $messages['success'] = $success;
    if ($error) $messages['error'] = $error;
    return $messages;
}

// Upload path
define('UPLOAD_URL', 'uploads/');
?>