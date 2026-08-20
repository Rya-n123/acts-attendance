<?php
// admin/students.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$message = '';
$error = '';

// =========================================
// CRUD LOGIC (ADD, EDIT, DELETE SINGLE)
// =========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CSRF Validation for all POST actions
    if (!validateCsrfToken()) {
        $error = "Invalid request. Please refresh the page and try again.";
    }
    
    // 1. ADD SINGLE STUDENT
    if (empty($error) && isset($_POST['add_single'])) {
        $student_no = trim($_POST['student_number']);
        $fname = trim($_POST['first_name']);
        $mi = trim($_POST['middle_initial']);
        $lname = trim($_POST['last_name']);
        $dept = trim($_POST['department']);
        $course = trim($_POST['course_strand']);
        $year = trim($_POST['year_grade_level']);
        $section = trim($_POST['section']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO students (student_number, first_name, middle_initial, last_name, department, course_strand, year_grade_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_no, $fname, $mi, $lname, $dept, $course, $year, $section]);
            $message = "Student $fname $mi $lname has been added.";
            logActivity($pdo, 'Add Student', "Added: $fname $mi $lname ($student_no)");
        } catch (Exception $e) {
            $error = "Error adding student. Student Number might already exist.";
        }
    }
    
    // 2. EDIT STUDENT
    if (empty($error) && isset($_POST['edit_student'])) {
        $id = $_POST['student_id'];
        $student_no = trim($_POST['student_number']);
        $fname = trim($_POST['first_name']);
        $mi = trim($_POST['middle_initial']);
        $lname = trim($_POST['last_name']);
        $dept = trim($_POST['department']);
        $course = trim($_POST['course_strand']);
        $year = trim($_POST['year_grade_level']);
        $section = trim($_POST['section']);
        
        try {
            $stmt = $pdo->prepare("UPDATE students SET student_number=?, first_name=?, middle_initial=?, last_name=?, department=?, course_strand=?, year_grade_level=?, section=? WHERE id=?");
            $stmt->execute([$student_no, $fname, $mi, $lname, $dept, $course, $year, $section, $id]);
            $message = "Student information successfully updated.";
            logActivity($pdo, 'Edit Student', "Updated: $fname $mi $lname ($student_no)");
        } catch (Exception $e) {
            $error = "Error updating student.";
        }
    }
    
        // 3. TOGGLE STATUS (Active/Inactive)
    if (empty($error) && isset($_POST['toggle_status'])) {
        $id = $_POST['student_id'];
        $new_status = $_POST['new_status'];
        
        if (in_array($new_status, ['Active', 'Inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                $message = "Student status changed to $new_status.";
                logActivity($pdo, 'Toggle Student Status', "Student ID $id set to $new_status");
            } catch (Exception $e) {
                $error = "Error updating student status.";
            }
        }
    }
    
    // 4. DELETE STUDENT
    if (empty($error) && isset($_POST['delete_student'])) {
        $id = $_POST['student_id'];
        try {
            // Delete attendance records first to avoid orphaned data
            $stmtDelAtt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ?");
            $stmtDelAtt->execute([$id]);
            $stmtDelStu = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmtDelStu->execute([$id]);
            $message = "Student successfully deleted from the database.";
            logActivity($pdo, 'Delete Student', "Deleted student ID: $id");
        } catch (Exception $e) {
            $error = "Error deleting student.";
        }
    }
}

// KUNG MAY INUPLOAD NA CSV FILE
if (isset($_POST['import']) && validateCsrfToken()) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        
        $file = fopen($filename, "r");
        
        // Tanggalin ang UTF-8 BOM kung meron (ito yung nagiging sanhi ng invisible characters)
        $bom = fread($file, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($file); // Kung walang BOM, bumalik sa simula ng file
        }

        $isFirstRow = true;
        
        try {
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO students (student_number, first_name, middle_initial, last_name, department, course_strand, year_grade_level, section) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    first_name=VALUES(first_name), middle_initial=VALUES(middle_initial), last_name=VALUES(last_name), department=VALUES(department), 
                    course_strand=VALUES(course_strand), year_grade_level=VALUES(year_grade_level), section=VALUES(section)";
            $stmt = $pdo->prepare($sql);

            while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                if (!empty($data[0])) {
                    $stmt->execute([
                        trim($data[0]), // Col 1: Student Number
                        trim($data[1]), // Col 2: First Name
                        trim($data[2]), // Col 3: Middle Initial
                        trim($data[3]), // Col 4: Last Name
                        trim($data[4]), // Col 5: Department
                        trim($data[5]), // Col 6: Course/Strand
                        trim($data[6]), // Col 7: Year/Grade Level
                        trim($data[7])  // Col 8: Section
                    ]);
                }
            }
            
            $pdo->commit();
            $message = "Success! CSV masterlist has been imported.";
            logActivity($pdo, 'Import CSV', "Imported student masterlist from CSV file");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error importing file: " . $e->getMessage();
        }
        
        fclose($file);
    } else {
        $error = "Please select a valid CSV file.";
    }
}

// Kunin lahat ng estudyante para i-display sa table
$stmtDisplay = $pdo->query("SELECT * FROM students ORDER BY last_name ASC");
$studentsList = $stmtDisplay->fetchAll();

// Kunin ang mga options para sa Add/Edit Dropdowns mula sa bagong normalized tables
$deptList = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$courseList = $pdo->query("SELECT name FROM courses ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$yearList = $pdo->query("SELECT name FROM year_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$sectionList = $pdo->query("SELECT name FROM sections ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .btn-edit { background-color: #ffc107; color: #000; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .btn-delete { background-color: #dc3545; color: #fff; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; margin-left: 5px;}
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); border-top: 5px solid var(--acts-green);}
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #000; }
        .modal-form-group { margin-bottom: 15px; }
        .modal-form-group label { display: block; font-size: 12px; color: var(--acts-green); font-weight: bold; margin-bottom: 5px;}
        .modal-form-group input, .modal-form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;}
    </style>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">Manage Masterlist</h2>
            </div>

            <?php if (!empty($message)): ?>
                <script>Swal.fire('Success!', <?php echo json_encode($message); ?>, 'success');</script>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <script>Swal.fire('Error!', <?php echo json_encode($error); ?>, 'error');</script>
            <?php endif; ?>

            <!-- Import CSV & Add Single -->
            <div class="admin-card" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                <div>
                    <h3 style="margin-top: 0; color: var(--acts-green);">Import Students (CSV UTF-8)</h3>
                    <p style="font-size: 14px; color: #666;">Excel Column Format: <b>1.</b> Student Number | <b>2.</b> First Name | <b>3.</b> M.I. | <b>4.</b> Last Name | <b>5.</b> Dept | <b>6.</b> Course | <b>7.</b> Year | <b>8.</b> Section</p>
                                        <form action="students.php" method="POST" enctype="multipart/form-data">
                        <?php echo csrfTokenField(); ?>
                        <input type="file" name="csv_file" accept=".csv" required style="padding: 5px;">
                        <button type="submit" name="import" class="btn-primary">Upload & Import</button>
                    </form>
                </div>
                <div style="margin-top: 10px;">
                    <button onclick="openAddModal()" class="btn-primary" style="background-color: #28a745;">+ Add Single Student</button>
                </div>
            </div>

            <!-- Student List Table -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">Registered Students (<?php echo count($studentsList); ?>)</h3>
                <div class="table-responsive" style="margin-top: 15px;">
                    <table class="data-table" id="studentsTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Student No.</th>
                                <th>Name</th>
                                <th>Dept</th>
                                <th>Course/Strand</th>
                                <th>Yr/Grade</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($studentsList) > 0): ?>
                                <?php foreach ($studentsList as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_initial']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['course_strand']); ?></td>
                                        <td><?php echo htmlspecialchars($row['year_grade_level']); ?></td>
                                        <td><?php echo htmlspecialchars($row['section']); ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Active'): ?>
                                                <span class="badge-active">ACTIVE</span>
                                            <?php else: ?>
                                                <span class="badge-inactive">INACTIVE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 160px;">
                                            <button class="btn-edit" onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['student_number']); ?>', '<?php echo addslashes($row['first_name']); ?>', '<?php echo addslashes($row['middle_initial']); ?>', '<?php echo addslashes($row['last_name']); ?>', '<?php echo addslashes($row['department']); ?>', '<?php echo addslashes($row['course_strand']); ?>', '<?php echo addslashes($row['year_grade_level']); ?>', '<?php echo addslashes($row['section']); ?>')">Edit</button>
                                            
                                            <form action="students.php" method="POST" style="display:inline;">
                                                <?php echo csrfTokenField(); ?>
                                                <input type="hidden" name="toggle_status" value="1">
                                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                                <?php if ($row['status'] === 'Active'): ?>
                                                    <input type="hidden" name="new_status" value="Inactive">
                                                    <button type="submit" class="btn-status" style="background-color: #ffc107; color: #000;" onclick="return confirm('Set this student to Inactive?')">⏸</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="new_status" value="Active">
                                                    <button type="submit" class="btn-status" style="background-color: #28a745; color: #fff;" onclick="return confirm('Reactivate this student?')">▶</button>
                                                <?php endif; ?>
                                            </form>
                                            <form action="students.php" method="POST" style="display:inline;" id="deleteForm_<?php echo $row['id']; ?>">
                                                <?php echo csrfTokenField(); ?>
                                                <input type="hidden" name="delete_student" value="1">
                                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                                <button type="button" class="btn-delete" onclick="confirmDelete('<?php echo $row['id']; ?>', '<?php echo addslashes($row['first_name'] . ' ' . $row['last_name']); ?>')">Del</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No registered students found. Please upload a CSV file to begin.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL PARA SA ADD AT EDIT -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle" style="color: var(--acts-green); margin-top: 0;">Add Student</h3>
            <form action="students.php" method="POST">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="student_id" id="modal_student_id">
                
                <div style="display: flex; gap: 10px;">
                    <div class="modal-form-group" style="flex: 1;">
                        <label>Student Number</label>
                        <input type="text" name="student_number" id="modal_student_number" required>
                    </div>
                    <div class="modal-form-group" style="flex: 1;">
                        <label>Department</label>
                        <select name="department" id="modal_department" required>
                            <option value="">Select Dept</option>
                            <?php foreach($deptList as $d): ?><option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="modal-form-group" style="flex: 2;">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="modal_first_name" required>
                    </div>
                    <div class="modal-form-group" style="flex: 1;">
                        <label>M.I.</label>
                        <input type="text" name="middle_initial" id="modal_middle_initial" placeholder="e.g. A.">
                    </div>
                    <div class="modal-form-group" style="flex: 2;">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="modal_last_name" required>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="modal-form-group" style="flex: 1;">
                        <label>Course/Strand</label>
                        <select name="course_strand" id="modal_course_strand" required>
                            <option value="">Select Course</option>
                            <?php foreach($courseList as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group" style="flex: 1;">
                        <label>Year/Grade Level</label>
                        <select name="year_grade_level" id="modal_year_grade" required>
                            <option value="">Select Year/Grade</option>
                            <?php foreach($yearList as $y): ?><option value="<?php echo htmlspecialchars($y); ?>"><?php echo htmlspecialchars($y); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group" style="flex: 1;">
                        <label>Section</label>
                        <select name="section" id="modal_section" required>
                            <option value="">Select Section</option>
                            <?php foreach($sectionList as $s): ?><option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" id="modalSubmitBtn" name="add_single" class="btn-primary" style="width: 100%; margin-top: 10px;">Save Student</button>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT PARA SA MODAL AT DELETE CONFIRMATION -->
    <script>
        const modal = document.getElementById("studentModal");

        function openAddModal() {
            document.getElementById("modalTitle").innerText = "Add New Student";
            document.getElementById("modalSubmitBtn").name = "add_single";
            document.getElementById("modalSubmitBtn").innerText = "Add Student";
            
            // Laging linisin ang form kapag Add
            document.getElementById("modal_student_id").value = "";
            document.getElementById("modal_student_number").value = "";
            document.getElementById("modal_first_name").value = "";
            document.getElementById("modal_middle_initial").value = "";
            document.getElementById("modal_last_name").value = "";
            document.getElementById("modal_department").value = "";
            document.getElementById("modal_course_strand").value = "";
            document.getElementById("modal_year_grade").value = "";
            document.getElementById("modal_section").value = "";

            modal.style.display = "block";
        }

        function openEditModal(id, st_no, fname, mi, lname, dept, course, yr, sec) {
            document.getElementById("modalTitle").innerText = "Edit Student Info";
            document.getElementById("modalSubmitBtn").name = "edit_student";
            document.getElementById("modalSubmitBtn").innerText = "Update Data";
            
            // Ilagay ang current data sa textbox at piliin sa dropdown
            document.getElementById("modal_student_id").value = id;
            document.getElementById("modal_student_number").value = st_no;
            document.getElementById("modal_first_name").value = fname;
            document.getElementById("modal_middle_initial").value = mi;
            document.getElementById("modal_last_name").value = lname;
            
            // Hanapin ang option na kapareho at i-select
            setSelectedValue("modal_department", dept);
            setSelectedValue("modal_course_strand", course);
            setSelectedValue("modal_year_grade", yr);
            setSelectedValue("modal_section", sec);

            modal.style.display = "block";
        }

        // Helper function para madaling pumili sa Dropdown gamit ang Javascript
        function setSelectedValue(selectId, valueToSet) {
            let selectObj = document.getElementById(selectId);
            for (let i = 0; i < selectObj.options.length; i++) {
                if (selectObj.options[i].text === valueToSet || selectObj.options[i].value === valueToSet) {
                    selectObj.options[i].selected = true;
                    return;
                }
            }
            // Kung wala sa listahan ang value, idagdag bilang 'Other' para di masira (Fallback)
            if(valueToSet !== "") {
                let option = document.createElement("option");
                option.text = valueToSet;
                option.value = valueToSet;
                option.selected = true;
                selectObj.add(option);
            }
        }

        function closeModal() {
            modal.style.display = "none";
        }

        // Isara kapag clinick sa labas ng form
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Delete Student?',
                html: "Are you sure you want to delete <b>" + name + "</b>?<br>This will also permanently delete their attendance records.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm_' + id).submit();
                }
            });
        }
    </script>

    <!-- jQuery & DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                "pageLength": 10, // Default na dami ng estudyante per page
                "lengthMenu": [10, 25, 50, 100, 500], // Mga options
                "language": {
                    "search": "🔍 Search Student:",
                    "lengthMenu": "Show _MENU_ students per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ students"
                },
                "order": [[ 1, "asc" ]] // Naka-sort by Name (Column 2) agad
            });
        });
    </script>
</body>
</html>