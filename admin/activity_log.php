<?php
// admin/activity_log.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// Date range filter
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 500");
$stmt->execute([$date_from, $date_to]);
$logs = $stmt->fetchAll();

// Auto-clear logs older than 90 days
$pdo->exec("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .log-action { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; }
        .log-add { background-color: #d4edda; color: #155724; }
        .log-edit { background-color: #fff3cd; color: #856404; }
        .log-delete { background-color: #f8d7da; color: #721c24; }
        .log-system { background-color: #d1ecf1; color: #0c5460; }
        .log-security { background-color: #e2e3e5; color: #383d41; }
    </style>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">📋 Activity Log</h2>
            </div>

            <!-- Date Range Filter -->
            <div class="admin-card">
                <form method="GET" action="activity_log.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="filter-group">
                        <label style="font-size: 12px; font-weight: bold; color: var(--acts-green);">From</label>
                        <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div class="filter-group">
                        <label style="font-size: 12px; font-weight: bold; color: var(--acts-green);">To</label>
                        <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <button type="submit" class="btn-primary" style="height: 38px;">Filter</button>
                </form>
            </div>

            <!-- Log Table -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">
                    System Activity (<?php echo count($logs); ?> records)
                    <span style="font-size: 12px; color: #999; font-weight: normal;"> — Auto-cleared after 90 days</span>
                </h3>
                <div class="table-responsive">
                    <table class="data-table" id="logTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                // Determine badge color based on action type
                                $badgeClass = 'log-system';
                                $action = $log['action'];
                                if (strpos($action, 'Add') !== false || strpos($action, 'Create') !== false || strpos($action, 'Import') !== false) {
                                    $badgeClass = 'log-add';
                                } elseif (strpos($action, 'Edit') !== false || strpos($action, 'Update') !== false || strpos($action, 'Change') !== false || strpos($action, 'Toggle') !== false || strpos($action, 'Override') !== false) {
                                    $badgeClass = 'log-edit';
                                } elseif (strpos($action, 'Delete') !== false || strpos($action, 'Reset') !== false) {
                                    $badgeClass = 'log-delete';
                                }
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date("M d, Y h:i:s A", strtotime($log['created_at'])); ?></td>
                                    <td><b><?php echo htmlspecialchars($log['username']); ?></b></td>
                                    <td><span class="log-action <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                    <td style="font-size: 13px; color: #555;"><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#logTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [10, 25, 50, 100],
                "order": [[ 0, "desc" ]],
                "language": {
                    "search": "🔍 Search Log:",
                    "lengthMenu": "Show _MENU_ entries per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ log entries"
                }
            });
        });
    </script>
</body>
</html>