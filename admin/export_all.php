<?php
// admin/export_all.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// Kunin ang LAHAT ng attendance records
$sql = "SELECT s.student_number, s.first_name, s.middle_initial, s.last_name, s.department, s.course_strand, s.year_grade_level, s.section, 
               a.date, a.time_in, a.time_out, a.time_in_status, a.time_out_status 
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id 
        ORDER BY a.date DESC, s.last_name ASC";

$stmt = $pdo->query($sql);
$reports = $stmt->fetchAll();

$filename = "Acts_Attendance_Full_Backup_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Headers (May kasamang Date dahil all-time records ito)
fputcsv($output, ['Date', 'Student No.', 'Last Name', 'First Name', 'M.I.', 'Department', 'Course/Strand', 'Year/Grade Level', 'Section', 'Time In', 'Time In Status', 'Time Out', 'Time Out Status']);

foreach ($reports as $row) {
    $time_in_display = $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : '--:--';
    $time_out_display = $row['time_out'] ? date("h:i A", strtotime($row['time_out'])) : '--:--';
    
    fputcsv($output, [
        $row['date'],
        $row['student_number'],
        $row['last_name'],
        $row['first_name'],
        $row['middle_initial'],
        $row['department'],
        $row['course_strand'],
        $row['year_grade_level'],
        $row['section'],
        $time_in_display,
        $row['time_in_status'],
        $time_out_display,
        $row['time_out_status']
    ]);
}

fclose($output);
exit();
?>