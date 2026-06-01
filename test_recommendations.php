<?php
// Make sure config.php is properly loaded
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("Error: config.php not found! Please make sure it exists in the same folder.");
}

// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if tourist is logged in
if (!isset($_SESSION['tourist_id'])) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Login Required</title>
        <style>
            body { font-family: Arial; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; }
            .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🔒 Login Required</h1>
            <p>You must be logged in as a tourist to test recommendations.</p>
            <a href="tourist_login.php" class="btn">Login</a>
            <a href="tourist_register.php" class="btn" style="background: #28a745;">Register</a>
        </div>
    </body>
    </html>';
    exit;
}

$tourist_id = $_SESSION['tourist_id'];
$conn = getConnection();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Cosine Similarity Recommendations</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #667eea; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .vector { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
        .score { font-size: 20px; font-weight: bold; color: #28a745; }
        .match-high { background: #d4edda; }
        .match-medium { background: #fff3cd; }
        .match-low { background: #f8d7da; }
        .info-box { background: #e8f4f8; padding: 20px; border-radius: 8px; border-left: 4px solid #0288d1; margin: 20px 0; }
        .warning-box { background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 Cosine Similarity Recommendation Test</h1>
    <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['tourist_name']); ?> (ID: <?php echo $tourist_id; ?>)</p>

    <h2>📊 Step 1: Your Preference Vector</h2>
    <?php
    $userVector = getUserPreferenceVector($tourist_id);
    
    if (empty($userVector)):
    ?>
        <div class="warning-box">
            <strong>⚠️ No preferences found!</strong><br><br>
            <strong>Reason:</strong> You haven't booked any packages yet.<br><br>
            <strong>How it works:</strong> When you book a package, the system learns your preferences from that package's features (Adventure, Beach, Cultural, etc.). The more you book, the better recommendations you get!<br><br>
            <strong>What to do:</strong>
            <ol style="text-align: left; margin-top: 10px;">
                <li>Browse available packages</li>
                <li>Book at least one package</li>
                <li>Come back to see your personalized recommendations!</li>
            </ol>
            <a href="packages.php" class="btn btn-success">📦 Browse Packages</a>
        </div>
    <?php else: ?>
        <p>✅ <strong>Great! The system has learned your preferences from your booking history.</strong></p>
        <div class="vector">
            <strong>Your Preference Profile:</strong><br><br>
            <?php foreach ($userVector as $key => $value): ?>
                <div style="margin-bottom: 5px;">
                    <strong><?php echo str_replace('_', ' → ', ucfirst($key)); ?>:</strong> 
                    <span style="color: #28a745;"><?php echo number_format($value, 2); ?></span>
                    <div style="background: #e0e0e0; height: 20px; width: 100%; border-radius: 10px; display: inline-block; width: 200px; vertical-align: middle;">
                        <div style="background: #28a745; height: 20px; width: <?php echo ($value * 100); ?>%; border-radius: 10px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p><strong>Total preference features:</strong> <?php echo count($userVector); ?></p>
    <?php endif; ?>

    <h2>🎯 Step 2: Recommended Packages (Top 5)</h2>
    <?php
    $recommendations = getRecommendedPackages($tourist_id, 5);
    
    if (empty($recommendations)):
    ?>
        <div class="warning-box">
            <strong>⚠️ No packages available for recommendations.</strong><br>
            Please ask admin to add some packages first.
        </div>
    <?php else: ?>
        <table>
            <tr>
                <th>Rank</th>
                <th>Package Name</th>
                <th>Location</th>
                <th>Price</th>
                <th>Similarity Score</th>
                <th>Match Level</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($recommendations as $index => $pkg): ?>
                <?php
                $packageVector = getPackageFeatureVector($pkg['id']);
                
                // Calculate similarity
                if (!empty($userVector)) {
                    $similarity = calculateCosineSimilarity($userVector, $packageVector);
                    $score = round($similarity * 100, 2);
                } else {
                    $score = 0;
                }
                
                $matchClass = 'match-low';
                $matchLevel = 'Low Match';
                if ($score >= 70) {
                    $matchClass = 'match-high';
                    $matchLevel = '🔥 High Match';
                } elseif ($score >= 40) {
                    $matchClass = 'match-medium';
                    $matchLevel = '⭐ Medium Match';
                }
                ?>
                <tr class="<?php echo $matchClass; ?>">
                    <td><strong>#<?php echo $index + 1; ?></strong></td>
                    <td><?php echo htmlspecialchars($pkg['name']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['location']); ?></td>
                    <td>Rs <?php echo number_format($pkg['price'], 2); ?></td>
                    <td class="score"><?php echo $score; ?>%</td>
                    <td><?php echo $matchLevel; ?></td>
                    <td>
                        <a href="package_details.php?id=<?php echo $pkg['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>📦 Step 3: All Packages Similarity Analysis</h2>
    <?php
    $allPackages = $conn->query("SELECT * FROM packages WHERE status = 'Active' ORDER BY name");
    
    if ($allPackages->num_rows == 0):
    ?>
        <p>No active packages found in database.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Package Name</th>
                <th>Package Features</th>
                <th>Similarity Score</th>
                <th>Match %</th>
                <th>Recommendation</th>
            </tr>
            <?php 
            $packageScores = [];
            while ($pkg = $allPackages->fetch_assoc()):
    $pkgVector = getPackageFeatureVector($pkg['id']);
    
    if (!empty($userVector)) {
        $sim = calculateCosineSimilarity($userVector, $pkgVector);
    } else {
        $sim = 0;
    }
    
    $percentage = round($sim * 100, 2);

    $packageScores[] = [
        'package' => $pkg,
        'score' => $percentage,
        'vector' => $pkgVector
    ];
endwhile;

            
            // Sort by score
            usort($packageScores, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            foreach ($packageScores as $item):
                $pkg = $item['package'];
                $percentage = $item['score'];
                $pkgVector = $item['vector'];
                
                if ($percentage >= 70) {
                    $recommendation = '🔥 Highly Recommended';
                    $rowClass = 'match-high';
                } elseif ($percentage >= 40) {
                    $recommendation = '⭐ Recommended';
                    $rowClass = 'match-medium';
                } else {
                    $recommendation = '💡 Consider';
                    $rowClass = 'match-low';
                }
            ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><strong><?php echo htmlspecialchars($pkg['name']); ?></strong></td>
                    <td style="font-size: 11px;">
                        <?php 
                        $features = array_keys($pkgVector);
                        echo !empty($features) ? implode(', ', array_map('ucfirst', $features)) : 'No features'; 
                        ?>
                    </td>
                    <td><?php echo number_format($sim, 4); ?></td>
                    <td><strong><?php echo $percentage; ?>%</strong></td>
                    <td><?php echo $recommendation; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>🧮 Step 4: Understanding the Algorithm</h2>
    <div class="info-box">
        <h3>Cosine Similarity Formula:</h3>
        <p style="font-size: 18px; font-family: monospace; background: white; padding: 10px; border-radius: 5px;">
            similarity = (A · B) / (||A|| × ||B||)
        </p>
        <ul>
            <li><strong>A · B</strong> = Dot product of your preferences and package features</li>
            <li><strong>||A||</strong> = Magnitude of your preference vector</li>
            <li><strong>||B||</strong> = Magnitude of package feature vector</li>
        </ul>
        <p><strong>Result:</strong> Value between 0 (no similarity) and 1 (identical)</p>
        <p><strong>Percentage:</strong> Multiply by 100 to get match percentage</p>
        <br>
        <strong>Example Calculation:</strong><br>
        <code>If similarity = 0.85, then match = 85%</code>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3>📚 Quick Actions</h3>
        <a href="recommendations.php" class="btn">🎯 View Recommendations Page</a>
        <a href="packages.php" class="btn btn-success">📦 Browse All Packages</a>
        <a href="my_bookings.php" class="btn btn-warning">📋 My Bookings</a>
        <a href="index.php" class="btn" style="background: #6c757d;">🏠 Home</a>
    </div>

    <div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-radius: 5px; border-left: 4px solid #0c5460;">
        <strong>💡 Tip:</strong> The more packages you book, the better the recommendations become! The algorithm learns from your choices and finds packages that match your preferences.
    </div>

    <?php $conn->close(); ?>
</div>
</body>
</html>