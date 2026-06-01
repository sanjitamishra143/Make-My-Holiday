<?php
require_once 'config.php';

$conn = getConnection();

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Build query
$where = ["p.status = 'Active'"];
$params = [];
$types = '';

if ($category_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = implode(' AND ', $where);

// Sorting
$order_by = 'p.created_at DESC';
switch ($sort) {
    case 'price_low':
        $order_by = 'p.price ASC';
        break;
    case 'price_high':
        $order_by = 'p.price DESC';
        break;
    case 'duration_short':
        $order_by = 'p.duration ASC';
        break;
    case 'duration_long':
        $order_by = 'p.duration DESC';
        break;
    case 'popular':
        $order_by = 'p.featured DESC, p.created_at DESC';
        break;
}

$query = "SELECT p.*, c.name as category_name 
          FROM packages p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE $where_clause 
          ORDER BY $order_by";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$packages = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Packages - Make My Holiday</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <i class="fas fa-plane-departure"></i>
                <span>Make My Holiday</span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="packages.php" class="active">Packages</a></li>
                <?php if (isTouristLoggedIn()): ?>
                    <li><a href="my_bookings.php">My Bookings</a></li>
                    <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['tourist_name']); ?>)</a></li>
                <?php else: ?>
                    <li><a href="tourist_login.php">Login</a></li>
                    <li><a href="tourist_register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Explore Our Packages</h1>
            <p>Find your perfect travel destination</p>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="filters-section">
        <div class="container">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Search packages..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-list"></i> Category</label>
                    <select name="category">
                        <option value="0">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-sort"></i> Sort By</label>
                    <select name="sort">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="duration_short" <?php echo $sort == 'duration_short' ? 'selected' : ''; ?>>Duration: Short to Long</option>
                        <option value="duration_long" <?php echo $sort == 'duration_long' ? 'selected' : ''; ?>>Duration: Long to Short</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </div>
    </section>

    <!-- Packages Grid -->
    <section class="packages-section">
        <div class="container">
            <div style="margin-bottom: 20px; color: #666; font-size: 16px;">
                <strong><?php echo $packages->num_rows; ?></strong> package(s) found
                <?php if ($search): ?>
                    for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </div>
            
            <?php if ($packages->num_rows > 0): ?>
                <div class="packages-grid">
                    <?php while ($pkg = $packages->fetch_assoc()): ?>
                    <div class="package-card">
                        <div class="package-image">
                            <?php if ($pkg['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $pkg['image']; ?>" alt="<?php echo htmlspecialchars($pkg['name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x300?text=<?php echo urlencode($pkg['name']); ?>" alt="Package">
                            <?php endif; ?>
                            <?php if ($pkg['featured']): ?>
                                <div class="featured-badge"><i class="fas fa-star"></i> Featured</div>
                            <?php endif; ?>
                            <div class="package-badge"><?php echo htmlspecialchars($pkg['category_name']); ?></div>
                        </div>
                        <div class="package-content">
                            <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                            <p class="package-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($pkg['location']); ?>
                            </p>
                            <p class="package-description">
                                <?php echo substr(htmlspecialchars($pkg['description']), 0, 120); ?>...
                            </p>
                            <div class="package-footer">
                                <div class="package-price">
                                    <span class="price-label">From</span>
                                    <span class="price-amount">Rs <?php echo number_format($pkg['price'], 2); ?></span>
                                </div>
                                <div class="package-duration">
                                    <i class="far fa-clock"></i>
                                    <?php echo $pkg['duration']; ?> days
                                </div>
                            </div>
                            <div class="package-actions">
                                <a href="package_details.php?id=<?php echo $pkg['id']; ?>" class="btn btn-primary btn-block">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if (isTouristLoggedIn()): ?>
                                    <a href="customize_package.php?id=<?php echo $pkg['id']; ?>" class="btn btn-outline btn-block">
                                        <i class="fas fa-cog"></i> Customize & Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No packages found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                    <a href="packages.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Make My Holiday</h3>
                    <p>Your trusted travel partner for unforgettable journeys</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="packages.php">Packages</a></li>
                        <li><a href="tourist_login.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> +977 9876543210</p>
                    <p><i class="fas fa-envelope"></i> info@makemyholiday.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Make My Holiday. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>