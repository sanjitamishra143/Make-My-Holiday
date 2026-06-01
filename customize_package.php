<?php
require_once 'config.php';
 
if (!isTouristLoggedIn()) {
    redirect('tourist_login.php');
}
 
$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
 
if ($package_id == 0) {
    redirect('packages.php');
}
 
$conn = getConnection();
 
// Get package details
$stmt = $conn->prepare("SELECT * FROM packages WHERE id = ? AND status = 'Active'");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();
 
if ($result->num_rows == 0) {
    redirect('packages.php');
}
 
$package = $result->fetch_assoc();
$error = '';
 
// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tourist_id       = $_SESSION['tourist_id'];
    $start_date       = $_POST['start_date'];
    $num_travelers    = intval($_POST['num_travelers']);
    $special_requests = sanitize($_POST['special_requests']);
 
    // Customizations
    $custom_duration       = isset($_POST['custom_duration'])       ? intval($_POST['custom_duration'])       : $package['duration'];
    $accommodation_type    = isset($_POST['accommodation_type'])    ? sanitize($_POST['accommodation_type'])  : 'standard';
    $meal_plan             = isset($_POST['meal_plan'])             ? sanitize($_POST['meal_plan'])            : 'breakfast';
    $additional_activities = isset($_POST['additional_activities']) ? $_POST['additional_activities']         : [];
 
    // ══════════════════════════════════════════════════════════════
    //  VALIDATION 1 — Check for duplicate active booking
    //  Same tourist + same package + status NOT cancelled/rejected
    // ══════════════════════════════════════════════════════════════
    $dup_stmt = $conn->prepare("
        SELECT id FROM bookings
        WHERE tourist_id = ?
          AND package_id = ?
          AND status NOT IN ('Cancelled', 'Rejected', 'Completed')
        LIMIT 1
    ");
    $dup_stmt->bind_param("ii", $tourist_id, $package_id);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
 
    if ($dup_result->num_rows > 0) {
        $error = "You already have an active booking for this package. 
                  You cannot book the same package twice. 
                  Please check <a href='my_bookings.php'>My Bookings</a>.";
        $dup_stmt->close();
        goto skip_booking; // jump past insert logic
    }
    $dup_stmt->close();
 
    // ══════════════════════════════════════════════════════════════
    //  VALIDATION 2 — Start date must be in the future
    // ══════════════════════════════════════════════════════════════
    if (strtotime($start_date) < strtotime('+1 day')) {
        $error = "Start date must be at least tomorrow.";
        goto skip_booking;
    }
 
    // ══════════════════════════════════════════════════════════════
    //  VALIDATION 3 — Number of travelers within allowed range
    // ══════════════════════════════════════════════════════════════
    if ($num_travelers < 1 || $num_travelers > $package['max_people']) {
        $error = "Number of travelers must be between 1 and " . $package['max_people'] . ".";
        goto skip_booking;
    }
 
    // ── Calculate price ───────────────────────────────────────────
    $base_price  = $package['price'];
    $custom_price = $base_price;
 
    if ($accommodation_type == 'deluxe') {
        $custom_price += ($base_price * 0.3);
    } elseif ($accommodation_type == 'luxury') {
        $custom_price += ($base_price * 0.5);
    }
 
    if ($meal_plan == 'half_board') {
        $custom_price += 2000;
    } elseif ($meal_plan == 'full_board') {
        $custom_price += 4000;
    }
 
    $activity_cost = count($additional_activities) * 1500;
    $custom_price += $activity_cost;
 
    if ($custom_duration != $package['duration']) {
        $duration_ratio = $custom_duration / $package['duration'];
        $custom_price   = $custom_price * $duration_ratio;
    }
 
    $total_price = $custom_price * $num_travelers;
    $end_date    = date('Y-m-d', strtotime($start_date . " + $custom_duration days"));
 
    $customizations = json_encode([
        'custom_duration'       => $custom_duration,
        'accommodation_type'    => $accommodation_type,
        'meal_plan'             => $meal_plan,
        'additional_activities' => $additional_activities,
        'base_price'            => $base_price,
        'custom_price'          => $custom_price,
    ]);
 
    // ── Insert custom_packages ────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO custom_packages
            (tourist_id, base_package_id, custom_name, custom_duration, custom_price, customizations, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Submitted')
    ");
    $custom_name = "Custom: " . $package['name'];
    $stmt->bind_param("iisids", $tourist_id, $package_id, $custom_name, $custom_duration, $custom_price, $customizations);
    $stmt->execute();
    $stmt->close();
 
    // ── Insert booking ────────────────────────────────────────────
    $booking_date = date('Y-m-d');
    $stmt = $conn->prepare("
        INSERT INTO bookings
            (package_id, tourist_id, booking_date, start_date, end_date, num_travelers, total_price, status, special_requests)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
    ");
    $stmt->bind_param("iisssiis", $package_id, $tourist_id, $booking_date, $start_date, $end_date, $num_travelers, $total_price, $special_requests);
 
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        setSuccessMessage("Booking submitted successfully! We'll contact you shortly.");
        redirect('my_bookings.php');
    } else {
        $error = "Booking failed! Please try again.";
    }
    $stmt->close();
 
    skip_booking:; // label for goto (skips insert on validation failure)
}
 
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Package - <?php echo htmlspecialchars($package['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .customize-section {
            padding: 50px 0;
            background: #f8f9fa;
        }
        .customize-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        .customize-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .form-section:last-child { border-bottom: none; }
        .form-section h3 { margin-bottom: 20px; color: #333; font-size: 18px; }
        .options-container { display: flex; gap: 20px; }
        .option-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 120px;
        }
        .option-card:hover  { border-color: #667eea; }
        .option-card.selected { border-color: #667eea; background: #f0f4ff; }
        .option-card input[type="radio"] { display: none; }
        .option-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .option-title  { font-weight: 600; color: #333; }
        .option-price  { color: #667eea; font-weight: 600; }
        .checkbox-group { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .checkbox-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .checkbox-card:hover { border-color: #667eea; }
        .checkbox-card input[type="checkbox"]:checked + label { color: #667eea; }
        .price-summary {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        .summary-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
        .summary-total { font-size: 20px; font-weight: 700; color: #667eea; padding-top: 15px; }
 
        /* ── Duplicate warning box ── */
        .alert-duplicate {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 5px solid #ff6b35;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-duplicate i { color: #ff6b35; font-size: 22px; margin-top: 2px; }
        .alert-duplicate strong { display: block; color: #856404; font-size: 15px; }
        .alert-duplicate span  { color: #856404; font-size: 14px; }
        .alert-duplicate a     { color: #667eea; font-weight: 600; }
 
        .alert-danger {
            background: #ffe0e0;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #e53e3e;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            color: #721c24;
            font-size: 14px;
        }
 
        @media (max-width: 968px) {
            .customize-grid { grid-template-columns: 1fr; }
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
                <li><a href="my_bookings.php">My Bookings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
 
    <section class="customize-section">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php">Home</a> /
                <a href="packages.php">Packages</a> /
                <a href="package_details.php?id=<?php echo $package['id']; ?>"><?php echo htmlspecialchars($package['name']); ?></a> /
                Customize
            </div>
 
            <h1 style="margin-top: 20px;">Customize Your Package</h1>
            <p style="color: #666; margin-bottom: 10px;">Tailor this package to your preferences</p>
 
            <?php if (!empty($error)): ?>
                <?php if (strpos($error, 'already have an active booking') !== false): ?>
                    <!-- Duplicate booking warning -->
                    <div class="alert-duplicate">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Duplicate Booking Detected!</strong>
                            <span><?php echo $error; ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
 
            <div class="customize-grid">
                <form method="POST" id="customizeForm" class="customize-form">
                    <!-- Basic Details -->
                    <div class="form-section">
                        <h3><i class="fas fa-calendar-alt"></i> Travel Details</h3>
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Number of Travelers *</label>
                            <input type="number" name="num_travelers" id="num_travelers" class="form-control"
                                   min="1" max="<?php echo $package['max_people']; ?>" value="1" required>
                        </div>
                    </div>
 
                    <!-- Duration -->
                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Duration</h3>
                        <div class="form-group">
                            <label>Customize Duration (days)</label>
                            <input type="number" name="custom_duration" id="custom_duration" class="form-control"
                                   min="<?php echo max(1, $package['duration'] - 2); ?>"
                                   max="<?php echo $package['duration'] + 5; ?>"
                                   value="<?php echo $package['duration']; ?>">
                            <small>Original package: <?php echo $package['duration']; ?> days</small>
                        </div>
                    </div>
 
                    <!-- Accommodation -->
                    <div class="form-section">
                        <h3><i class="fas fa-hotel"></i> Accommodation Type</h3>
                        <div class="options-container">
                            <label class="option-card" id="acc-standard">
                                <input type="radio" name="accommodation_type" value="standard" checked>
                                <div class="option-header">
                                    <span class="option-title">Standard</span>
                                    <span class="option-price">Included</span>
                                </div>
                                <p style="font-size:13px; color:#666;">Comfortable 3-star accommodation</p>
                            </label>
                            <label class="option-card" id="acc-deluxe">
                                <input type="radio" name="accommodation_type" value="deluxe">
                                <div class="option-header">
                                    <span class="option-title">Deluxe</span>
                                    <span class="option-price">+30%</span>
                                </div>
                                <p style="font-size:13px; color:#666;">Upgrade to 4-star hotels with premium amenities</p>
                            </label>
                            <label class="option-card" id="acc-luxury">
                                <input type="radio" name="accommodation_type" value="luxury">
                                <div class="option-header">
                                    <span class="option-title">Luxury</span>
                                    <span class="option-price">+50%</span>
                                </div>
                                <p style="font-size:13px; color:#666;">5-star luxury experience with exclusive services</p>
                            </label>
                        </div>
                    </div>
 
                    <!-- Meal Plan -->
                    <div class="form-section">
                        <h3><i class="fas fa-utensils"></i> Meal Plan</h3>
                        <div class="options-container">
                            <label class="option-card" id="meal-breakfast">
                                <input type="radio" name="meal_plan" value="breakfast" checked>
                                <div class="option-header">
                                    <span class="option-title">Breakfast Only</span>
                                    <span class="option-price">Included</span>
                                </div>
                            </label>
                            <label class="option-card" id="meal-half">
                                <input type="radio" name="meal_plan" value="half_board">
                                <div class="option-header">
                                    <span class="option-title">Half Board</span>
                                    <span class="option-price">+Rs 2,000</span>
                                </div>
                                <p style="font-size:13px; color:#666;">Breakfast + Dinner</p>
                            </label>
                            <label class="option-card" id="meal-full">
                                <input type="radio" name="meal_plan" value="full_board">
                                <div class="option-header">
                                    <span class="option-title">Full Board</span>
                                    <span class="option-price">+Rs 4,000</span>
                                </div>
                                <p style="font-size:13px; color:#666;">All meals included</p>
                            </label>
                        </div>
                    </div>
 
                    <!-- Activities -->
                    <div class="form-section">
                        <h3><i class="fas fa-hiking"></i> Additional Activities</h3>
                        <div class="checkbox-group">
                            <div class="checkbox-card">
                                <input type="checkbox" name="additional_activities[]" value="guided_tour" id="act1">
                                <label for="act1"><strong>Guided City Tour</strong><br><small>+Rs 1,500</small></label>
                            </div>
                            <div class="checkbox-card">
                                <input type="checkbox" name="additional_activities[]" value="adventure" id="act2">
                                <label for="act2"><strong>Adventure Sports</strong><br><small>+Rs 1,500</small></label>
                            </div>
                            <div class="checkbox-card">
                                <input type="checkbox" name="additional_activities[]" value="spa" id="act3">
                                <label for="act3"><strong>Spa Session</strong><br><small>+Rs 1,500</small></label>
                            </div>
                            <div class="checkbox-card">
                                <input type="checkbox" name="additional_activities[]" value="cultural" id="act4">
                                <label for="act4"><strong>Cultural Show</strong><br><small>+Rs 1,500</small></label>
                            </div>
                        </div>
                    </div>
 
                    <!-- Special Requests -->
                    <div class="form-section">
                        <h3><i class="fas fa-comment"></i> Special Requests</h3>
                        <textarea name="special_requests" class="form-control" rows="4"
                                  placeholder="Any special requests or dietary requirements..."></textarea>
                    </div>
 
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-check"></i> Confirm Booking
                    </button>
                </form>
 
                <!-- Price Summary -->
                <div>
                    <div class="price-summary">
                        <h3 style="margin-bottom:20px;">Price Summary</h3>
                        <div class="summary-item">
                            <span>Base Price</span>
                            <span id="basePrice">Rs <?php echo number_format($package['price'], 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Accommodation</span>
                            <span id="accPrice">Rs 0</span>
                        </div>
                        <div class="summary-item">
                            <span>Meal Plan</span>
                            <span id="mealPrice">Rs 0</span>
                        </div>
                        <div class="summary-item">
                            <span>Activities</span>
                            <span id="actPrice">Rs 0</span>
                        </div>
                        <div class="summary-item">
                            <span>Duration Adjustment</span>
                            <span id="durationPrice">Rs 0</span>
                        </div>
                        <div class="summary-item">
                            <span>Number of Travelers</span>
                            <span id="travelers">1</span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>Total Price</span>
                            <span id="totalPrice">Rs <?php echo number_format($package['price'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
 
    <script>
        const basePrice       = <?php echo $package['price']; ?>;
        const originalDuration = <?php echo $package['duration']; ?>;
 
        document.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    this.parentElement.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    calculatePrice();
                }
            });
        });
 
        document.querySelector('#acc-standard').classList.add('selected');
        document.querySelector('#meal-breakfast').classList.add('selected');
 
        function calculatePrice() {
            let price = basePrice;
 
            const acc = document.querySelector('input[name="accommodation_type"]:checked').value;
            let accCost = 0;
            if (acc === 'deluxe')  { accCost = basePrice * 0.3; price += accCost; }
            if (acc === 'luxury')  { accCost = basePrice * 0.5; price += accCost; }
            document.getElementById('accPrice').textContent = 'Rs ' + accCost.toFixed(2);
 
            const meal = document.querySelector('input[name="meal_plan"]:checked').value;
            let mealCost = 0;
            if (meal === 'half_board') { mealCost = 2000; price += mealCost; }
            if (meal === 'full_board') { mealCost = 4000; price += mealCost; }
            document.getElementById('mealPrice').textContent = 'Rs ' + mealCost.toFixed(2);
 
            const activities = document.querySelectorAll('input[name="additional_activities[]"]:checked').length;
            const actCost = activities * 1500;
            price += actCost;
            document.getElementById('actPrice').textContent = 'Rs ' + actCost.toFixed(2);
 
            const duration     = parseInt(document.getElementById('custom_duration').value) || originalDuration;
            const durationRatio = duration / originalDuration;
            const durationAdj  = (price - basePrice) * (durationRatio - 1);
            price = price * durationRatio;
            document.getElementById('durationPrice').textContent = 'Rs ' + durationAdj.toFixed(2);
 
            const travelers = parseInt(document.getElementById('num_travelers').value) || 1;
            document.getElementById('travelers').textContent = travelers;
 
            document.getElementById('totalPrice').textContent = 'Rs ' + (price * travelers).toFixed(2);
        }
 
        document.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(i => {
            i.addEventListener('change', calculatePrice);
        });
        document.getElementById('custom_duration').addEventListener('input', calculatePrice);
        document.getElementById('num_travelers').addEventListener('input', calculatePrice);
 
        calculatePrice();
    </script>
 
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Make My Holiday. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>