<?php
// admin/export.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_dept = isset($_GET['department']) ? $_GET['department'] : '';

$stmtSettings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'time_in_absent'");
$time_in_absent = $stmtSettings->fetchColumn();
if (!$time_in_absent) $time_in_absent = '12:01:00';

$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$filter_course = isset($_GET['course_strand']) ? $_GET['course_strand'] : '';
$filter_year = isset($_GET['year_grade_level']) ? $_GET['year_grade_level'] : '';
$filter_section = isset($_GET['section']) ? $_GET['section'] : '';

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

$filename = "Acts_Attendance_" . $filter_date . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// UPDATE: Idinagdag ang 'M.I.' sa mismong Headers para pumantay sa data!
fputcsv($output, ['Student No.', 'Last Name', 'First Name', 'M.I.', 'Department', 'Course/Strand', 'Year/Grade Level', 'Section', 'Time In', 'Time In Status', 'Time Out', 'Time Out Status']);

foreach ($reports as $row) {
    $time_in_display = $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : '--:--';
    $time_out_display = $row['time_out'] ? date("h:i A", strtotime($row['time_out'])) : '--:--';
    
    if (!$row['time_in_status']) {
        if ($filter_date < $current_date) {
            $in_status = 'Absent';
        } elseif ($filter_date == $current_date && $current_time >= $time_in_absent) {
            $in_status = 'Absent';
        } else {
            $in_status = 'Pending';
        }
    } else {
        $in_status = $row['time_in_status'];
    }
    $out_status = $row['time_out_status'] ? $row['time_out_status'] : 'No Record';

    fputcsv($output, [
        $row['student_number'],
        $row['last_name'],
        $row['first_name'],
        $row['middle_initial'], // Idinagdag ang M.I. dito
        $row['department'],
        $row['course_strand'],
        $row['year_grade_level'],
        $row['section'],
        $time_in_display,
        $in_status,
        $time_out_display,
        $out_status
    ]);
}

fclose($output);
exit();
?>