<?php
// admin/includes/sidebar.php
// Shared sidebar component para sa lahat ng admin pages

// Kunin ang filename ng current page para malaman kung alin ang "active"
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h2>ACTS Admin</h2>
        <p style="font-size: 12px; margin-top: 5px; color: #ccc;">Attendance System</p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" <?php if($currentPage == 'dashboard.php') echo 'class="active"'; ?>>Dashboard</a></li>
        <li><a href="students.php" <?php if($currentPage == 'students.php') echo 'class="active"'; ?>>Manage Students</a></li>
        <li><a href="users.php" <?php if($currentPage == 'users.php') echo 'class="active"'; ?>>Manage Users</a></li>
        <li><a href="classes.php" <?php if($currentPage == 'classes.php') echo 'class="active"'; ?>>Manage Classes</a></li>
        <li><a href="settings.php" <?php if($currentPage == 'settings.php') echo 'class="active"'; ?>>System Settings</a></li>
        <li><a href="reports.php" <?php if($currentPage == 'reports.php') echo 'class="active"'; ?>>Attendance Reports</a></li>
        <li><a href="activity_log.php" <?php if($currentPage == 'activity_log.php') echo 'class="active"'; ?>>📋 Activity Log</a></li>
        <li><a href="../scanner/index.php" target="_blank">Open Scanner UI</a></li>
        <li><a href="../logout.php" style="color: #ff9999;">Logout</a></li>
    </ul>
</nav>