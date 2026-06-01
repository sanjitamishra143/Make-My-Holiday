<?php
require_once '../config.php';
 
if (!isAdminLoggedIn()) {
    redirect('login.php');
}
 
$conn = getConnection();
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
 
if ($id == 0) {
    redirect('manage_bookings.php');
}
 
// ── Status change actions ─────────────────────────────────────────
$action = $_GET['action'] ?? '';
$allowed_actions = ['confirm' => 'Confirmed', 'cancel' => 'Cancelled', 'complete' => 'Completed'];
 
if (isset($allowed_actions[$action])) {
    $new_status = $allowed_actions[$action];
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    if ($stmt->execute()) {
        setSuccessMessage("Booking #$id status updated to $new_status.");
    } else {
        setErrorMessage("Failed to update booking status.");
    }
    $stmt->close();
    $conn->close();
    redirect('manage_bookings.php');
}
 
// ── DELETE booking ────────────────────────────────────────────────
if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setSuccessMessage("Booking #$id has been deleted successfully.");
    } else {
        setErrorMessage("Failed to delete booking.");
    }
    $stmt->close();
    $conn->close();
    redirect('manage_bookings.php');
}
 
// ── EDIT booking (POST) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $start_date       = $_POST['start_date'];
    $end_date         = $_POST['end_date'];
    $num_travelers    = intval($_POST['num_travelers']);
    $total_price      = floatval($_POST['total_price']);
    $status           = sanitize($_POST['status']);
    $special_requests = sanitize($_POST['special_requests']);
 
    $stmt = $conn->prepare("
        UPDATE bookings
        SET start_date = ?, end_date = ?, num_travelers = ?,
            total_price = ?, status = ?, special_requests = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssiidsi", $start_date, $end_date, $num_travelers, $total_price, $status, $special_requests, $id);
 
    if ($stmt->execute()) {
        setSuccessMessage("Booking #$id updated successfully.");
    } else {
        setErrorMessage("Failed to update booking.");
    }
    $stmt->close();
    $conn->close();
    redirect('manage_bookings.php');
}
 
// ── Load booking for edit form ────────────────────────────────────
if ($action === 'edit') {
    $stmt = $conn->prepare("
        SELECT b.*, p.name as package_name, t.name as tourist_name
        FROM bookings b
        JOIN packages p ON b.package_id = p.id
        JOIN tourists t ON b.tourist_id = t.id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
 
    if (!$booking) {
        setErrorMessage("Booking not found.");
        $conn->close();
        redirect('manage_bookings.php');
    }
} else {
    $conn->close();
    redirect('manage_bookings.php');
}
 
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking #<?php echo $id; ?> - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        .edit-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            max-width: 700px;
        }
        .edit-card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edit-card-body { padding: 30px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #555;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid #dde0e8;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border 0.2s;
            background: #fafbfc;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #667eea;
            background: white;
        }
        .info-box {
            background: #f0f4ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #4338ca;
        }
        .info-box strong { display: block; margin-bottom: 4px; }
        .btn-row { display: flex; gap: 12px; margin-top: 10px; }
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 8px;
            padding: 11px 28px; font-size: 14px; font-weight: 600;
            font-family: inherit; cursor: pointer; transition: opacity 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-save:hover { opacity: 0.9; }
        .btn-back {
            background: #6c757d; color: white; border: none; border-radius: 8px;
            padding: 11px 24px; font-size: 14px; font-weight: 500;
            font-family: inherit; cursor: pointer; text-decoration: none;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { background: #5a6268; color: white; }
 
        /* Status select colors */
        select option[value="Pending"]   { background: #fff3cd; }
        select option[value="Confirmed"] { background: #d1ecf1; }
        select option[value="Completed"] { background: #d4edda; }
        select option[value="Cancelled"] { background: #f8d7da; }
        select option[value="Rejected"]  { background: #f8d7da; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
 
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
 
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                <h1><i class="fas fa-edit"></i> Edit Booking #<?php echo $id; ?></h1>
                <a href="manage_bookings.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
 
            <div class="edit-card">
                <div class="edit-card-header">
                    <i class="fas fa-calendar-edit"></i>
                    Edit Booking Details
                </div>
                <div class="edit-card-body">
 
                    <!-- Info summary -->
                    <div class="info-box">
                        <strong>📦 <?php echo htmlspecialchars($booking['package_name']); ?></strong>
                        Tourist: <?php echo htmlspecialchars($booking['tourist_name']); ?> &nbsp;|&nbsp;
                        Booked on: <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                    </div>
 
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
 
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                                <input type="date" name="start_date"
                                       value="<?php echo $booking['start_date']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-times"></i> End Date</label>
                                <input type="date" name="end_date"
                                       value="<?php echo $booking['end_date']; ?>" required>
                            </div>
                        </div>
 
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-users"></i> Number of Travelers</label>
                                <input type="number" name="num_travelers" min="1"
                                       value="<?php echo $booking['num_travelers']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-rupee-sign"></i> Total Price (Rs)</label>
                                <input type="number" name="total_price" step="0.01" min="0"
                                       value="<?php echo $booking['total_price']; ?>" required>
                            </div>
                        </div>
 
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Booking Status</label>
                            <select name="status" required>
                                <?php
                                $statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'Rejected'];
                                foreach ($statuses as $s):
                                ?>
                                    <option value="<?php echo $s; ?>"
                                        <?php echo $booking['status'] === $s ? 'selected' : ''; ?>>
                                        <?php echo $s; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
 
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Special Requests</label>
                            <textarea name="special_requests" rows="4"
                                      placeholder="Any special requests..."><?php echo htmlspecialchars($booking['special_requests'] ?? ''); ?></textarea>
                        </div>
 
                        <div class="btn-row">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="manage_bookings.php" class="btn-back">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
 
                </div>
            </div>
        </div>
    </div>
</body>
</html>