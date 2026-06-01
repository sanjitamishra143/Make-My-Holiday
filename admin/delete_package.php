<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($package_id == 0) {
    setErrorMessage("Invalid package ID!");
    redirect('manage_packages.php');
}

$conn = getConnection();

// Get package image to delete
$stmt = $conn->prepare("SELECT image FROM packages WHERE id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $package = $result->fetch_assoc();
    
    // Delete package
    $stmt = $conn->prepare("DELETE FROM packages WHERE id = ?");
    $stmt->bind_param("i", $package_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if ($package['image'] && file_exists(UPLOAD_DIR . $package['image'])) {
            unlink(UPLOAD_DIR . $package['image']);
        }
        
        setSuccessMessage("Package deleted successfully!");
    } else {
        setErrorMessage("Failed to delete package!");
    }
} else {
    setErrorMessage("Package not found!");
}

$stmt->close();
$conn->close();

redirect('manage_packages.php');
?>