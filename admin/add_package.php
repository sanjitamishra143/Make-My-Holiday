<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

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
    
    // Handle file upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newname = uniqid() . '.' . $ext;
            $upload_dir = '../uploads/';
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $newname);
            //move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $newname);
            $image = $newname;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO packages (category_id, name, description, price, duration, location, image, status, featured, max_people, includes, excludes, itinerary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdisssiisss", $category_id, $name, $description, $price, $duration, $location, $image, $status, $featured, $max_people, $includes, $excludes, $itinerary);
    
    if ($stmt->execute()) {
        $package_id = $stmt->insert_id;
        
        // Add package features for recommendation
        if (isset($_POST['features'])) {
            $feature_stmt = $conn->prepare("INSERT INTO package_features (package_id, feature_name, feature_value) VALUES (?, ?, ?)");
            foreach ($_POST['features'] as $feature) {
                list($fname, $fvalue) = explode(':', $feature);
                $feature_stmt->bind_param("iss", $package_id, $fname, $fvalue);
                $feature_stmt->execute();
            }
            $feature_stmt->close();
        }
        
        setSuccessMessage("Package added successfully!");
        redirect('manage_packages.php');
    } else {
        $error = "Failed to add package!";
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
    <title>Add Package - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <h1>Add New Package</h1>
            
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
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Package Name *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="price">Price (Rs) *</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (days) *</label>
                            <input type="number" name="duration" id="duration" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" name="location" id="location" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Package Image</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_people">Max People</label>
                            <input type="number" name="max_people" id="max_people" class="form-control" value="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="featured" value="1">
                            Mark as Featured Package
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="includes">What's Included</label>
                        <textarea name="includes" id="includes" class="form-control" placeholder="e.g., Accommodation, Meals, Transport"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="excludes">What's Excluded</label>
                        <textarea name="excludes" id="excludes" class="form-control" placeholder="e.g., Personal expenses, Tips"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="itinerary">Itinerary</label>
                        <textarea name="itinerary" id="itinerary" class="form-control" placeholder="Day 1: Arrival|Day 2: Sightseeing"></textarea>
                        <small>Separate days with pipe (|) character</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Package Features (for recommendations)</label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <label><input type="checkbox" name="features[]" value="type:adventure"> Adventure</label>
                            <label><input type="checkbox" name="features[]" value="type:beach"> Beach</label>
                            <label><input type="checkbox" name="features[]" value="type:cultural"> Cultural</label>
                            <label><input type="checkbox" name="features[]" value="type:luxury"> Luxury</label>
                            <label><input type="checkbox" name="features[]" value="type:spiritual"> Spiritual</label>
                            <label><input type="checkbox" name="features[]" value="activity:trekking"> Trekking</label>
                            <label><input type="checkbox" name="features[]" value="activity:water_sports"> Water Sports</label>
                            <label><input type="checkbox" name="features[]" value="activity:meditation"> Meditation</label>
                            <label><input type="checkbox" name="features[]" value="activity:wildlife"> Wildlife</label>
                            <label><input type="checkbox" name="features[]" value="activity:honeymoon"> Honeymoon</label>
                            <label><input type="checkbox" name="features[]" value="location_type:mountain"> Mountain</label>
                            <label><input type="checkbox" name="features[]" value="location_type:tropical"> Tropical</label>
                            <label><input type="checkbox" name="features[]" value="budget:budget"> Budget Friendly</label>
                            <label><input type="checkbox" name="features[]" value="budget:budget"> Wellness & Spa</label>
                            <label><input type="checkbox" name="features[]" value="budget:premium"> Premium</label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Package
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