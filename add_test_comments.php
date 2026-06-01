<!-- <?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Test Comments</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Add Test Comments</h1>";

$conn = getConnection();

// Check if we have tourists and packages
$tourist_check = $conn->query("SELECT COUNT(*) as count FROM tourists");
$tourist_count = $tourist_check->fetch_assoc()['count'];

$package_check = $conn->query("SELECT COUNT(*) as count FROM packages WHERE status = 'Active'");
$package_count = $package_check->fetch_assoc()['count'];

if ($tourist_count == 0) {
    echo "<div class='error'>❌ <strong>No tourists found!</strong><br>";
    echo "You need to register at least one tourist first.<br>";
    echo "<a href='tourist_register.php' class='btn'>Register Tourist</a></div>";
    exit;
}

if ($package_count == 0) {
    echo "<div class='error'>❌ <strong>No active packages found!</strong><br>";
    echo "You need to add at least one package first.<br>";
    echo "<a href='admin/add_package.php' class='btn'>Add Package</a></div>";
    exit;
}

// Get first tourist and package
$tourist = $conn->query("SELECT * FROM tourists LIMIT 1")->fetch_assoc();
$package = $conn->query("SELECT * FROM packages WHERE status = 'Active' LIMIT 1")->fetch_assoc();

if (isset($_GET['add']) && $_GET['add'] === 'yes') {
    $comments_added = 0;
    
    // Sample comments
    $test_comments = [
        [
            'rating' => 5,
            'comment' => 'Amazing experience! The package was well organized and the accommodations were excellent. Highly recommend!',
            'status' => 'Pending'
        ],
        [
            'rating' => 4,
            'comment' => 'Great trip overall. The locations were beautiful and the guide was very knowledgeable. Would definitely book again.',
            'status' => 'Pending'
        ],
        [
            'rating' => 5,
            'comment' => 'Perfect vacation! Everything exceeded our expectations. The itinerary was perfect and we had an unforgettable time.',
            'status' => 'Approved'
        ],
        [
            'rating' => 3,
            'comment' => 'Good package but could be improved. The transportation was a bit delayed but overall it was a nice experience.',
            'status' => 'Pending'
        ],
        [
            'rating' => 5,
            'comment' => 'Absolutely wonderful! From booking to the end of the trip, everything was smooth. The team was very professional.',
            'status' => 'Approved'
        ],
        [
            'rating' => 2,
            'comment' => 'Not satisfied with the service. The hotel was not as described and some activities were cancelled.',
            'status' => 'Rejected'
        ],
        [
            'rating' => 4,
            'comment' => 'Really enjoyed the trip. Beautiful destinations and friendly staff. Minor issues with timings but overall great value.',
            'status' => 'Pending'
        ]
    ];
    
    foreach ($test_comments as $test_comment) {
        $stmt = $conn->prepare("INSERT INTO comments (package_id, tourist_id, rating, comment, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $package['id'], $tourist['id'], $test_comment['rating'], $test_comment['comment'], $test_comment['status']);
        
        if ($stmt->execute()) {
            $comments_added++;
        }
        $stmt->close();
    }
    
    echo "<div class='success'>";
    echo "✅ <strong>Success!</strong><br>";
    echo "Added <strong>$comments_added</strong> test comments to the database.<br><br>";
    echo "<strong>Details:</strong><br>";
    echo "Tourist: " . htmlspecialchars($tourist['name']) . " (" . htmlspecialchars($tourist['email']) . ")<br>";
    echo "Package: " . htmlspecialchars($package['name']) . "<br>";
    echo "</div>";
    
    echo "<a href='admin/moderate_comments.php' class='btn'>View Comments in Admin Panel</a>";
    echo "<a href='?' class='btn' style='background: #666;'>Back</a>";
} else {
    echo "<p>This will add <strong>7 sample comments</strong> with different ratings and statuses:</p>";
    echo "<ul>";
    echo "<li>✅ 3 Pending comments (need moderation)</li>";
    echo "<li>✅ 2 Approved comments</li>";
    echo "<li>✅ 1 Rejected comment</li>";
    echo "<li>✅ Mix of 5-star, 4-star, 3-star, and 2-star ratings</li>";
    echo "</ul>";
    
    echo "<h3>Current Data:</h3>";
    echo "<table>";
    echo "<tr><th>Type</th><th>Details</th></tr>";
    echo "<tr><td>Tourist</td><td>" . htmlspecialchars($tourist['name']) . " (" . htmlspecialchars($tourist['email']) . ")</td></tr>";
    echo "<tr><td>Package</td><td>" . htmlspecialchars($package['name']) . "</td></tr>";
    echo "<tr><td>Registered Tourists</td><td>" . $tourist_count . "</td></tr>";
    echo "<tr><td>Active Packages</td><td>" . $package_count . "</td></tr>";
    echo "</table>";
    
    echo "<div style='margin-top: 30px;'>";
    echo "<a href='?add=yes' class='btn' style='font-size: 18px;'>➕ Add Test Comments Now</a>";
    echo "</div>";
}

$conn->close();

echo "</div></body></html>";
?> -->