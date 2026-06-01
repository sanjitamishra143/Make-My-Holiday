<?php
require_once 'config.php';
require_once 'recommendation.php';

if (!isTouristLoggedIn()) {
    redirect('tourist_login.php');
}

$tourist_id = $_SESSION['tourist_id'];
$conn       = getConnection();

// Tourist: Cancel booking 
if (isset($_GET['cancel'])) {
    $bid  = intval($_GET['cancel']);
    $stmt = $conn->prepare("
        UPDATE bookings SET status = 'Cancelled'
        WHERE id = ? AND tourist_id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("ii", $bid, $tourist_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        setSuccessMessage("Booking #$bid has been cancelled.");
    } else {
        setErrorMessage("Cannot cancel this booking. Only Pending bookings can be cancelled.");
    }
    $stmt->close();
    $conn->close();
    redirect('my_bookings.php');
}

//  Tourist: Delete booking 
if (isset($_GET['delete'])) {
    $bid  = intval($_GET['delete']);
    $stmt = $conn->prepare("
        DELETE FROM bookings
        WHERE id = ? AND tourist_id = ? AND status IN ('Cancelled', 'Completed', 'Rejected')
    ");
    $stmt->bind_param("ii", $bid, $tourist_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        setSuccessMessage("Booking #$bid has been removed from your list.");
    } else {
        setErrorMessage("Cannot delete this booking. Only Cancelled, Rejected, or Completed bookings can be removed.");
    }
    $stmt->close();
    $conn->close();
    redirect('my_bookings.php');
}

// Fetch bookings 
$stmt = $conn->prepare("
    SELECT b.*, p.name as package_name, p.image, p.location
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    WHERE b.tourist_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $tourist_id);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();

//  CF Recommendations 
// Always get recommendations — CF if possible, popular packages as fallback
$recommended_packages = getRecommendedPackages($tourist_id, 3);

// Hard fallback: if CF + popular both return nothing, query directly
if (empty($recommended_packages)) {
    $fallback = $conn->query("
        SELECT p.*, c.name as category_name,
               COUNT(DISTINCT b.id) as booking_count,
               AVG(cm.rating)       as avg_rating
        FROM packages p
        LEFT JOIN categories c  ON c.id  = p.category_id
        LEFT JOIN bookings b    ON b.package_id = p.id AND b.tourist_id != $tourist_id
        LEFT JOIN comments cm   ON cm.package_id = p.id AND cm.status = 'Approved'
        WHERE p.status = 'Active'
        GROUP BY p.id
        ORDER BY booking_count DESC, p.created_at DESC
        LIMIT 3
    ");
    while ($row = $fallback->fetch_assoc()) {
        $row['predicted_rating'] = round($row['avg_rating'] ?? 3.0, 2);
        $recommended_packages[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Make My Holiday</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Page Layout */
        .bookings-section {
            padding: 50px 0;
            background: #f8f9fa;
            min-height: calc(100vh - 200px);
        }

        /* Header */
        .bookings-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .bookings-header h1 { font-size: 32px; margin-bottom: 10px; color: #333; }
        .bookings-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box h3 { font-size: 32px; margin-bottom: 5px; }
        .stat-box p  { font-size: 14px; opacity: 0.9; }

        /* Booking Cards */
        .bookings-list { display: grid; gap: 20px; }
        .booking-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 200px 1fr auto;
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .booking-image { width: 100%; height: 100%; overflow: hidden; }
        .booking-image img { width: 100%; height: 100%; object-fit: cover; }
        .booking-details { padding: 25px; }
        .booking-details h3 { font-size: 22px; margin-bottom: 10px; color: #333; }
        .booking-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        .info-row { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #666; }
        .info-row i { color: #667eea; width: 20px; }
        .booking-actions {
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
            min-width: 160px;
        }

        /* Status Badges */
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending   { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-rejected  { background: #f8d7da; color: #721c24; }

        .booking-price {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-top: 15px;
        }

        /* Action Buttons */
        .action-btns { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; width: 100%; }
        .btn-view-pkg {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-view-pkg:hover { background: #667eea; color: white; }
        .btn-cancel-booking {
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-cancel-booking:hover { background: #e65100; color: white; }
        .btn-delete-booking {
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-delete-booking:hover { background: #c53030; color: white; }
        .action-rules {
            font-size: 11px;
            color: #aaa;
            margin-top: 6px;
            text-align: center;
            line-height: 1.5;
        }

        /* No Bookings */
        .no-bookings {
            background: white;
            padding: 80px 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-bookings i  { font-size: 80px; color: #ccc; margin-bottom: 20px; }
        .no-bookings h3 { font-size: 28px; margin-bottom: 10px; color: #666; }
        .no-bookings p  { color: #999; margin-bottom: 30px; }

        /* Recommendations Section */
        .recommendations-section { margin-top: 50px; }
        .recommendations-section h2 { font-size: 28px; margin-bottom: 10px; color: #333; }

        .algo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .booking-card    { grid-template-columns: 1fr; }
            .booking-image   { height: 200px; }
            .booking-info    { grid-template-columns: 1fr; }
            .booking-actions { flex-direction: row; justify-content: space-between; }
        }
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
                <li><a href="my_bookings.php" class="active">My Bookings</a></li>
                <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['tourist_name']); ?>)</a></li>
            </ul>
        </div>
    </nav>

    <section class="bookings-section">
        <div class="container">

            <!-- Flash Messages -->
            <?php $messages = getMessages(); ?>
            <?php if (isset($messages['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $messages['error']; ?>
                </div>
            <?php endif; ?>

            <!-- HEADER & STATS -->
            <div class="bookings-header">
                <h1><i class="fas fa-suitcase"></i> My Bookings</h1>
                <p style="color:#666;">Manage and view all your travel bookings</p>

                <?php
                $total = $bookings->num_rows;
                $bookings->data_seek(0);
                $pending = $confirmed = $completed = 0;
                while ($b = $bookings->fetch_assoc()) {
                    if ($b['status'] === 'Pending')       $pending++;
                    elseif ($b['status'] === 'Confirmed') $confirmed++;
                    elseif ($b['status'] === 'Completed') $completed++;
                }
                $bookings->data_seek(0);
                ?>

                <div class="bookings-stats">
                    <div class="stat-box"><h3><?php echo $total; ?></h3><p>Total Bookings</p></div>
                    <div class="stat-box"><h3><?php echo $pending; ?></h3><p>Pending</p></div>
                    <div class="stat-box"><h3><?php echo $confirmed; ?></h3><p>Confirmed</p></div>
                    <div class="stat-box"><h3><?php echo $completed; ?></h3><p>Completed</p></div>
                </div>
            </div>

            <!-- BOOKING CARDS -->
            <?php if ($bookings->num_rows > 0): ?>
                <div class="bookings-list">
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <div class="booking-card">

                        <!-- Image -->
                        <div class="booking-image">
                            <?php if ($booking['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $booking['image']; ?>" alt="Package">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/200x150" alt="Package">
                            <?php endif; ?>
                        </div>

                        <!-- Details -->
                        <div class="booking-details">
                            <h3><?php echo htmlspecialchars($booking['package_name']); ?></h3>
                            <p style="color:#666; font-size:14px; margin-bottom:15px;">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </p>
                            <div class="booking-info">
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span><strong>Booked:</strong> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar-check"></i>
                                    <span><strong>Start:</strong> <?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar-times"></i>
                                    <span><strong>End:</strong> <?php echo date('M d, Y', strtotime($booking['end_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-users"></i>
                                    <span><strong>Travelers:</strong> <?php echo $booking['num_travelers']; ?></span>
                                </div>
                            </div>
                            <?php if ($booking['special_requests']): ?>
                            <div style="margin-top:15px; padding:10px; background:#f8f9fa; border-radius:5px;">
                                <strong style="font-size:13px;">Special Requests:</strong>
                                <p style="font-size:13px; color:#666; margin-top:5px;">
                                    <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="booking-actions">
                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                <?php echo $booking['status']; ?>
                            </span>

                            <div class="booking-price">
                                Rs <?php echo number_format($booking['total_price'], 2); ?>
                            </div>

                            <div class="action-btns">
                                <a href="package_details.php?id=<?php echo $booking['package_id']; ?>"
                                   class="btn-view-pkg">
                                    <i class="fas fa-eye"></i> View Package
                                </a>

                                <?php if ($booking['status'] === 'Pending'): ?>
                                    <a href="my_bookings.php?cancel=<?php echo $booking['id']; ?>"
                                       class="btn-cancel-booking"
                                       onclick="return confirm('Cancel Booking #<?php echo $booking['id']; ?>?\nThis cannot be undone.')">
                                        <i class="fas fa-ban"></i> Cancel Booking
                                    </a>
                                <?php endif; ?>

                                <?php if (in_array($booking['status'], ['Cancelled', 'Completed', 'Rejected'])): ?>
                                    <a href="my_bookings.php?delete=<?php echo $booking['id']; ?>"
                                       class="btn-delete-booking"
                                       onclick="return confirm('Remove Booking #<?php echo $booking['id']; ?> from your list?\nThis cannot be undone.')">
                                        <i class="fas fa-trash"></i> Remove
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="action-rules">
                                <?php if ($booking['status'] === 'Pending'): ?>
                                    You can cancel pending bookings
                                <?php elseif ($booking['status'] === 'Confirmed'): ?>
                                    Contact us to make changes
                                <?php elseif (in_array($booking['status'], ['Cancelled', 'Completed', 'Rejected'])): ?>
                                    You can remove this from your list
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div class="no-bookings">
                    <i class="fas fa-suitcase"></i>
                    <h3>No Bookings Yet</h3>
                    <p>Start planning your dream vacation today!</p>
                    <a href="packages.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Browse Packages
                    </a>
                </div>
            <?php endif; ?>

            <!-- SECTION: CF RECOMMENDATIONS -->
            <?php if (!empty($recommended_packages)): ?>
            <div class="recommendations-section">

               
                <h2><i class="fas fa-star"></i> Recommended For You</h2>
                <p style="color:#666; margin-bottom:30px;">
                    Based on tourists with similar interests
                </p>

                <div class="packages-grid">
                    <?php foreach ($recommended_packages as $pkg): ?>
                    <div class="package-card">
                        <div class="package-image">
                            <?php if ($pkg['image']): ?>
                                <img src="<?php echo UPLOAD_URL . $pkg['image']; ?>" alt="Package">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x300" alt="Package">
                            <?php endif; ?>
                            <div class="featured-badge">
                                <i class="fas fa-magic"></i> Recommended
                            </div>
                        </div>
                        <div class="package-content">
                            <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                            <p class="package-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($pkg['location']); ?>
                            </p>
                            <p class="package-description">
                                <?php echo substr(htmlspecialchars($pkg['description']), 0, 100); ?>...
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
                            <?php if (!empty($pkg['predicted_rating'])): ?>
                            <div style="text-align:center; margin-bottom:10px;">
                                <?php
                                $score = $pkg['predicted_rating'];
                                $pct   = round(($score / 5) * 100);
                                $cls   = $pct >= 70 ? '#155724' : ($pct >= 50 ? '#856404' : '#721c24');
                                $bg    = $pct >= 70 ? '#d4edda' : ($pct >= 50 ? '#fff3cd' : '#f8d7da');
                                ?>
                                
                            </div>
                            <?php endif; ?>
                            <a href="package_details.php?id=<?php echo $pkg['id']; ?>"
                               class="btn btn-primary btn-block">
                                View Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

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
                    <p>Your trusted travel partner</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="packages.php">Packages</a></li>
                        <li><a href="my_bookings.php">My Bookings</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> +977-9876543210</p>
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