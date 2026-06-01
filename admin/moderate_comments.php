<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// Handle Approve
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE comments SET status = 'Approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        setSuccessMessage("Comment approved successfully!");
    } else {
        setErrorMessage("Failed to approve comment!");
    }
    $stmt->close();
    redirect('moderate_comments.php');
}

// Handle Reject
if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE comments SET status = 'Rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        setSuccessMessage("Comment rejected successfully!");
    } else {
        setErrorMessage("Failed to reject comment!");
    }
    $stmt->close();
    redirect('moderate_comments.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        setSuccessMessage("Comment deleted successfully!");
    } else {
        setErrorMessage("Failed to delete comment!");
    }
    $stmt->close();
    redirect('moderate_comments.php');
}

// Get filter
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';

// Build query
$where = "1=1";
if ($filter === 'pending') {
    $where = "c.status = 'Pending'";
} elseif ($filter === 'approved') {
    $where = "c.status = 'Approved'";
} elseif ($filter === 'rejected') {
    $where = "c.status = 'Rejected'";
}

// Get comments
$comments = $conn->query("
    SELECT c.*, 
           t.name as tourist_name, 
           t.email as tourist_email,
           p.name as package_name
    FROM comments c
    JOIN tourists t ON c.tourist_id = t.id
    JOIN packages p ON c.package_id = p.id
    WHERE $where
    ORDER BY c.created_at DESC
");

// Get counts
$pending_count = $conn->query("SELECT COUNT(*) as count FROM comments WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM comments WHERE status = 'Approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM comments WHERE status = 'Rejected'")->fetch_assoc()['count'];
$total_count = $pending_count + $approved_count + $rejected_count;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Comments - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        .filter-tab {
            padding: 12px 24px;
            background: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            text-decoration: none;
        }
        .filter-tab:hover {
            color: #667eea;
        }
        .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .comment-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .commenter-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .commenter-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }
        .rating-stars i {
            margin-right: 2px;
        }
        .comment-text {
            color: #333;
            line-height: 1.8;
            margin: 15px 0;
        }
        .comment-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <h1><i class="fas fa-comments"></i> Moderate Comments & Reviews</h1>
            
            <?php 
            $messages = getMessages();
            if (isset($messages['success'])): 
            ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $messages['success']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $messages['error']; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All (<?php echo $total_count; ?>)
                </a>
                <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $approved_count; ?>)
                </a>
                <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $rejected_count; ?>)
                </a>
            </div>
            
            <!-- Comments List -->
            <?php if ($comments->num_rows > 0): ?>
                <?php while ($comment = $comments->fetch_assoc()): ?>
                <div class="comment-card">
                    <div class="comment-header">
                        <div class="commenter-info">
                            <div class="commenter-avatar">
                                <?php echo strtoupper(substr($comment['tourist_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong style="font-size: 16px; display: block;"><?php echo htmlspecialchars($comment['tourist_name']); ?></strong>
                                <small style="color: #999;">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($comment['tourist_email']); ?>
                                </small>
                                <br>
                                <small style="color: #999;">
                                    <i class="fas fa-box"></i> <?php echo htmlspecialchars($comment['package_name']); ?>
                                </small>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $comment['rating'] ? '' : 'far'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small style="color: #999; display: block; margin-top: 5px;">
                                <?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?>
                            </small>
                            <span class="badge badge-<?php echo strtolower($comment['status']); ?>" style="margin-top: 5px;">
                                <?php echo $comment['status']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                    </div>
                    
                    <div class="comment-actions">
                        <?php if ($comment['status'] === 'Pending'): ?>
                            <a href="?approve=<?php echo $comment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-success btn-sm"
                               onclick="return confirm('Approve this comment?')">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="?reject=<?php echo $comment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-warning btn-sm"
                               onclick="return confirm('Reject this comment?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        <?php elseif ($comment['status'] === 'Approved'): ?>
                            <a href="?reject=<?php echo $comment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-warning btn-sm"
                               onclick="return confirm('Reject this comment?')">
                                <i class="fas fa-ban"></i> Reject
                            </a>
                        <?php elseif ($comment['status'] === 'Rejected'): ?>
                            <a href="?approve=<?php echo $comment['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn btn-success btn-sm"
                               onclick="return confirm('Approve this comment?')">
                                <i class="fas fa-check"></i> Approve
                            </a>
                        <?php endif; ?>
                        
                        <a href="?delete=<?php echo $comment['id']; ?>&filter=<?php echo $filter; ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Permanently delete this comment?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 80px 20px;">
                    <i class="fas fa-comment-slash" style="font-size: 80px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">No Comments Found</h3>
                    <p style="color: #999;">
                        <?php if ($filter === 'pending'): ?>
                            No pending comments to moderate
                        <?php elseif ($filter === 'approved'): ?>
                            No approved comments yet
                        <?php elseif ($filter === 'rejected'): ?>
                            No rejected comments
                        <?php else: ?>
                            No comments have been submitted yet
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>