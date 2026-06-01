<?php
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
session_start();
require_once '../config.php';
$conn = getConnection();

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($package_id == 0) {
    redirect('manage_packages.php');
}

$conn = getConnection();

// Get package details
$stmt = $conn->prepare("SELECT * FROM packages WHERE id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('manage_packages.php');
}

$package = $result->fetch_assoc();

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Get existing features
$existing_features = [];
$features_result = $conn->query("SELECT feature_name, feature_value FROM package_features WHERE package_id = $package_id");
while ($row = $features_result->fetch_assoc()) {
    $existing_features[] = $row['feature_name'] . ':' . $row['feature_value'];
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'];
    $name = sanitize($_POST['name']);
    $description = $_POST['description'];
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);
    $location = sanitize($_POST['location']);
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $max_people = intval($_POST['max_people']);
    $includes = $_POST['includes'];
    $excludes = $_POST['excludes'];
    $itinerary = $_POST['itinerary'];
    
    $image = $package['image'];
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Delete old image
            if ($package['image'] && file_exists(UPLOAD_DIR . $package['image'])) {
                unlink(UPLOAD_DIR . $package['image']);
            }
            
            $newname = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $newname);
            $image = $newname;
        }
    }
    
    // Update package
    $stmt = $conn->prepare("UPDATE packages SET category_id = ?, name = ?, description = ?, price = ?, duration = ?, location = ?, image = ?, status = ?, featured = ?, max_people = ?, includes = ?, excludes = ?, itinerary = ? WHERE id = ?");
    $stmt->bind_param("issdissssisssi", $category_id, $name, $description, $price, $duration, $location, $image, $status, $featured, $max_people, $includes, $excludes, $itinerary, $package_id);
    
    if ($stmt->execute()) {
        // Delete old features
        $conn->query("DELETE FROM package_features WHERE package_id = $package_id");
        
        // Add new features
        if (isset($_POST['features'])) {
            $feature_stmt = $conn->prepare("INSERT INTO package_features (package_id, feature_name, feature_value) VALUES (?, ?, ?)");
            foreach ($_POST['features'] as $feature) {
                list($fname, $fvalue) = explode(':', $feature);
                $feature_stmt->bind_param("iss", $package_id, $fname, $fvalue);
                $feature_stmt->execute();
            }
            $feature_stmt->close();
        }
        
        setSuccessMessage("Package updated successfully!");
        redirect('manage_packages.php');
    } else {
        $error = "Failed to update package!";
    }
    
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Package - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <h1>Edit Package</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $package['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Package Name *</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($package['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control"><?php echo htmlspecialchars($package['description']); ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="price">Price (Rs) *</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control" value="<?php echo $package['price']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (days) *</label>
                            <input type="number" name="duration" id="duration" class="form-control" value="<?php echo $package['duration']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" name="location" id="location" class="form-control" value="<?php echo htmlspecialchars($package['location']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Package Image</label>
                        <?php if ($package['image']): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="<?php echo UPLOAD_URL . $package['image']; ?>" style="max-width: 200px; border-radius: 8px;" alt="Current Image">
                                <p style="font-size: 13px; color: #666; margin-top: 5px;">Current image (upload new to replace)</p>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="Active" <?php echo $package['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $package['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_people">Max People</label>
                            <input type="number" name="max_people" id="max_people" class="form-control" value="<?php echo $package['max_people']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="featured" value="1" <?php echo $package['featured'] ? 'checked' : ''; ?>>
                            Mark as Featured Package
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="includes">What's Included</label>
                        <textarea name="includes" id="includes" class="form-control"><?php echo htmlspecialchars($package['includes']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="excludes">What's Excluded</label>
                        <textarea name="excludes" id="excludes" class="form-control"><?php echo htmlspecialchars($package['excludes']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="itinerary">Itinerary</label>
                        <textarea name="itinerary" id="itinerary" class="form-control"><?php echo htmlspecialchars($package['itinerary']); ?></textarea>
                        <small>Separate days with pipe (|) character</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Package Features (for recommendations)</label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <label><input type="checkbox" name="features[]" value="type:adventure" <?php echo in_array('type:adventure', $existing_features) ? 'checked' : ''; ?>> Adventure</label>
                            <label><input type="checkbox" name="features[]" value="type:beach" <?php echo in_array('type:beach', $existing_features) ? 'checked' : ''; ?>> Beach</label>
                            <label><input type="checkbox" name="features[]" value="type:cultural" <?php echo in_array('type:cultural', $existing_features) ? 'checked' : ''; ?>> Cultural</label>
                            <label><input type="checkbox" name="features[]" value="type:luxury" <?php echo in_array('type:luxury', $existing_features) ? 'checked' : ''; ?>> Luxury</label>
                            <label><input type="checkbox" name="features[]" value="type:spiritual" <?php echo in_array('type:spiritual', $existing_features) ? 'checked' : ''; ?>> Spiritual</label>
                            <label><input type="checkbox" name="features[]" value="activity:trekking" <?php echo in_array('activity:trekking', $existing_features) ? 'checked' : ''; ?>> Trekking</label>
                            <label><input type="checkbox" name="features[]" value="activity:water_sports" <?php echo in_array('activity:water_sports', $existing_features) ? 'checked' : ''; ?>> Water Sports</label>
                            <label><input type="checkbox" name="features[]" value="activity:meditation" <?php echo in_array('activity:meditation', $existing_features) ? 'checked' : ''; ?>> Meditation</label>
                            <label><input type="checkbox" name="features[]" value="activity:wildlife" <?php echo in_array('activity:wildlife', $existing_features) ? 'checked' : ''; ?>> Wildlife</label>
                            <label><input type="checkbox" name="features[]" value="activity:honeymoon" <?php echo in_array('activity:honeymoon', $existing_features) ? 'checked' : ''; ?>> Honeymoon</label>
                            <label><input type="checkbox" name="features[]" value="location_type:mountain" <?php echo in_array('location_type:mountain', $existing_features) ? 'checked' : ''; ?>> Mountain</label>
                            <label><input type="checkbox" name="features[]" value="location_type:tropical" <?php echo in_array('location_type:tropical', $existing_features) ? 'checked' : ''; ?>> Tropical</label>
                            <label><input type="checkbox" name="features[]" value="budget:budget" <?php echo in_array('type:spa', $existing_features) ? 'checked' : ''; ?>> Budget Friendly</label>
                            <label><input type="checkbox" name="features[]" value="budget:budget" <?php echo in_array('budget:budget', $existing_features) ? 'checked' : ''; ?>> Wellness & Spa</label>
                            <label><input type="checkbox" name="features[]" value="budget:premium" <?php echo in_array('budget:premium', $existing_features) ? 'checked' : ''; ?>> Premium</label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Package
                        </button>
                        <a href="manage_packages.php" class="btn" style="background: #666; color: white;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>