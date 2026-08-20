<?php
// scanner/process_scan.php
require_once '../config/session.php';
require_once '../config/db.php';
requireScanner();

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_data']) && isset($_POST['scan_type'])) {
    $searchData = trim($_POST['student_data']);
    $scanType = trim($_POST['scan_type']); // 'time_in' or 'time_out'
    
    if (empty($searchData)) {
        echo json_encode(['status' => 'error', 'message' => 'Walang data na na-receive.']);
        exit();
    }

    try {
        // Kunin ang System Settings (Dynamic Times)
        $stmtFetch = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settingsData = $stmtFetch->fetchAll(PDO::FETCH_KEY_PAIR);
        $time_late = isset($settingsData['time_in_late']) ? $settingsData['time_in_late'] : '10:01:00';
        $time_absent = isset($settingsData['time_in_absent']) ? $settingsData['time_in_absent'] : '12:01:00';
        $time_out_start = isset($settingsData['time_out_start']) ? $settingsData['time_out_start'] : '17:00:00';

        // Hanapin ang estudyante (Active lang!)
        $stmt = $pdo->prepare("SELECT id, student_number, first_name, middle_initial, last_name, course_strand, section, year_grade_level FROM students WHERE student_number = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$searchData]);
        $student = $stmt->fetch();
        

        $step = isset($_POST['step']) ? $_POST['step'] : 'record';

        // ==========================================
        // NEW: LIVE SEARCH SUGGESTIONS LOGIC
        // ==========================================
        if ($step === 'live_search') {
            $wildcard = "%" . $searchData . "%";
            // Kukuha tayo ng hanggang 5 suggestions habang nagta-type ang user
            $stmt = $pdo->prepare("SELECT student_number, first_name, middle_initial, last_name, course_strand, section, year_grade_level FROM students WHERE status = 'Active' AND (first_name LIKE ? OR last_name LIKE ? OR student_number LIKE ?) LIMIT 5");
            $stmt->execute([$wildcard, $wildcard, $wildcard]);
            $studentsFound = $stmt->fetchAll();
            
            $studentList = [];
            foreach ($studentsFound as $s) {
                $mi = !empty($s['middle_initial']) ? $s['middle_initial'] . ' ' : '';
                $fullName = $s['first_name'] . ' ' . $mi . $s['last_name'];
                $tag = $s['course_strand'] . '-' . $s['section'] . ', ' . $s['year_grade_level'];
                
                $studentList[] = [
                    'student_number' => $s['student_number'],
                    'name' => $fullName,
                    'tag' => $tag
                ];
            }
            // Ibalik bilang "multiple" list para gawing button ng Javascript, kahit isa lang ang nahanap!
            echo json_encode(['status' => 'multiple', 'students' => $studentList]);
            exit();
        }
        // ==========================================

        if (!$student) {
            $wildcard = "%" . $searchData . "%";
            $stmt = $pdo->prepare("SELECT id, student_number, first_name, middle_initial, last_name, course_strand, section, year_grade_level FROM students WHERE status = 'Active' AND (first_name LIKE ? OR last_name LIKE ? OR student_number LIKE ?)");
            $stmt->execute([$wildcard, $wildcard, $wildcard]);
            $studentsFound = $stmt->fetchAll();

            if (count($studentsFound) > 1) {
                // KUNG MULTIPLE ANG NAHANAP
                if ($step === 'check') {
                    $studentList = [];
                    foreach ($studentsFound as $s) {
                        $mi = !empty($s['middle_initial']) ? $s['middle_initial'] . ' ' : '';
                        $fullName = $s['first_name'] . ' ' . $mi . $s['last_name'];
                        // Ito yung format na gusto mo: BSIT-A, First Year
                        $tag = $s['course_strand'] . '-' . $s['section'] . ', ' . $s['year_grade_level'];
                        
                        $studentList[] = [
                            'student_number' => $s['student_number'],
                            'name' => $fullName,
                            'tag' => $tag
                        ];
                    }
                    echo json_encode(['status' => 'multiple', 'students' => $studentList]);
                    exit();
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Multiple names found. Please use the search selection.']);
                    exit();
                }
            } elseif (count($studentsFound) == 1) {
                $student = $studentsFound[0];
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Student not found in the masterlist.']);
                exit();
            }
        }

        $student_id = $student['id'];
        $mi_display = !empty($student['middle_initial']) ? $student['middle_initial'] . ' ' : '';
        $fullName = $student['first_name'] . ' ' . $mi_display . $student['last_name'];
        // Format na BSIT-A, First Year
        $tag = $student['course_strand'] . '-' . $student['section'] . ', ' . $student['year_grade_level'];
        
        if ($step === 'check') {
            echo json_encode([
                'status' => 'confirm', 
                'name' => $fullName, 
                'section' => $tag,
                'student_number' => $student['student_number']
            ]);
            exit();
        }

        $today = date('Y-m-d');
        $timeNow = date('H:i:s');
        $scannedBy = $_SESSION['user_id'];
        
        // I-check ang attendance para ngayong araw
        $stmtCheck = $pdo->prepare("SELECT id, time_in, time_out FROM attendance WHERE student_id = ? AND date = ? LIMIT 1");
        $stmtCheck->execute([$student_id, $today]);
        $existingRecord = $stmtCheck->fetch();

        // -------------------------------------------------------------
        // TIME IN LOGIC
        // -------------------------------------------------------------
        if ($scanType === 'time_in') {
            if ($existingRecord) {
                $timeInFormat = date("h:i A", strtotime($existingRecord['time_in']));
                echo json_encode(['status' => 'error', 'message' => "$fullName already timed in at $timeInFormat."]);
                exit();
            }

            // Compute Status (Removed Absent, anyone scanning after threshold is Late)
            $in_status = 'Present';
            if (strtotime($timeNow) >= strtotime($time_late)) {
                $in_status = 'Late';
            }

            $stmtInsert = $pdo->prepare("INSERT INTO attendance (student_id, date, time_in, time_in_status, scanned_by) VALUES (?, ?, ?, ?, ?)");
            if ($stmtInsert->execute([$student_id, $today, $timeNow, $in_status, $scannedBy])) {
                $timeInDisplay = date("h:i A", strtotime($timeNow));
                echo json_encode(['status' => 'success', 'message' => "Time In ($in_status): $fullName - $timeInDisplay"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error during time in.']);
            }
        } 
        // -------------------------------------------------------------
        // TIME OUT LOGIC
        // -------------------------------------------------------------
        elseif ($scanType === 'time_out') {
            if (!$existingRecord) {
                echo json_encode(['status' => 'error', 'message' => "$fullName must Time In first before Timing Out."]);
                exit();
            }

            if (!empty($existingRecord['time_out'])) {
                $timeOutFormat = date("h:i A", strtotime($existingRecord['time_out']));
                echo json_encode(['status' => 'error', 'message' => "$fullName already timed out at $timeOutFormat."]);
                exit();
            }

            // Compute Status
            $out_status = 'Early Out';
            if (strtotime($timeNow) >= strtotime($time_out_start)) {
                $out_status = 'Normal Out';
            }

            $stmtUpdate = $pdo->prepare("UPDATE attendance SET time_out = ?, time_out_status = ? WHERE id = ?");
            if ($stmtUpdate->execute([$timeNow, $out_status, $existingRecord['id']])) {
                $timeOutDisplay = date("h:i A", strtotime($timeNow));
                echo json_encode(['status' => 'success', 'message' => "Time Out ($out_status): $fullName - $timeOutDisplay"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error during time out.']);
            }
        }

    } catch (PDOException $e) {
        // Log the actual error for debugging (check PHP error log)
        error_log("ACTS Scanner Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'A system error occurred. Please try again or contact the administrator.']);
    }
}
?>