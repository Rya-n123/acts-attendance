<?php
// admin/print_report.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$display_date = date('F d, Y', strtotime($filter_date));

$filter_dept = isset($_GET['department']) ? $_GET['department'] : '';
$filter_course = isset($_GET['course_strand']) ? $_GET['course_strand'] : '';
$filter_year = isset($_GET['year_grade_level']) ? $_GET['year_grade_level'] : '';
$filter_section = isset($_GET['section']) ? $_GET['section'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Settings
$stmtSettings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'time_in_absent'");
$time_in_absent = $stmtSettings->fetchColumn();
if (!$time_in_absent) $time_in_absent = '12:01:00';

$current_date = date('Y-m-d');
$current_time = date('H:i:s');

$sql = "SELECT s.student_number, s.first_name, s.middle_initial, s.last_name, s.department, s.course_strand, s.year_grade_level, s.section, 
               a.time_in, a.time_out, a.time_in_status, a.time_out_status 
        FROM students s 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = :date 
        WHERE s.status = 'Active'";

$params = [':date' => $filter_date];
if (!empty($filter_dept)) { $sql .= " AND s.department = :dept"; $params[':dept'] = $filter_dept; }
if (!empty($filter_course)) { $sql .= " AND s.course_strand = :course"; $params[':course'] = $filter_course; }
if (!empty($filter_year)) { $sql .= " AND s.year_grade_level = :year"; $params[':year'] = $filter_year; }
if (!empty($filter_section)) { $sql .= " AND s.section = :section"; $params[':section'] = $filter_section; }

$sql .= " ORDER BY s.last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$presentCount = 0; $lateCount = 0; $absentCount = 0; $pendingCount = 0;
$final_reports = [];

foreach ($reports as $row) {
    if (!$row['time_in_status']) {
        if ($filter_date < $current_date || ($filter_date == $current_date && $current_time >= $time_in_absent)) {
            $in_status = 'Absent';
        } else {
            $in_status = 'Pending';
        }
    } else {
        $in_status = $row['time_in_status'];
    }

    if ($filter_status != '' && $in_status != $filter_status) continue; 

    if ($in_status == 'Absent') $absentCount++;
    elseif ($in_status == 'Late') $lateCount++;
    elseif ($in_status == 'Present') $presentCount++;
    else $pendingCount++;

    $row['calculated_in_status'] = $in_status;
    $final_reports[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Report - ACTS Attendance</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #033500; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #033500; font-size: 24px; }
        .header p { margin: 5px 0; font-size: 14px; color: #555; }
        
        .summary-box { display: flex; justify-content: space-between; margin-bottom: 20px; border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9; }
        .summary-box div { text-align: center; width: 22%; }
        .summary-box h4 { margin: 0 0 5px 0; font-size: 12px; color: #666; text-transform: uppercase; }
        .summary-box .val { font-size: 18px; font-weight: bold; }
        .text-success { color: #28a745; } .text-warning { color: #856404; } .text-danger { color: #dc3545; } .text-info { color: #17a2b8; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #033500; color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        
        .filters { margin-bottom: 15px; font-size: 13px; color: #444; }
        .filters span { font-weight: bold; margin-right: 15px; }

        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .sign-line { width: 250px; border-top: 1px solid #000; text-align: center; padding-top: 5px; font-weight: bold; }

        @media print {
            @page { margin: 1cm; }
            body { padding: 0; }
            .no-print { display: none !important; } /* Ito ang magtatago sa buttons kapag ipi-print na */
        }
    </style>
</head>
<body>

    <!-- ACTION BAR (Makikita lang sa screen, hindi sa papel) -->
    <div class="no-print" style="background-color: #f8f9fa; padding: 15px; text-align: right; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <span style="float: left; font-weight: bold; color: #555; line-height: 35px;">📄 Document Preview Mode</span>
        <button onclick="window.print()" style="background-color: #17a2b8; color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-right: 10px;">🖨️ Print / Save as PDF</button>
        <button onclick="window.close()" style="background-color: #dc3545; color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 5px; cursor: pointer;">❌ Close View</button>
    </div>

    <div class="header">
        <h1>ACTS Attendance System</h1>
        <p>Daily Attendance Summary Report</p>
        <p><strong>Date: <?php echo $display_date; ?></strong></p>
    </div>

    <div class="filters">
        <span>Department: <?php echo $filter_dept ?: 'All'; ?></span>
        <span>Course/Strand: <?php echo $filter_course ?: 'All'; ?></span>
        <span>Year/Grade: <?php echo $filter_year ?: 'All'; ?></span>
        <span>Section: <?php echo $filter_section ?: 'All'; ?></span>
        <span>Status: <?php echo $filter_status ?: 'All'; ?></span>
    </div>

    <div class="summary-box">
        <div><h4>Total Present</h4><span class="val text-success"><?php echo $presentCount; ?></span></div>
        <div><h4>Total Late</h4><span class="val text-warning"><?php echo $lateCount; ?></span></div>
        <div><h4>Total Absent</h4><span class="val text-danger"><?php echo $absentCount; ?></span></div>
        <div><h4>Total Pending</h4><span class="val text-info"><?php echo $pendingCount; ?></span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student No.</th>
                <th>Name</th>
                <th>Section</th>
                <th>Time In</th>
                <th>In Status</th>
                <th>Time Out</th>
                <th>Out Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($final_reports) > 0): ?>
                <?php foreach ($final_reports as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_initial']); ?></td>
                        <td><?php echo htmlspecialchars($row['section']); ?></td>
                        <td><?php echo $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : '--:--'; ?></td>
                        <td><?php echo $row['calculated_in_status']; ?></td>
                        <td><?php echo $row['time_out'] ? date("h:i A", strtotime($row['time_out'])) : '--:--'; ?></td>
                        <td><?php echo $row['time_out_status'] ?: '--'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center;">No records found for the selected filter.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div>
            <div class="sign-line">Prepared By (System Administrator)</div>
        </div>
        <div>
            <div class="sign-line">Noted By (Adviser / Principal)</div>
        </div>
    </div>

    
</body>
</html>