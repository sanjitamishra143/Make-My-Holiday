<?php
require_once '../config.php';
 
if (!isAdminLoggedIn()) {
    redirect('login.php');
}
 
$conn = getConnection();
 
// ── EDIT Tourist (POST) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id      = intval($_POST['id']);
    $name    = sanitize($_POST['name']);
    $email   = sanitize($_POST['email']);
    $phone   = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
 
    // Check if email is taken by another tourist
    $check = $conn->prepare("SELECT id FROM tourists WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        setErrorMessage("Email '$email' is already used by another tourist.");
        $check->close();
        redirect('tourists.php');
    }
    $check->close();
 
    // Update password only if provided
    if (!empty($_POST['password'])) {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE tourists SET name=?, email=?, phone=?, address=?, password=? WHERE id=?");
        $stmt->bind_param("sssssi", $name, $email, $phone, $address, $hashed, $id);
    } else {
        $stmt = $conn->prepare("UPDATE tourists SET name=?, email=?, phone=?, address=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $address, $id);
    }
 
    if ($stmt->execute()) {
        setSuccessMessage("Tourist #$id updated successfully.");
    } else {
        setErrorMessage("Failed to update tourist.");
    }
    $stmt->close();
    $conn->close();
    redirect('tourists.php');
}
 
// ── DELETE Tourist ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
 
    // Check if tourist has bookings
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE tourist_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();
 
    if ($cnt > 0) {
        setErrorMessage("Cannot delete tourist #$id — they have $cnt booking(s) on record.");
    } else {
        $stmt = $conn->prepare("DELETE FROM tourists WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setSuccessMessage("Tourist #$id deleted successfully.");
        } else {
            setErrorMessage("Failed to delete tourist.");
        }
        $stmt->close();
    }
    $conn->close();
    redirect('tourists.php');
}
 
// ── Fetch all tourists ────────────────────────────────────────────
$tourists = $conn->query("
    SELECT t.*,
           COUNT(DISTINCT b.id) as booking_count,
           SUM(b.total_price)   as total_spent
    FROM tourists t
    LEFT JOIN bookings b ON t.id = b.tourist_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
 
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tourists - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        /* ── Action buttons ── */
        .btn-edit-tourist {
            background: #667eea;
            color: white; border: none; border-radius: 6px;
            padding: 6px 12px; font-size: 12px; font-family: inherit;
            text-decoration: none; display: inline-flex; align-items: center;
            gap: 4px; cursor: pointer; transition: background 0.2s;
        }
        .btn-edit-tourist:hover { background: #5a6fd6; color: white; }
 
        .btn-delete-tourist {
            background: #e53e3e;
            color: white; border: none; border-radius: 6px;
            padding: 6px 12px; font-size: 12px; font-family: inherit;
            text-decoration: none; display: inline-flex; align-items: center;
            gap: 4px; cursor: pointer; transition: background 0.2s;
        }
        .btn-delete-tourist:hover { background: #c53030; color: white; }
 
        .action-buttons { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
 
        /* ── Modal ── */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal.show { display: flex; align-items: center; justify-content: center; }
 
        .modal-box {
            background: white;
            border-radius: 14px;
            width: 95%;
            max-width: 520px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideIn 0.2s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
 
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .modal-close {
            background: none; border: none; color: white;
            font-size: 22px; cursor: pointer; line-height: 1;
            padding: 0 4px;
        }
        .modal-close:hover { opacity: 0.7; }
 
        .modal-body { padding: 28px 24px; }
 
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #555;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input {
            width: 100%;
            border: 1px solid #dde0e8;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            background: #fafbfc;
            transition: border 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus { border-color: #667eea; background: white; }
        .form-group small { font-size: 11px; color: #aaa; margin-top: 4px; display: block; }
 
        .modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 8px;
            padding: 10px 26px; font-size: 14px; font-weight: 600;
            font-family: inherit; cursor: pointer; transition: opacity 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-save:hover { opacity: 0.9; }
        .btn-cancel-modal {
            background: #6c757d; color: white; border: none; border-radius: 8px;
            padding: 10px 20px; font-size: 14px; font-family: inherit;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-cancel-modal:hover { background: #5a6268; }
 
        /* Tourist avatar */
        .tourist-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 16px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
 
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
 
        <div class="content">
            <h1><i class="fas fa-users"></i> Registered Tourists</h1>
 
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Bookings</th>
                            <th>Total Spent</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tourists->num_rows > 0): ?>
                            <?php while ($tourist = $tourists->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $tourist['id']; ?></strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="tourist-avatar">
                                            <?php echo strtoupper(substr($tourist['name'], 0, 1)); ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($tourist['name']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-envelope" style="color:#667eea;"></i>
                                    <?php echo htmlspecialchars($tourist['email']); ?>
                                </td>
                                <td>
                                    <?php if ($tourist['phone']): ?>
                                        <i class="fas fa-phone" style="color:#667eea;"></i>
                                        <?php echo htmlspecialchars($tourist['phone']); ?>
                                    <?php else: ?>
                                        <span style="color:#999;">Not provided</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($tourist['address'] ?: 'Not provided'); ?></td>
                                <td>
                                    <span class="badge" style="background:#667eea; color:white; padding:5px 12px;">
                                        <?php echo $tourist['booking_count']; ?> bookings
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:#667eea; font-size:16px;">
                                        Rs <?php echo number_format($tourist['total_spent'] ?: 0, 2); ?>
                                    </strong>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($tourist['created_at'])); ?></td>
 
                                <!-- ── ACTIONS ── -->
                                <td class="action-buttons">
                                    <!-- ✏️ Edit -->
                                    <button class="btn-edit-tourist"
                                        onclick="openEditModal(
                                            <?php echo $tourist['id']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($tourist['name'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($tourist['email'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($tourist['phone'] ?? '')); ?>',
                                            '<?php echo htmlspecialchars(addslashes($tourist['address'] ?? '')); ?>'
                                        )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
 
                                    <!-- 🗑️ Delete -->
                                    <a href="tourists.php?delete=<?php echo $tourist['id']; ?>"
                                       class="btn-delete-tourist"
                                       onclick="return confirm('Delete tourist: <?php echo htmlspecialchars(addslashes($tourist['name'])); ?>?\n\nThis will fail if they have bookings.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:50px; color:#999;">
                                    <i class="fas fa-users-slash" style="font-size:64px; display:block; margin-bottom:15px; color:#ddd;"></i>
                                    <h3 style="color:#666; margin-bottom:10px;">No tourists registered yet</h3>
                                    <p>Tourist registrations will appear here</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
 
    <!-- ══ EDIT TOURIST MODAL ════════════════════════════════════ -->
    <div id="editModal" class="modal" onclick="closeOnOutsideClick(event)">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Tourist</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
 
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id"     id="edit_id">
 
                <div class="modal-body">
 
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="name" id="edit_name"
                                   placeholder="Full name" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" id="edit_email"
                                   placeholder="Email address" required>
                        </div>
                    </div>
 
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" name="phone" id="edit_phone"
                                   placeholder="Phone number">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" id="edit_address"
                                   placeholder="Address">
                        </div>
                    </div>
 
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="password" id="edit_password"
                               placeholder="Leave blank to keep current password">
                        <small>Only fill this if you want to reset the tourist's password.</small>
                    </div>
 
                </div>
 
                <div class="modal-footer">
                    <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <script>
        function openEditModal(id, name, email, phone, address) {
            document.getElementById('edit_id').value      = id;
            document.getElementById('edit_name').value    = name;
            document.getElementById('edit_email').value   = email;
            document.getElementById('edit_phone').value   = phone;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_password').value = '';
            document.getElementById('editModal').classList.add('show');
        }
 
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
 
        function closeOnOutsideClick(event) {
            if (event.target === document.getElementById('editModal')) {
                closeEditModal();
            }
        }
 
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>
</body>
</html>