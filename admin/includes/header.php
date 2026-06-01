<div class="header">
    <div>
        <h3 style="margin: 0; color: #333;">
            <i class="fas fa-dashboard" style="margin-right: 10px; color: #667eea;"></i>
            Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!
        </h3>
        <p style="margin: 5px 0 0 0; font-size: 13px; color: #999;">
            <i class="far fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
        </p>
    </div>
    <div class="user-info">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
        </div>
        <div style="margin-right: 20px;">
            <strong style="display: block; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
            <span style="font-size: 12px; color: #999;">Administrator</span>
        </div>
       <!-- <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a> -->
    </div>
</div>