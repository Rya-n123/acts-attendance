<?php
// admin/update_status.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['attendance_id']) && validateCsrfToken()) {
    $attendance_id = $_POST['attendance_id'];
    $field_name = $_POST['field_name'];
    $new_value = $_POST['new_value'];

    $allowed_fields = ['time_in_status', 'time_out_status'];
    $allowed_values = [
        'time_in_status' => ['Present', 'Late'],
        'time_out_status' => ['Normal Out', 'Early Out']
    ];
    
    if (in_array($field_name, $allowed_fields) && isset($allowed_values[$field_name]) && in_array($new_value, $allowed_values[$field_name])) {
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET $field_name = ? WHERE id = ?");
            if ($stmt->execute([$new_value, $attendance_id])) {
                echo json_encode(['status' => 'success', 'message' => 'Status updated.']);
                logActivity($pdo, 'Override Status', "Attendance ID $attendance_id: $field_name changed to $new_value");
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid field.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>