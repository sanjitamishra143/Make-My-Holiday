<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// Get statistics
$packageCount = $conn->query("SELECT COUNT(*) as count FROM packages")->fetch_assoc()['count'];
$categoryCount = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$bookingCount = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$commentCount = $conn->query("SELECT COUNT(*) as count FROM comments WHERE status = 'Pending'")->fetch_assoc()['count'];

// Get recent bookings
$recentBookings = $conn->query("
    SELECT b.*, p.name as package_name, t.name as tourist_name 
    FROM bookings b 
    JOIN packages p ON b.package_id = p.id 
    JOIN tourists t ON b.tourist_id = t.id 
    ORDER BY b.created_at DESC 
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <h1>Dashboard Overview</h1>
            
            <?php 
            $messages = getMessages();
            if (isset($messages['success'])): 
            ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $packageCount; ?></h3>
                        <p>Packages</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $categoryCount; ?></h3>
                        <p>Categories</p>
                    </div>
                </div>
                
                <div class="stat-card yellow">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $bookingCount; ?></h3>
                        <p>Bookings</p>
                    </div>
                </div>
                
                <div class="stat-card teal">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $commentCount; ?></h3>
                        <p>Pending Comments</p>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <h2>Overview by Numbers</h2>
                <canvas id="overviewChart"></canvas>
            </div>
            
            <div class="recent-bookings">
                <h2>Recent Bookings</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Package</th>
                            <th>Tourist</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['package_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['tourist_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo $booking['status']; ?>
                                </span>
                            </td>
                            <td>Rs <?php echo number_format($booking['total_price'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('overviewChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Packages', 'Categories', 'Bookings', 'Comments'],
                datasets: [{
                    label: 'Count',
                    data: [<?php echo "$packageCount, $categoryCount, $bookingCount, $commentCount"; ?>],
                    backgroundColor: [
                        'rgba(33, 150, 243, 0.7)',
                        'rgba(76, 175, 80, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(0, 188, 212, 0.7)'
                    ],
                    borderColor: [
                        'rgba(33, 150, 243, 1)',
                        'rgba(76, 175, 80, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(0, 188, 212, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>