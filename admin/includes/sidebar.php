<?php 
$current_page = basename($_SERVER['PHP_SELF']); 
?>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-plane-departure"></i>
        <h2>TMS Admin</h2>
        <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Travel Management System</p>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="manage_packages.php" class="<?php echo ($current_page == 'manage_packages.php' || $current_page == 'add_package.php' || $current_page == 'edit_package.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Manage Packages</span>
            </a>
        </li>
        <li>
            <a href="manage_categories.php" class="<?php echo $current_page == 'manage_categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Manage Categories</span>
            </a>
        </li>
        <li>
            <a href="manage_bookings.php" class="<?php echo $current_page == 'manage_bookings.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Handle Bookings</span>
            </a>
        </li>
        <li>
            <a href="moderate_comments.php" class="<?php echo $current_page == 'moderate_comments.php' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span>Moderate Comments</span>
            </a>
        </li>
        <li>
            <a href="tourists.php" class="<?php echo $current_page == 'tourists.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Tourists</span>
            </a>
        </li>
        <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <a href="logout.php" style="color: #ff6b6b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>