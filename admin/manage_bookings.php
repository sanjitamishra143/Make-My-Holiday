<?php
require_once '../config.php';
 
if (!isAdminLoggedIn()) {
    redirect('login.php');
}
 
$conn = getConnection();
 
// Get all bookings with related information
$bookings = $conn->query("
    SELECT b.*,
           p.name  as package_name,
           t.name  as tourist_name,
           t.email as tourist_email,
           t.phone as tourist_phone
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    JOIN tourists t ON b.tourist_id  = t.id
    ORDER BY b.created_at DESC
");
 
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        .btn-edit {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-edit:hover { background: #5a6fd6; color: white; }
 
        .btn-delete {
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-delete:hover { background: #c53030; color: white; }
 
        .action-buttons { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
 
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
 
        <div class="content">
            <h1><i class="fas fa-calendar-check"></i> Manage Bookings</h1>
 
            <?php
            $messages = getMessages();
            if (isset($messages['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $messages['error']; ?>
                </div>
            <?php endif; ?>
 
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tourist</th>
                            <th>Package</th>
                            <th>Booking Date</th>
                            <th>Travel Dates</th>
                            <th>Travelers</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings->num_rows > 0): ?>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['tourist_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['tourist_email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($booking['tourist_phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['package_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                <td>
                                    <strong>Start:</strong> <?php echo date('M d, Y', strtotime($booking['start_date'])); ?><br>
                                    <strong>End:</strong> <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                </td>
                                <td><?php echo $booking['num_travelers']; ?></td>
                                <td>Rs <?php echo number_format($booking['total_price'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo $booking['status']; ?>
                                    </span>
                                </td>
 
                                <!-- ── ACTIONS ── -->
                                <td class="action-buttons">
 
                                    <!-- Status actions -->
                                    <?php if ($booking['status'] == 'Pending'): ?>
                                        <a href="handle_booking.php?id=<?php echo $booking['id']; ?>&action=confirm"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Confirm this booking?')">
                                            <i class="fas fa-check"></i> Confirm
                                        </a>
                                        <a href="handle_booking.php?id=<?php echo $booking['id']; ?>&action=cancel"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Cancel this booking?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    <?php elseif ($booking['status'] == 'Confirmed'): ?>
                                        <a href="handle_booking.php?id=<?php echo $booking['id']; ?>&action=complete"
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Mark as completed?')">
                                            <i class="fas fa-check-double"></i> Complete
                                        </a>
                                    <?php endif; ?>
 
                                    <!-- Special requests -->
                                    <?php if ($booking['special_requests']): ?>
                                        <button class="btn btn-sm"
                                                style="background:#667eea; color:white;"
                                                onclick="alert('Special Requests:\n<?php echo addslashes($booking['special_requests']); ?>')">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    <?php endif; ?>
 
                                    <!-- ✏️ EDIT button (always visible) -->
                                    <a href="handle_booking.php?id=<?php echo $booking['id']; ?>&action=edit"
                                       class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
 
                                    <!-- 🗑️ DELETE button (always visible) -->
                                    <a href="handle_booking.php?id=<?php echo $booking['id']; ?>&action=delete"
                                       class="btn-delete"
                                       onclick="return confirm('⚠️ Permanently delete Booking #<?php echo $booking['id']; ?>?\nThis cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
 
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:50px; color:#999;">
                                    <i class="fas fa-calendar-times" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                                    <strong>No bookings found</strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>