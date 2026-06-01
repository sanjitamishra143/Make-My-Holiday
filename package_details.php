<?php
require_once 'config.php';

$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($package_id == 0) {
    redirect('packages.php');
}

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {

    if (!isTouristLoggedIn()) {
        redirect('tourist_login.php');
    }

    $tourist_id = $_SESSION['tourist_id'];
    $rating     = intval($_POST['rating']);
    $comment    = sanitize($_POST['comment']);

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $review_error = "Please select a star rating.";
        goto skip_review;
    }

    // Validate comment
    if (empty(trim($comment))) {
        $review_error = "Please write a comment before submitting.";
        goto skip_review;
    }

    // Check tourist has a completed/confirmed booking for this package
    $booking_check = $conn->prepare("
        SELECT id FROM bookings
        WHERE tourist_id = ? AND package_id = ?
        AND status IN ('Confirmed', 'Completed')
        LIMIT 1
    ");
    $booking_check->bind_param("ii", $tourist_id, $package_id);
    $booking_check->execute();
    if ($booking_check->get_result()->num_rows == 0) {
        $review_error = "You can only review packages you have booked and confirmed.";
        $booking_check->close();
        goto skip_review;
    }
    $booking_check->close();

    // Check if already reviewed
    $dup_check = $conn->prepare("
        SELECT id FROM comments
        WHERE tourist_id = ? AND package_id = ?
        LIMIT 1
    ");
    $dup_check->bind_param("ii", $tourist_id, $package_id);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) {
        $review_error = "You have already submitted a review for this package.";
        $dup_check->close();
        goto skip_review;
    }
    $dup_check->close();

    // Insert review (status = Pending — admin must approve)
    $stmt = $conn->prepare("
        INSERT INTO comments (tourist_id, package_id, rating, comment, status, created_at)
        VALUES (?, ?, ?, ?, 'Pending', NOW())
    ");
    $stmt->bind_param("iiis", $tourist_id, $package_id, $rating, $comment);

    if ($stmt->execute()) {
        $review_success = "✅ Your review has been submitted and is pending approval. Thank you!";
    } else {
        $review_error = "Failed to submit review. Please try again.";
    }
    $stmt->close();

    skip_review:;
}

// Get package details
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name
    FROM packages p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'Active'
");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('packages.php');
}

$package = $result->fetch_assoc();

// Get approved reviews
$reviews = $conn->query("
    SELECT c.*, t.name as tourist_name
    FROM comments c
    JOIN tourists t ON c.tourist_id = t.id
    WHERE c.package_id = $package_id AND c.status = 'Approved'
    ORDER BY c.created_at DESC
");

// Average rating
$avg_rating = $conn->query("
    SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
    FROM comments
    WHERE package_id = $package_id AND status = 'Approved'
")->fetch_assoc();

// Similar packages 
$recommended = $conn->query("
    SELECT p.* FROM packages p
    WHERE p.category_id = {$package['category_id']}
    AND p.id != $package_id
    AND p.status = 'Active'
    LIMIT 3
");

// Check if logged-in tourist already reviewed 
$already_reviewed = false;
$has_booking      = false;
if (isTouristLoggedIn()) {
    $tid = $_SESSION['tourist_id'];

    $r = $conn->prepare("SELECT id FROM comments WHERE tourist_id = ? AND package_id = ?");
    $r->bind_param("ii", $tid, $package_id);
    $r->execute();
    $already_reviewed = $r->get_result()->num_rows > 0;
    $r->close();

    $b = $conn->prepare("SELECT id FROM bookings WHERE tourist_id = ? AND package_id = ? AND status IN ('Confirmed','Completed')");
    $b->bind_param("ii", $tid, $package_id);
    $b->execute();
    $has_booking = $b->get_result()->num_rows > 0;
    $b->close();
}

$conn->close();

$itinerary_days = $package['itinerary'] ? explode('|', $package['itinerary']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($package['name']); ?> - Make My Holiday</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Review Form ── */
        .review-form-box {
            background: #f8f9ff;
            border: 2px solid #e8ecff;
            border-radius: 14px;
            padding: 28px;
            margin-top: 30px;
        }
        .review-form-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .review-form-box h3 i { color: #667eea; }

        /* Star rating input */
        .star-rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 6px;
            margin-bottom: 20px;
        }
        .star-rating-input input[type="radio"] { display: none; }
        .star-rating-input label {
            font-size: 36px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.15s;
            line-height: 1;
        }
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label,
        .star-rating-input input:checked ~ label {
            color: #f5a623;
        }
        .star-label {
            font-weight: 600;
            font-size: 13px;
            color: #555;
            margin-bottom: 8px;
            display: block;
        }

        /* Comment textarea */
        .review-form-box textarea {
            width: 100%;
            border: 1px solid #dde0ee;
            border-radius: 10px;
            padding: 14px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 110px;
            outline: none;
            background: white;
            transition: border 0.2s;
            box-sizing: border-box;
        }
        .review-form-box textarea:focus { border-color: #667eea; }

        .btn-submit-review {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 32px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 16px;
            transition: opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit-review:hover { opacity: 0.9; }

        /* Alerts */
        .review-alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            border-radius: 10px;
            padding: 14px 18px;
            color: #155724;
            font-size: 14px;
            margin-top: 16px;
        }
        .review-alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 14px 18px;
            color: #721c24;
            font-size: 14px;
            margin-top: 16px;
        }
        .review-alert-info {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 14px 18px;
            color: #856404;
            font-size: 14px;
            margin-top: 16px;
        }

        /* Existing reviews */
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }
        .review-item:last-child { border-bottom: none; }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .reviewer-info { display: flex; align-items: center; gap: 14px; }
        .reviewer-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 18px;
            flex-shrink: 0;
        }
        .reviewer-info h4 { margin: 0 0 4px; font-size: 15px; color: #333; }
        .review-rating i  { color: #ddd; font-size: 14px; }
        .review-rating i.active { color: #f5a623; }
        .review-date { font-size: 12px; color: #999; }
        .review-text { font-size: 14px; color: #555; line-height: 1.7; margin: 0; }
        .no-reviews  { color: #999; font-style: italic; padding: 20px 0; }

        /* Rating summary bar */
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #f8f9ff;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
        }
        .rating-big {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
            line-height: 1;
        }
        .rating-stars-big i { color: #f5a623; font-size: 20px; }
        .rating-count { font-size: 13px; color: #999; margin-top: 4px; }
    </style>
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
                <li><a href="packages.php">Packages</a></li>
                <?php if (isTouristLoggedIn()): ?>
                    <li><a href="my_bookings.php">My Bookings</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="tourist_login.php">Login</a></li>
                    <li><a href="tourist_register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Package Details -->
    <section class="package-details-section">
        <div class="container">
            <div class="package-details-grid">

                <!-- MAIN CONTENT -->
                <div class="package-main">
                    <div class="package-header">
                        <div class="breadcrumb">
                            <a href="index.php">Home</a> /
                            <a href="packages.php">Packages</a> /
                            <?php echo htmlspecialchars($package['name']); ?>
                        </div>
                        <h1><?php echo htmlspecialchars($package['name']); ?></h1>
                        <div class="package-meta">
                            <span class="category-badge">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($package['category_name']); ?>
                            </span>
                            <span class="location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($package['location']); ?>
                            </span>
                            <?php if ($avg_rating['review_count'] > 0): ?>
                            <span class="rating">
                                <i class="fas fa-star"></i>
                                <?php echo number_format($avg_rating['avg_rating'], 1); ?>
                                (<?php echo $avg_rating['review_count']; ?> reviews)
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="package-image-large">
                        <?php if ($package['image']): ?>
                            <img src="<?php echo UPLOAD_URL . $package['image']; ?>"
                                 alt="<?php echo htmlspecialchars($package['name']); ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/1200x600?text=<?php echo urlencode($package['name']); ?>"
                                 alt="Package">
                        <?php endif; ?>
                    </div>

                    <!-- About -->
                    <div class="package-content-block">
                        <h2>About This Package</h2>
                        <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                    </div>

                    <!-- Included -->
                    <?php if (!empty($package['includes'])): ?>
                    <div class="package-content-block">
                        <h2><i class="fas fa-check-circle"></i> What's Included</h2>
                        <ul class="included-list">
                            <?php foreach (explode(',', $package['includes']) as $inc): ?>
                                <li><i class="fas fa-check"></i> <?php echo trim(htmlspecialchars($inc)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Excluded -->
                    <?php if (!empty($package['excludes'])): ?>
                    <div class="package-content-block">
                        <h2><i class="fas fa-times-circle"></i> What's Not Included</h2>
                        <ul class="excluded-list">
                            <?php foreach (explode(',', $package['excludes']) as $exc): ?>
                                <li><i class="fas fa-times"></i> <?php echo trim(htmlspecialchars($exc)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Itinerary -->
                    <?php if (!empty($itinerary_days)): ?>
                    <div class="package-content-block">
                        <h2><i class="fas fa-route"></i> Itinerary</h2>
                        <div class="itinerary-timeline">
                            <?php foreach ($itinerary_days as $index => $day): ?>
                                <div class="itinerary-day">
                                    <div class="day-number">Day <?php echo $index + 1; ?></div>
                                    <div class="day-content">
                                        <p><?php echo htmlspecialchars(trim($day)); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="package-content-block">
                        <h2><i class="fas fa-comments"></i> Reviews</h2>

                        <!-- Rating Summary (shown if reviews exist) -->
                        <?php if ($avg_rating['review_count'] > 0): ?>
                        <div class="rating-summary">
                            <div class="rating-big">
                                <?php echo number_format($avg_rating['avg_rating'], 1); ?>
                            </div>
                            <div>
                                <div class="rating-stars-big">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($avg_rating['avg_rating']) ? '' : 'far'; ?>"
                                           style="color: <?php echo $i <= round($avg_rating['avg_rating']) ? '#f5a623' : '#ddd'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-count">
                                    Based on <?php echo $avg_rating['review_count']; ?> review<?php echo $avg_rating['review_count'] > 1 ? 's' : ''; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Existing Reviews -->
                        <?php if ($reviews->num_rows > 0): ?>
                            <div class="reviews-list">
                                <?php while ($review = $reviews->fetch_assoc()): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <?php echo strtoupper(substr($review['tourist_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h4><?php echo htmlspecialchars($review['tourist_name']); ?></h4>
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="review-date">
                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="review-text">
                                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                    </p>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-reviews">No reviews yet. Be the first to review this package!</p>
                        <?php endif; ?>

                        <!-- REVIEW SUBMISSION FORM -->
                        <?php if (!isTouristLoggedIn()): ?>
                            <!-- Not logged in -->
                            <div class="review-alert-info">
                                <i class="fas fa-info-circle"></i>
                                <a href="tourist_login.php" style="color:#856404; font-weight:600;">Login</a>
                                to write a review for this package.
                            </div>

                        <?php elseif ($already_reviewed): ?>
                            <!-- Already reviewed -->
                            <div class="review-alert-info">
                                <i class="fas fa-check-circle"></i>
                                You have already submitted a review for this package. Thank you!
                            </div>

                        <?php elseif (!$has_booking): ?>
                            <!-- No confirmed booking -->
                            <div class="review-alert-info">
                                <i class="fas fa-lock"></i>
                                Only tourists with a <strong>Confirmed</strong> or <strong>Completed</strong>
                                booking can write a review.
                                <a href="customize_package.php?id=<?php echo $package['id']; ?>"
                                   style="color:#856404; font-weight:600;">Book this package</a> to leave a review.
                            </div>

                        <?php else: ?>
                            <!-- Show success/error from submission -->
                            <?php if (isset($review_success)): ?>
                                <div class="review-alert-success">
                                    <i class="fas fa-check-circle"></i> <?php echo $review_success; ?>
                                </div>
                            <?php elseif (isset($review_error)): ?>
                                <div class="review-alert-error">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $review_error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!isset($review_success)): ?>
                            <!-- Review Form -->
                            <div class="review-form-box">
                                <h3><i class="fas fa-pen"></i> Write a Review</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="submit_review">

                                    <!-- Star Rating -->
                                    <span class="star-label">Your Rating *</span>
                                    <div class="star-rating-input">
                                        <input type="radio" name="rating" id="star5" value="5">
                                        <label for="star5" title="5 stars">&#9733;</label>
                                        <input type="radio" name="rating" id="star4" value="4">
                                        <label for="star4" title="4 stars">&#9733;</label>
                                        <input type="radio" name="rating" id="star3" value="3">
                                        <label for="star3" title="3 stars">&#9733;</label>
                                        <input type="radio" name="rating" id="star2" value="2">
                                        <label for="star2" title="2 stars">&#9733;</label>
                                        <input type="radio" name="rating" id="star1" value="1">
                                        <label for="star1" title="1 star">&#9733;</label>
                                    </div>

                                    <!-- Comment -->
                                    <div style="margin-top: 8px;">
                                        <span class="star-label">Your Review *</span>
                                        <textarea name="comment"
                                                  placeholder="Share your experience with this package..."
                                                  required><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" class="btn-submit-review">
                                        <i class="fas fa-paper-plane"></i> Submit Review
                                    </button>

                                    <p style="font-size:12px; color:#999; margin-top:10px;">
                                        <i class="fas fa-info-circle"></i>
                                        Your review will be visible after admin approval.
                                    </p>
                                </form>
                            </div>
                            <?php endif; ?>

                        <?php endif; ?>
                        <!-- END REVIEW FORM -->

                    </div>
                    <!-- END Reviews Section -->

                </div>

                <!-- SIDEBAR -->
                <div class="package-sidebar">
                    <div class="booking-card">
                        <div class="price-section">
                            <span class="price-label">Starting from</span>
                            <h2 class="price">Rs <?php echo number_format($package['price'], 2); ?></h2>
                            <span class="per-person">per person</span>
                        </div>
                        <div class="package-info">
                            <div class="info-item">
                                <i class="far fa-clock"></i>
                                <div>
                                    <strong>Duration</strong>
                                    <span><?php echo $package['duration']; ?> days</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <div>
                                    <strong>Max People</strong>
                                    <span><?php echo $package['max_people']; ?> persons</span>
                                </div>
                            </div>
                        </div>
                        <?php if (isTouristLoggedIn()): ?>
                            <a href="customize_package.php?id=<?php echo $package['id']; ?>"
                               class="btn btn-primary btn-block">
                                <i class="fas fa-cog"></i> Customize & Book
                            </a>
                        <?php else: ?>
                            <a href="tourist_login.php" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt"></i> Login to Book
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Similar Packages -->
                    <?php if ($recommended->num_rows > 0): ?>
                    <div class="recommended-section">
                        <h3>Similar Packages</h3>
                        <?php while ($rec = $recommended->fetch_assoc()): ?>
                        <div class="recommended-item">
                            <div class="rec-image">
                                <?php if ($rec['image']): ?>
                                    <img src="<?php echo UPLOAD_URL . $rec['image']; ?>" alt="Package">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/100x80" alt="Package">
                                <?php endif; ?>
                            </div>
                            <div class="rec-content">
                                <h4><?php echo htmlspecialchars($rec['name']); ?></h4>
                                <p class="rec-price">Rs <?php echo number_format($rec['price'], 2); ?></p>
                                <a href="package_details.php?id=<?php echo $rec['id']; ?>">View Details</a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Make My Holiday</h3>
                    <p>Your trusted travel partner</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="packages.php">Packages</a></li>
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