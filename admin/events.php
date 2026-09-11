<?php
// admin/events.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$message = '';
$error = '';

// CREATE EVENT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event']) && validateCsrfToken()) {
    $event_name = trim($_POST['event_name']);
    $event_date = $_POST['event_date'];
    $time_in_late = $_POST['time_in_late'];
    $time_in_absent = $_POST['time_in_absent'];
    $time_out_start = $_POST['time_out_start'];
    
    if (empty($event_name) || empty($event_date)) {
        $error = "Please fill in the event name and date.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (event_name, event_date, time_in_late, time_in_absent, time_out_start, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$event_name, $event_date, $time_in_late, $time_in_absent, $time_out_start, $_SESSION['user_id']]);
            $message = "Event '$event_name' has been created!";
            logActivity($pdo, 'Create Event', "Created: $event_name ($event_date)");
        } catch (Exception $e) {
            $error = "Error creating event.";
        }
    }
}

// TOGGLE EVENT STATUS (Active/Closed)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_event']) && validateCsrfToken()) {
    $event_id = $_POST['event_id'];
    $new_status = $_POST['new_status'];
    
    if (in_array($new_status, ['Active', 'Closed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $event_id]);
            $message = "Event status changed to $new_status.";
            logActivity($pdo, 'Toggle Event', "Event ID $event_id set to $new_status");
        } catch (Exception $e) {
            $error = "Error updating event status.";
        }
    }
}

// DELETE EVENT (and its attendance records)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_event']) && validateCsrfToken()) {
    $event_id = $_POST['event_id'];
    try {
        $pdo->beginTransaction();
        $stmtDelAtt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
        $stmtDelAtt->execute([$event_id]);
        $stmtDelEvt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmtDelEvt->execute([$event_id]);
        $pdo->commit();
        $message = "Event and its attendance records have been deleted.";
        logActivity($pdo, 'Delete Event', "Deleted event ID: $event_id");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting event.";
    }
}

// Fetch all events
$events = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM attendance a WHERE a.event_id = e.id) as scan_count FROM events e ORDER BY e.event_date DESC, e.created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .event-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid var(--acts-green); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .event-card.closed { border-left-color: #6c757d; opacity: 0.7; }
        .event-info h4 { margin: 0 0 5px 0; color: var(--acts-green); font-size: 18px; }
        .event-info.closed h4 { color: #6c757d; }
        .event-meta { font-size: 13px; color: #666; }
        .event-meta span { margin-right: 15px; }
        .event-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .event-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-open { background-color: #d4edda; color: #155724; }
        .badge-closed { background-color: #e2e3e5; color: #383d41; }
    </style>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">🎪 Manage Events</h2>
            </div>

            <?php if (!empty($message)): ?>
                <script>Swal.fire('Success!', <?php echo json_encode($message); ?>, 'success');</script>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <script>Swal.fire('Error!', <?php echo json_encode($error); ?>, 'error');</script>
            <?php endif; ?>

            <!-- Create New Event -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">Create New Event</h3>
                <p style="font-size: 14px; color: #666;">Set up a new attendance event with its own time thresholds.</p>
                <form action="events.php" method="POST">
                    <?php echo csrfTokenField(); ?>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                        <input type="text" name="event_name" placeholder="Event Name (e.g. Monthly Assembly - August)" required style="flex: 2; min-width: 250px; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <input type="date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required style="flex: 1; min-width: 150px; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                        <div style="flex: 1; min-width: 140px;">
                            <label style="font-size: 12px; font-weight: bold; color: var(--acts-green);">⏰ Late After</label>
                            <input type="time" name="time_in_late" value="10:01" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="font-size: 12px; font-weight: bold; color: var(--acts-green);">🚫 Absent After</label>
                            <input type="time" name="time_in_absent" value="12:01" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="font-size: 12px; font-weight: bold; color: var(--acts-green);">🚪 Normal Out After</label>
                            <input type="time" name="time_out_start" value="17:00" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <button type="submit" name="create_event" class="btn-primary">+ Create Event</button>
                </form>
            </div>

            <!-- Events List -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">All Events (<?php echo count($events); ?>)</h3>
                
                <?php if (empty($events)): ?>
                    <p style="text-align: center; color: #999; padding: 30px;">No events yet. Create your first event above!</p>
                <?php endif; ?>

                <?php foreach ($events as $evt): ?>
                    <div class="event-card <?php echo $evt['status'] === 'Closed' ? 'closed' : ''; ?>">
                        <div class="event-info <?php echo $evt['status'] === 'Closed' ? 'closed' : ''; ?>">
                            <h4>
                                <?php echo htmlspecialchars($evt['event_name']); ?>
                                <span class="event-badge <?php echo $evt['status'] === 'Active' ? 'badge-open' : 'badge-closed'; ?>">
                                    <?php echo $evt['status'] === 'Active' ? '● ACTIVE' : '● CLOSED'; ?>
                                </span>
                            </h4>
                            <div class="event-meta">
                                <span>📅 <?php echo date('F d, Y (l)', strtotime($evt['event_date'])); ?></span>
                                <span>👥 <?php echo $evt['scan_count']; ?> scans</span>
                                <span>⏰ Late: <?php echo date('h:i A', strtotime($evt['time_in_late'])); ?></span>
                                <span>🚫 Absent: <?php echo date('h:i A', strtotime($evt['time_in_absent'])); ?></span>
                                <span>🚪 Out: <?php echo date('h:i A', strtotime($evt['time_out_start'])); ?></span>
                            </div>
                        </div>
                        <div class="event-actions">
                            <!-- Toggle Status -->
                            <form action="events.php" method="POST" style="display:inline;">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="toggle_event" value="1">
                                <input type="hidden" name="event_id" value="<?php echo $evt['id']; ?>">
                                <?php if ($evt['status'] === 'Active'): ?>
                                    <input type="hidden" name="new_status" value="Closed">
                                    <button type="submit" style="background: #6c757d; color: #fff; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: bold;">🔒 Close</button>
                                <?php else: ?>
                                    <input type="hidden" name="new_status" value="Active">
                                    <button type="submit" style="background: #28a745; color: #fff; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: bold;">🔓 Reopen</button>
                                <?php endif; ?>
                            </form>
                            
                            <!-- Delete Event -->
                            <form action="events.php" method="POST" style="display:inline;" id="delEventForm_<?php echo $evt['id']; ?>">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="delete_event" value="1">
                                <input type="hidden" name="event_id" value="<?php echo $evt['id']; ?>">
                                <button type="button" style="background: #dc3545; color: #fff; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: bold;" onclick="confirmDeleteEvent(<?php echo $evt['id']; ?>, '<?php echo addslashes($evt['event_name']); ?>', <?php echo $evt['scan_count']; ?>)">🗑 Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmDeleteEvent(eventId, eventName, scanCount) {
            let warning = scanCount > 0 ? `<br><br>⚠️ This will also delete <b>${scanCount} attendance records</b>!` : '';
            Swal.fire({
                title: 'Delete Event?',
                html: `Are you sure you want to delete <b>${eventName}</b>?${warning}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delEventForm_' + eventId).submit();
                }
            });
        }
    </script>
</body>
</html>