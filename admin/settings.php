<?php
// admin/settings.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$message = '';
$error = '';

// IF NEW SETTINGS SUBMITTED
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings']) && validateCsrfToken()) {
    $time_in_late = $_POST['time_in_late'];
    $time_in_absent = $_POST['time_in_absent']; // NEW: Kukunin ang absent time
    $time_out_start = $_POST['time_out_start'];

    try {
        $pdo->beginTransaction();
        // Gumamit tayo ng INSERT IGNORE... ON DUPLICATE KEY UPDATE para safe kahit wala pa sa database
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute(['time_in_late', $time_in_late]);
        $stmt->execute(['time_in_absent', $time_in_absent]); // NEW: Ise-save sa database
        $stmt->execute(['time_out_start', $time_out_start]);
        
        $pdo->commit();
        $message = "Success! Attendance time thresholds have been updated.";
        logActivity($pdo, 'Update Settings', "Late: $time_in_late, Absent: $time_in_absent, TimeOut: $time_out_start");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// IF RESET DATABASE SUBMITTED
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_database']) && validateCsrfToken()) {
    try {
        $pdo->exec("DELETE FROM attendance");
        $pdo->exec("ALTER TABLE attendance AUTO_INCREMENT = 1");
        $message = "Database Reset Successful! All attendance records have been cleared.";
        logActivity($pdo, 'Reset Database', "All attendance records have been cleared");
    } catch (Exception $e) {
        $error = "Error resetting database: " . $e->getMessage();
    }
}

$stmtFetch = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settingsData = $stmtFetch->fetchAll(PDO::FETCH_KEY_PAIR); 

$current_late = isset($settingsData['time_in_late']) ? $settingsData['time_in_late'] : '10:01:00';
$current_absent = isset($settingsData['time_in_absent']) ? $settingsData['time_in_absent'] : '12:01:00'; // NEW: Kukunin para sa HTML form
$current_timeout = isset($settingsData['time_out_start']) ? $settingsData['time_out_start'] : '17:00:00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">System Settings</h2>
            </div>

            <?php if (!empty($message)): ?>
                <script>
                    Swal.fire('Success!', <?php echo json_encode($message); ?>, 'success');
                </script>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <script>
                    Swal.fire('Error!', <?php echo json_encode($error); ?>, 'error');
                </script>
            <?php endif; ?>

            <div class="admin-card" style="max-width: 600px;">
                <h3 style="margin-top: 0; color: var(--acts-green);">Attendance Time Thresholds</h3>
                <p style="font-size: 14px; color: #666;">Set the threshold times for marking students as Late or Early Out.</p>
                
                <form action="settings.php" method="POST" style="display: flex; flex-direction: column; gap: 15px; align-items: flex-start;">
                    <?php echo csrfTokenField(); ?>
                    
                    <div style="display: flex; flex-direction: column; width: 100%; max-width: 450px; text-align: left;">
                        <label style="font-weight: bold; color: var(--acts-green); margin-bottom: 5px;">Late Threshold (Time In)</label>
                        <p style="font-size: 12px; color: #888; margin: 0 0 8px 0;">Any time-in from this time onwards will be marked as "Late".</p>
                        <input type="time" name="time_in_late" value="<?php echo htmlspecialchars($current_late); ?>" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%; font-family: inherit;">
                    </div>

                    <div style="display: flex; flex-direction: column; width: 100%; max-width: 450px; text-align: left;">
                        <label style="font-weight: bold; color: var(--acts-green); margin-bottom: 5px;">Absent Threshold (Time In)</label>
                        <p style="font-size: 12px; color: #888; margin: 0 0 8px 0;">If the student has no record past this time, they will be automatically marked as "Absent" in reports.</p>
                        <input type="time" name="time_in_absent" value="<?php echo htmlspecialchars($current_absent); ?>" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%; font-family: inherit;">
                    </div>

                    <div style="display: flex; flex-direction: column; width: 100%; max-width: 450px; text-align: left;">
                        <label style="font-weight: bold; color: var(--acts-green); margin-bottom: 5px;">Normal Time Out</label>
                        <p style="font-size: 12px; color: #888; margin: 0 0 8px 0;">Timing out before this time will be marked as "Early Out".</p>
                        <input type="time" name="time_out_start" value="<?php echo htmlspecialchars($current_timeout); ?>" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%; font-family: inherit;">
                    </div>

                    <button type="submit" name="update_settings" class="btn-primary" style="margin-top: 5px; padding: 12px 25px;">Save Settings</button>
                </form>
            </div>

            <!-- DANGER ZONE: DATA RESET -->
            <div class="admin-card" style="max-width: 600px; border: 2px solid #dc3545;">
                <h3 style="margin-top: 0; color: #dc3545;">Danger Zone: Reset Database</h3>
                <p style="font-size: 14px; color: #666;">This action will permanently delete all attendance records. This is useful for clearing test data before final deployment.</p>
                <p style="font-size: 14px; color: #dc3545; font-weight: bold;">Make sure to download a backup first before resetting!</p>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <!-- Backup Button -->
                    <a href="export_all.php" class="btn-primary" style="background-color: #28a745; text-decoration: none; text-align: center; flex: 1;">📥 Backup / Export All Data</a>
                    
                    <!-- Reset Form & Button -->
                    <form id="resetForm" action="settings.php" method="POST" style="flex: 1; margin: 0;">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="reset_database" value="1">
                        <button type="button" onclick="confirmReset()" class="btn-primary" style="background-color: #dc3545; width: 100%;">⚠️ Clear All Records</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        function confirmReset() {
            Swal.fire({
                title: 'Are you absolutely sure?',
                html: "This will permanently delete ALL attendance records.<br>Have you clicked <b>Backup / Export</b> first?<br><br>If you are sure, type <b>CONFIRM</b> below:",
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Type CONFIRM here...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Everything'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (result.value === 'CONFIRM') {
                        document.getElementById('resetForm').submit();
                    } else {
                        Swal.fire('Cancelled', 'You did not type CONFIRM. The database is safe.', 'info');
                    }
                }
            });
        }
    </script>
</body>
</html>