<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();
$result = $conn->query("
    SELECT p.*, c.name as category_name 
    FROM packages p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>Manage Packages</h1>
                <a href="add_package.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Package
                </a>
            </div>
            
            <?php 
            $messages = getMessages();
            if (isset($messages['success'])): 
            ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $messages['error']; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Duration</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($package = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $package['id']; ?></strong></td>
                                <td>
                                    <?php if ($package['image']): ?>
                                        <img src="<?php echo UPLOAD_URL . $package['image']; ?>" class="package-image" alt="Package">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="font-size: 30px; color: #ccc;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($package['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($package['category_name'] ?? 'No Category'); ?></td>
                                <td><strong>Rs <?php echo number_format($package['price'], 2); ?></strong></td>
                                <td><?php echo $package['duration']; ?> days</td>
                                <td><?php echo htmlspecialchars($package['location']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($package['status']); ?>">
                                        <?php echo $package['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($package['featured']): ?>
                                        <span class="badge" style="background: #4CAF50; color: white;">
                                            <i class="fas fa-star"></i> Featured
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #ccc; color: #666;">Not Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="edit_package.php?id=<?php echo $package['id']; ?>" 
                                       class="btn btn-warning btn-sm"
                                       title="Edit Package">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_package.php?id=<?php echo $package['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this package? This action cannot be undone.')" 
                                       class="btn btn-danger btn-sm"
                                       title="Delete Package">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 50px; color: #999;">
                                    <i class="fas fa-box-open" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                                    <strong>No packages found</strong>
                                    <p style="margin-top: 10px;">Click "Add New Package" button to create your first package</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>