<?php
// admin/reports.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// Kunin ang buong structure ng school para sa "Smart Dropdowns" ng JavaScript
$stmtStructure = $pdo->query("SELECT DISTINCT department, course_strand, year_grade_level, section FROM students WHERE department != ''");
$schoolStructure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);

// Kunin ang mga initial lists (Fallback)
$depts = array_unique(array_column($schoolStructure, 'department'));
$courses = array_unique(array_column($schoolStructure, 'course_strand'));
$years = array_unique(array_column($schoolStructure, 'year_grade_level'));
$sections = array_unique(array_column($schoolStructure, 'section'));

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$display_date = date('F d, Y', strtotime($filter_date)); // e.g. "August 14, 2026"

$filter_dept = isset($_GET['department']) ? $_GET['department'] : '';
$filter_course = isset($_GET['course_strand']) ? $_GET['course_strand'] : '';
$filter_year = isset($_GET['year_grade_level']) ? $_GET['year_grade_level'] : '';
$filter_section = isset($_GET['section']) ? $_GET['section'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : ''; // NEW: Status Filter

// Get System Settings for Absent Threshold
$stmtSettings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'time_in_absent'");
$time_in_absent = $stmtSettings->fetchColumn();
if (!$time_in_absent) $time_in_absent = '12:01:00';

$current_date = date('Y-m-d');
$current_time = date('H:i:s');

$sql = "SELECT a.id as attendance_id, s.student_number, s.first_name, s.middle_initial, s.last_name, s.department, s.course_strand, s.year_grade_level, s.section, 
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
$final_reports = []; // Dito natin ilalagay ang filtered list

foreach ($reports as $row) {
    // Alamin ang eksaktong status bawat row
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

    // Kung nag-filter ang user ng Status, i-skip ang hindi match
    if ($filter_status != '' && $in_status != $filter_status) {
        continue; 
    }

    // Bilangin para sa Stat Cards sa taas
    if ($in_status == 'Absent') $absentCount++;
    elseif ($in_status == 'Late') $lateCount++;
    elseif ($in_status == 'Present') $presentCount++;
    else $pendingCount++;

    $row['calculated_in_status'] = $in_status; // I-save ang status
    $final_reports[] = $row;
}
$totalFiltered = count($final_reports);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="display: block; height: auto;">
    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-header">
                <!-- Dito natin pinalabas ang August 14, 2026 -->
                <h2 style="margin: 0; color: var(--acts-green);">Attendance Report - <?php echo $display_date; ?></h2>
            </div>

            <!-- Filter Section -->
            <div class="admin-card">
                <form method="GET" action="reports.php" class="filter-form">
                    <div class="filter-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" required>
                    </div>
                    <!-- Nilagyan natin ng onchange at ID para mag-trigger sa JavaScript -->
                    <div class="filter-group">
                        <label>Department</label>
                        <select name="department" id="filter-dept" onchange="updateDropdowns()">
                            <option value="">All</option>
                            <?php foreach ($depts as $d): ?><option value="<?php echo htmlspecialchars($d); ?>" <?php if($filter_dept == $d) echo 'selected'; ?>><?php echo htmlspecialchars($d); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Course/Strand</label>
                        <select name="course_strand" id="filter-course" onchange="updateDropdowns()">
                            <option value="">All</option>
                            <?php foreach ($courses as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php if($filter_course == $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Year/Grade</label>
                        <select name="year_grade_level" id="filter-year" onchange="updateDropdowns()">
                            <option value="">All</option>
                            <?php foreach ($years as $y): ?><option value="<?php echo htmlspecialchars($y); ?>" <?php if($filter_year == $y) echo 'selected'; ?>><?php echo htmlspecialchars($y); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Section</label>
                        <select name="section" id="filter-section">
                            <option value="">All</option>
                            <?php foreach ($sections as $s): ?><option value="<?php echo htmlspecialchars($s); ?>" <?php if($filter_section == $s) echo 'selected'; ?>><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All</option>
                            <option value="Present" <?php if($filter_status == 'Present') echo 'selected'; ?>>Present</option>
                            <option value="Late" <?php if($filter_status == 'Late') echo 'selected'; ?>>Late</option>
                            <option value="Absent" <?php if($filter_status == 'Absent') echo 'selected'; ?>>Absent</option>
                            <option value="Pending" <?php if($filter_status == 'Pending') echo 'selected'; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 0.8; display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary" style="height: 40px; flex: 1;">Filter</button>
                        
                        <?php $query_string = http_build_query($_GET); ?>
                        <a href="export.php?<?php echo $query_string; ?>" class="btn-primary" style="height: 40px; line-height: 40px; text-decoration: none; text-align: center; background-color: #28a745; flex: 1.2;">📥 Export CSV</a>
                        
                        <!-- NEW: PRINT / PDF BUTTON -->
                        <a href="print_report.php?<?php echo $query_string; ?>" target="_blank" class="btn-primary" style="height: 40px; line-height: 40px; text-decoration: none; text-align: center; background-color: #17a2b8; flex: 1.2;">🖨️ Print / PDF</a>
                    </div>
                </form>
            </div>

            <!-- Summary Stats -->
            <div class="stat-cards" style="margin-bottom: 20px;">
                <div class="card" style="border-top-color: #28a745;">
                    <h3>Total Present (On Time)</h3>
                    <div class="number" style="color: #28a745;"><?php echo $presentCount; ?></div>
                </div>
                <div class="card" style="border-top-color: #ffc107;">
                    <h3>Total Late</h3>
                    <div class="number" style="color: #ffc107;"><?php echo $lateCount; ?></div>
                </div>
                <div class="card" style="border-top-color: #17a2b8;">
                    <h3>Total Pending</h3>
                    <div class="number" style="color: #17a2b8;"><?php echo $pendingCount; ?></div>
                </div>
                <div class="card" style="border-top-color: #dc3545;">
                    <h3>Total Absent</h3>
                    <div class="number" style="color: #dc3545;"><?php echo $absentCount; ?></div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="admin-card">
                <div class="table-responsive" style="margin-top: 15px;">
                    <table class="data-table" id="reportsTable" style="width: 100%;">
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
                            <?php foreach ($final_reports as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_initial']); ?></td>
                                    <td><?php echo htmlspecialchars($row['section']); ?></td>
                                    
                                    <!-- TIME IN -->
                                    <td><?php echo $row['time_in'] ? date("h:i A", strtotime($row['time_in'])) : '--:--'; ?></td>
                                    <td>
                                        <?php if ($row['time_in']): // Kung may time in record na ang estudyante ?>
                                            <select class="status-override" 
                                                    onchange="confirmUpdate(this, <?php echo $row['attendance_id']; ?>, 'time_in_status', this.value, '<?php echo $row['calculated_in_status']; ?>')"
                                                    style="padding: 4px; border-radius: 4px; border: 1px solid #ccc; font-size: 12px; font-weight: bold; <?php echo ($row['time_in_status'] == 'Present') ? 'background-color:#d4edda; color:#155724;' : (($row['time_in_status'] == 'Late') ? 'background-color:#fff3cd; color:#856404;' : ''); ?>">
                                                <option value="Present" <?php echo ($row['time_in_status'] == 'Present') ? 'selected' : ''; ?>>Present</option>
                                                <option value="Late" <?php echo ($row['time_in_status'] == 'Late') ? 'selected' : ''; ?>>Late</option>
                                            </select>
                                        <?php else: // Kung wala pang time in record (Absent o Pending) ?>
                                            <?php 
                                            if ($filter_date < $current_date || ($filter_date == $current_date && $current_time >= $time_in_absent)) {
                                                echo '<span class="badge-absent" style="background-color: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">Absent</span>';
                                            } else {
                                                echo '<span style="background-color: #e2e3e5; color: #383d41; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">Pending</span>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </td>

                                    <!-- TIME OUT -->
                                    <td><?php echo $row['time_out'] ? date("h:i A", strtotime($row['time_out'])) : '--:--'; ?></td>
                                    <td>
                                        <?php 
                                        if ($row['time_out_status'] == 'Early Out') {
                                            echo '<span class="badge-early">Early Out</span>';
                                        } elseif ($row['time_out_status'] == 'Normal Out') {
                                            echo '<span class="badge-present">Normal Out</span>';
                                        } else {
                                            echo '<span style="color: #999; font-size: 12px;">--</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT PARA SA SMART CASCADING DROPDOWNS -->
    <script>
        // Kunin ang data ng school galing sa database at gawing JavaScript Object
        const schoolData = <?php echo json_encode($schoolStructure); ?>;
        
        // Function para i-filter ang mga options
        function updateDropdowns() {
            const deptVal = document.getElementById('filter-dept').value;
            const courseVal = document.getElementById('filter-course').value;
            const yearVal = document.getElementById('filter-year').value;

            const courseSelect = document.getElementById('filter-course');
            const yearSelect = document.getElementById('filter-year');
            const sectionSelect = document.getElementById('filter-section');

            // I-save ang kasalukuyang nakapili para hindi mawala kung applicable pa
            const currentCourse = courseSelect.value;
            const currentYear = yearSelect.value;
            const currentSection = sectionSelect.value;

            let availableCourses = new Set();
            let availableYears = new Set();
            let availableSections = new Set();

            // Salain kung aling course, year, at section ang kabilang sa piniling Department
            schoolData.forEach(row => {
                let matchDept = (deptVal === "" || row.department === deptVal);
                let matchCourse = (courseVal === "" || row.course_strand === courseVal);
                let matchYear = (yearVal === "" || row.year_grade_level === yearVal);

                if (matchDept) availableCourses.add(row.course_strand);
                if (matchDept && matchCourse) availableYears.add(row.year_grade_level);
                if (matchDept && matchCourse && matchYear) availableSections.add(row.section);
            });

            // Rebuild function para sa HTML select
            function rebuildDropdown(selectEl, availableSet, currentValue) {
                selectEl.innerHTML = '<option value="">All</option>'; // Laging may All
                availableSet.forEach(val => {
                    let option = document.createElement('option');
                    option.value = val;
                    option.text = val;
                    // Ibalik ang dating selected kung available pa siya sa bagong listahan
                    if (val === currentValue) option.selected = true;
                    selectEl.appendChild(option);
                });
            }

            rebuildDropdown(courseSelect, availableCourses, currentCourse);
            rebuildDropdown(yearSelect, availableYears, currentYear);
            rebuildDropdown(sectionSelect, availableSections, currentSection);
        }

        // I-run agad ang function pagka-load ng page para ma-filter agad
        window.onload = function() {
            updateDropdowns();
            
            // I-force na i-select kung ano yung nasa URL natin (Fallback security)
            document.getElementById('filter-course').value = "<?php echo $filter_course; ?>";
            document.getElementById('filter-year').value = "<?php echo $filter_year; ?>";
            document.getElementById('filter-section').value = "<?php echo $filter_section; ?>";
        };
    </script>

    <!-- Admin Override Script -->
    <script>
        function confirmUpdate(selectElement, attendanceId, fieldName, newValue, oldValue) {
            if(!attendanceId) return;

            // I-trigger ang SweetAlert bago i-save
            Swal.fire({
                title: 'Override Status?',
                html: `Are you sure you want to change the status to <b style="color:var(--acts-green);">${newValue}</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Yes, change it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kung kinonfirm, ipadala sa database
                    fetch('update_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `attendance_id=${attendanceId}&field_name=${fieldName}&new_value=${newValue}&csrf_token=<?php echo generateCsrfToken(); ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: 'The record has been updated successfully.',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // I-reload ang page para ma-update ang Stat Cards sa taas at ang DataTables Cache!
                                location.reload(); 
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                            selectElement.value = oldValue; // Ibalik sa dati kung may error
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Failed to communicate with server.', 'error');
                        selectElement.value = oldValue;
                    });
                } else {
                    // Kung kinansela ang SweetAlert, ibalik ang dropdown sa dating value
                    selectElement.value = oldValue;
                }
            });
        }
    </script>

    <!-- jQuery & DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#reportsTable').DataTable({
                "pageLength": 25, // Ginawa nating 25 default para mas marami agad makita sa report
                "lengthMenu": [10, 25, 50, 100, 500, 1000], 
                "language": {
                    "search": "🔍 Search Report:",
                    "lengthMenu": "Show _MENU_ records per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ records"
                }
            });
        });
    </script>
</body>
</html>