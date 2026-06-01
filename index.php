<?php
require_once 'config.php';

$conn = getConnection();

// Get featured packages
$featured = $conn->query("
    SELECT p.*, c.name as category_name 
    FROM packages p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'Active' AND p.featured = TRUE 
    LIMIT 6
");

// Get categories with package count
$categories = $conn->query("
    SELECT c.*, COUNT(p.id) as package_count 
    FROM categories c 
    LEFT JOIN packages p ON c.id = p.category_id AND p.status = 'Active' 
    GROUP BY c.id
");

// Get statistics
$total_packages = $conn->query("SELECT COUNT(*) as count FROM packages WHERE status = 'Active'")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status != 'Cancelled'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make My Holiday - Your Travel Partner</title>
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
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="packages.php">Packages</a></li>
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

    <!-- Success/Error Messages -->
    <?php 
    $messages = getMessages();
    if (isset($messages['success'])): 
    ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; text-align: center; border-bottom: 3px solid #28a745;">
            <div class="container">
                <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Explore the World with Us</h1>
            <p>Discover amazing destinations and create unforgettable memories</p>
            <a href="packages.php" class="btn btn-primary btn-lg">
                <i class="fas fa-search"></i> Explore Packages
            </a>
        </div>
    </section>

    <!-- Statistics Section -->
    <section style="background: white; padding: 60px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; text-align: center;">
                <div style="padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                    <i class="fas fa-box" style="font-size: 52px; margin-bottom: 15px; opacity: 0.9;"></i>
                    <h2 style="font-size: 48px; margin-bottom: 10px; font-weight: 700;"><?php echo $total_packages; ?></h2>
                    <p style="font-size: 18px; opacity: 0.95;">Travel Packages</p>
                </div>
                <div style="padding: 30px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 15px; color: white; box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);">
                    <i class="fas fa-list" style="font-size: 52px; margin-bottom: 15px; opacity: 0.9;"></i>
                    <h2 style="font-size: 48px; margin-bottom: 10px; font-weight: 700;"><?php echo $total_categories; ?></h2>
                    <p style="font-size: 18px; opacity: 0.95;">Categories</p>
                </div>
                <div style="padding: 30px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 15px; color: white; box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);">
                    <i class="fas fa-users" style="font-size: 52px; margin-bottom: 15px; opacity: 0.9;"></i>
                    <h2 style="font-size: 48px; margin-bottom: 10px; font-weight: 700;"><?php echo $total_bookings; ?></h2>
                    <p style="font-size: 18px; opacity: 0.95;">Happy Travelers</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <h2>Popular Categories</h2>
            <p style="text-align: center; color: #666; margin-bottom: 50px; font-size: 18px;">Choose from our wide range of travel categories</p>
            <div class="categories-grid">
                <?php if ($categories->num_rows > 0): ?>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                    <div class="category-card" onclick="window.location.href='packages.php?category=<?php echo $cat['id']; ?>'">
                        <i class="fas <?php echo $cat['icon']; ?>"></i>
                        <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p><?php echo $cat['package_count']; ?> packages available</p>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #999;">
                        <i class="fas fa-folder-open" style="font-size: 64px; margin-bottom: 20px; color: #ddd;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">No categories available yet</h3>
                        <p>Categories will be added soon!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Featured Packages -->
    <section class="featured-packages">
        <div class="container">
            <h2>Featured Packages</h2>
            <p style="text-align: center; color: #666; margin-bottom: 50px; font-size: 18px;">Handpicked destinations just for you</p>
            
            <?php if ($featured->num_rows > 0): ?>
                <div class="packages-grid">
                    <?php while ($pkg = $featured->fetch_assoc()): ?>
                    <div class="package-card">
                        <div class="package-image">
                            <?php if ($pkg['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $pkg['image']; ?>" alt="<?php echo htmlspecialchars($pkg['name']); ?>">
                            <?php else: ?>
                                <img src="https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?w=400&h=300&fit=crop" alt="Package">
                            <?php endif; ?>
                            <div class="featured-badge"><i class="fas fa-star"></i> Featured</div>
                            <div class="package-badge"><?php echo htmlspecialchars($pkg['category_name']); ?></div>
                        </div>
                        <div class="package-content">
                            <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                            <p class="package-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($pkg['location']); ?>
                            </p>
                            <p class="package-description">
                                <?php 
                                $description = $pkg['description'] ? htmlspecialchars($pkg['description']) : 'Explore this amazing destination';
                                echo substr($description, 0, 100) . '...'; 
                                ?>
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
                            <a href="package_details.php?id=<?php echo $pkg['id']; ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div style="text-align: center; margin-top: 50px;">
                    <a href="packages.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-globe"></i> View All Packages
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <i class="fas fa-box-open" style="font-size: 80px; color: #ddd; margin-bottom: 25px;"></i>
                    <h3 style="color: #666; font-size: 28px; margin-bottom: 15px;">No Featured Packages Yet</h3>
                    <p style="color: #999; font-size: 16px; margin-bottom: 30px;">Check back soon for exciting travel packages!</p>
                    <a href="packages.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Browse All Packages
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 80px 0;">
        <div class="container">
            <h2 style="text-align: center; font-size: 36px; margin-bottom: 20px; color: #333;">Why Choose Make My Holiday?</h2>
            <p style="text-align: center; color: #666; font-size: 18px; margin-bottom: 60px;">We make your travel dreams come true</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px;">
                <div style="text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-10px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="width: 80px; height: 80px; margin: 0 auto 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-shield-alt" style="font-size: 36px; color: white;"></i>
                    </div>
                    <h3 style="margin-bottom: 15px; color: #333; font-size: 22px;">Safe & Secure</h3>
                    <p style="color: #666; line-height: 1.6;">Your safety is our priority. All bookings are verified and secure with trusted payment methods.</p>
                </div>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-10px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="width: 80px; height: 80px; margin: 0 auto 25px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-dollar-sign" style="font-size: 36px; color: white;"></i>
                    </div>
                    <h3 style="margin-bottom: 15px; color: #333; font-size: 22px;">Best Prices</h3>
                    <p style="color: #666; line-height: 1.6;">Get the best deals on travel packages with transparent pricing and no hidden charges.</p>
                </div>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-10px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="width: 80px; height: 80px; margin: 0 auto 25px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-headset" style="font-size: 36px; color: white;"></i>
                    </div>
                    <h3 style="margin-bottom: 15px; color: #333; font-size: 22px;">24/7 Support</h3>
                    <p style="color: #666; line-height: 1.6;">Our dedicated team is always here to help you with any questions or concerns.</p>
                </div>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-10px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="width: 80px; height: 80px; margin: 0 auto 25px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-cog" style="font-size: 36px; color: white;"></i>
                    </div>
                    <h3 style="margin-bottom: 15px; color: #333; font-size: 22px;">Customizable</h3>
                    <p style="color: #666; line-height: 1.6;">Tailor packages to your preferences, budget, and schedule for the perfect trip.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 80px 0; color: white; text-align: center;">
        <div class="container">
            <h2 style="font-size: 42px; margin-bottom: 20px; font-weight: 700;">Ready to Start Your Adventure?</h2>
            <p style="font-size: 20px; margin-bottom: 40px; opacity: 0.95;">Join thousands of happy travelers and book your dream vacation today!</p>
            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <a href="packages.php" class="btn btn-lg" style="background: white; color: #667eea; padding: 15px 40px; font-size: 18px;">
                    <i class="fas fa-search"></i> Browse Packages
                </a>
                <?php if (!isTouristLoggedIn()): ?>
                <a href="tourist_register.php" class="btn btn-lg" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 15px 40px; font-size: 18px;">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-plane-departure"></i> Make My Holiday</h3>
                    <p style="margin: 20px 0;">Your trusted travel partner for unforgettable journeys around the world.</p>
                    <div style="margin-top: 20px;">
                        <a href="#" style="color: white; margin-right: 15px; font-size: 24px; transition: opacity 0.3s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" style="color: white; margin-right: 15px; font-size: 24px; transition: opacity 0.3s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" style="color: white; margin-right: 15px; font-size: 24px; transition: opacity 0.3s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" style="color: white; font-size: 24px; transition: opacity 0.3s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="packages.php">Packages</a></li>
                        <li><a href="tourist_login.php">Login</a></li>
                        <li><a href="tourist_register.php">Register</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</p>
                    <p><i class="fas fa-phone"></i> +977 9814000000</p>
                    <p><i class="fas fa-envelope"></i> info@makemyholiday.com</p>
                    <p><i class="fas fa-clock"></i> Mon - Fri: 9AM - 6PM</p>
                </div>
                <div class="footer-section">
                    <h4>Newsletter</h4>
                    <p style="margin-bottom: 15px;">Subscribe to get special offers and travel tips!</p>
                    <form style="margin-top: 15px;" onsubmit="event.preventDefault(); alert('Thank you for subscribing! Check your email for confirmation.');">
                        <input type="email" placeholder="Your email address" style="width: 100%; padding: 12px; border: none; border-radius: 5px; margin-bottom: 10px;" required>
                        <button type="submit" style="width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: background 0.3s;" onmouseover="this.style.background='#5568d3'" onmouseout="this.style.background='#667eea'">
                            <i class="fas fa-paper-plane"></i> Subscribe
                        </button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Make My Holiday. All rights reserved. | Designed with <i class="fas fa-heart" style="color: #f5576c;"></i> for travelers</p>
            </div>
        </div>
    </footer>
</body>
</html>