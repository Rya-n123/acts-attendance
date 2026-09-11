<?php
// admin/includes/sidebar.php
// Shared sidebar component para sa lahat ng admin pages

// Kunin ang filename ng current page para malaman kung alin ang "active"
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Hamburger Menu Button (mobile only) -->
<button class="hamburger-btn" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

<!-- Dark Overlay (mobile only) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <img src="../assets/img/acts-logo.png" alt="ACTS Logo" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid var(--acts-yellow); margin-bottom: 8px; object-fit: cover;">
        <h2 style="margin: 0;">ACTS Admin</h2>
        <p style="font-size: 11px; margin-top: 3px; color: #ccc;">ACTS Computer College</p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" <?php if($currentPage == 'dashboard.php') echo 'class="active"'; ?> onclick="closeSidebar()">📊 Dashboard</a></li>
        <li><a href="students.php" <?php if($currentPage == 'students.php') echo 'class="active"'; ?> onclick="closeSidebar()">👨‍🎓 Manage Students</a></li>
        <li><a href="users.php" <?php if($currentPage == 'users.php') echo 'class="active"'; ?> onclick="closeSidebar()">👤 Manage Users</a></li>
        <li><a href="classes.php" <?php if($currentPage == 'classes.php') echo 'class="active"'; ?> onclick="closeSidebar()">📚 Manage Classes</a></li>
        <li><a href="settings.php" <?php if($currentPage == 'settings.php') echo 'class="active"'; ?> onclick="closeSidebar()">⚙️ System Settings</a></li>
        <li><a href="events.php" <?php if($currentPage == 'events.php') echo 'class="active"'; ?> onclick="closeSidebar()">🎪 Manage Events</a></li>
        <li><a href="reports.php" <?php if($currentPage == 'reports.php') echo 'class="active"'; ?> onclick="closeSidebar()">📄 Attendance Reports</a></li>
        <li><a href="activity_log.php" <?php if($currentPage == 'activity_log.php') echo 'class="active"'; ?> onclick="closeSidebar()">📋 Activity Log</a></li>
        <li><a href="../scanner/index.php" target="_blank">📱 Open Scanner UI</a></li>
        <li><a href="../logout.php" style="color: #ff9999;">🚪 Logout</a></li>
    </ul>
</nav>

<script>
function toggleSidebar() {
    document.getElementById('mainSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
function closeSidebar() {
    document.getElementById('mainSidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}
</script>