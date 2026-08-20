<?php
// admin/dashboard.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// Kunin ang total na ACTIVE na estudyante sa database
$stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'Active'");
$totalStudents = $stmtTotal->fetch()['total'];

// Get attendance stats for today
$today = date('Y-m-d');

$stmtPresent = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ? AND time_in_status = 'Present'");
$stmtPresent->execute([$today]);
$totalPresent = $stmtPresent->fetch()['total'];

$stmtLate = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ? AND time_in_status = 'Late'");
$stmtLate->execute([$today]);
$totalLate = $stmtLate->fetch()['total'];

// Get settings for Absent Threshold
$stmtSettings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'time_in_absent'");
$time_in_absent = $stmtSettings->fetchColumn();
if (!$time_in_absent) $time_in_absent = '12:01:00';

$current_time = date('H:i:s');
$totalNoRecord = $totalStudents - ($totalPresent + $totalLate);

// TIME-AWARE LOGIC
if ($current_time >= $time_in_absent) {
    $totalAbsent = $totalNoRecord;
    $totalPending = 0;
} else {
    $totalAbsent = 0;
    $totalPending = $totalNoRecord;
}

// =========================================
// WEEKLY CHART DATA (Last 7 Days)
// =========================================
$chartLabels = [];
$chartPresent = [];
$chartLate = [];
$chartAbsent = [];

for ($i = 6; $i >= 0; $i--) {
    $chartDate = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d (D)', strtotime($chartDate)); // e.g. "Aug 10 (Mon)"
    
    $stmtCP = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND time_in_status = 'Present'");
    $stmtCP->execute([$chartDate]);
    $chartPresent[] = (int)$stmtCP->fetchColumn();
    
    $stmtCL = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND time_in_status = 'Late'");
    $stmtCL->execute([$chartDate]);
    $chartLate[] = (int)$stmtCL->fetchColumn();
    
    // Absent = Total Active Students minus those who have attendance record
    $stmtCA = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ?");
    $stmtCA->execute([$chartDate]);
    $totalScanned = (int)$stmtCA->fetchColumn();
    $chartAbsent[] = max(0, $totalStudents - $totalScanned);
}

// =========================================
// AJAX REAL-TIME LOGIC
// =========================================
// Kapag tinawag ng JavaScript ang page na ito na may "?ajax=1", 
// ibibigay lang nito ang numbers bilang JSON tapos hihinto na siya (hindi na iloload ang HTML).
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'total' => $totalStudents,
        'present' => $totalPresent,
        'late' => $totalLate,
        'pending' => $totalPending,
        'absent' => $totalAbsent,
        'chart' => [
            'labels' => $chartLabels,
            'present' => $chartPresent,
            'late' => $chartLate,
            'absent' => $chartAbsent
        ]
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ACTS Attendance</title>
    <!-- Pansinin na in-update natin ang path dahil nasa admin/ folder tayo -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">Dashboard Overview</h2>
                <span>Welcome, <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>!</span>
            </div>

            <!-- Quick Stats -->
            <div class="stat-cards" style="flex-wrap: wrap;">
                <div class="card" style="border-top-color: #033500;">
                    <h3>Total Students</h3>
                    <div class="number" id="stat-total"><?php echo $totalStudents; ?></div>
                </div>
                <div class="card" style="border-top-color: #28a745;">
                    <h3>Present (On Time)</h3>
                    <div class="number" id="stat-present" style="color: #28a745;"><?php echo $totalPresent; ?></div>
                </div>
                <div class="card" style="border-top-color: #ffc107;">
                    <h3>Late Today</h3>
                    <div class="number" id="stat-late" style="color: #ffc107;"><?php echo $totalLate; ?></div>
                </div>
                <div class="card" style="border-top-color: #17a2b8;">
                    <h3>Pending Today</h3>
                    <div class="number" id="stat-pending" style="color: #17a2b8;"><?php echo $totalPending; ?></div>
                </div>
                <div class="card" style="border-top-color: #dc3545;">
                    <h3>Absent Today</h3>
                    <div class="number" id="stat-absent" style="color: #dc3545;"><?php echo $totalAbsent; ?></div>
                </div>
            </div>

            <div style="background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h3 style="color: var(--acts-green); margin-top: 0;">Welcome to ACTS Attendance System</h3>
                    <p>Today is <b><?php echo date('F d, Y - l'); ?></b>. Use the left navigation menu to manage students, view reports, or configure system settings.</p>
                </div>
                <div style="text-align: right; background: #f4f6f9; padding: 10px 20px; border-radius: 8px; border: 2px solid var(--acts-green);">
                    <p style="margin: 0; font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase;">Current Time</p>
                    <h1 id="live-clock" style="margin: 0; color: var(--acts-green); font-size: 32px; letter-spacing: 2px;">--:--:-- --</h1>
                </div>
            </div>

            <!-- Weekly Attendance Chart -->
            <div style="background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <h3 style="color: var(--acts-green); margin-top: 0;">📊 Weekly Attendance Trend (Last 7 Days)</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- REAL-TIME DASHBOARD SCRIPT -->
    <script>
        function refreshDashboardStats() {
            fetch('dashboard.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    // Update stat cards
                    document.getElementById('stat-total').innerText = data.total;
                    document.getElementById('stat-present').innerText = data.present;
                    document.getElementById('stat-late').innerText = data.late;
                    document.getElementById('stat-pending').innerText = data.pending;
                    document.getElementById('stat-absent').innerText = data.absent;
                    
                    // Update chart data (kung may chart data sa response)
                    if (data.chart) {
                        weeklyChart.data.labels = data.chart.labels;
                        weeklyChart.data.datasets[0].data = data.chart.present;
                        weeklyChart.data.datasets[1].data = data.chart.late;
                        weeklyChart.data.datasets[2].data = data.chart.absent;
                        weeklyChart.update();
                    }
                })
                .catch(error => {
                    console.error('Error fetching live stats:', error);
                });
        }

        // ==========================================
        // CHART.JS — WEEKLY ATTENDANCE BAR CHART
        // ==========================================
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Present',
                        data: <?php echo json_encode($chartPresent); ?>,
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Late',
                        data: <?php echo json_encode($chartLate); ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: '#ffc107',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Absent',
                        data: <?php echo json_encode($chartAbsent); ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.8)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { weight: 'bold' }, padding: 15 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Students', font: { weight: 'bold' } },
                        ticks: { stepSize: 10 }
                    },
                    x: {
                        title: { display: true, text: 'Date', font: { weight: 'bold' } }
                    }
                }
            }
        });

        // I-set para mag-update mag-isa every 10 seconds (mas mabagal para sa chart)
        setInterval(refreshDashboardStats, 10000);

        // LIVE CLOCK FUNCTION
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
            document.getElementById('live-clock').innerText = timeString;
        }
        
        // Patakbuhin ang orasan bawat isang segundo (1000 milliseconds)
        setInterval(updateClock, 1000);
        updateClock(); // Patakbuhin agad pagka-load ng page
    </script>
</body>
</html>