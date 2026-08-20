<?php
// admin/classes.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$message = '';
$error = '';

$allowed_tables = ['departments', 'courses', 'year_levels', 'sections'];

// ADD ITEM LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item']) && validateCsrfToken()) {
    $table = $_POST['table_name'];
    $name = trim($_POST['item_name']);
    
    if (in_array($table, $allowed_tables) && !empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `$table` (`name`) VALUES (?)");
            $stmt->execute([$name]);
            $message = "Success! Added '$name'.";
        } catch (PDOException $e) {
            $error = "Error: Item might already exist.";
        }
    }
}

// DELETE ITEM LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_item']) && validateCsrfToken()) {
    $table = $_POST['table_name'];
    $id = $_POST['item_id'];
    
    if (in_array($table, $allowed_tables)) {
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Item successfully removed.";
    }
}

// FETCH ALL DATA
$depts = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll();
$years = $pdo->query("SELECT * FROM year_levels ORDER BY name ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Options - ACTS Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-delete { background-color: #dc3545; color: #fff; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; }
        .mini-form { display: flex; gap: 10px; margin-bottom: 15px; }
        .mini-form input { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .mini-form button { padding: 8px 12px; background-color: var(--acts-green); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        @media (max-width: 768px) { .grid-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">Manage Masterlist Options</h2>
            </div>

            <?php if (!empty($message)): ?>
                <script>Swal.fire('Success!', <?php echo json_encode($message); ?>, 'success');</script>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <script>Swal.fire('Error!', <?php echo json_encode($error); ?>, 'error');</script>
            <?php endif; ?>

            <div class="grid-container">
                
                <!-- DEPARTMENTS -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; color: var(--acts-green);">Departments</h3>
                    <form action="classes.php" method="POST" class="mini-form">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="table_name" value="departments">
                        <input type="text" name="item_name" placeholder="Add Department..." required>
                        <button type="submit" name="add_item">+ Add</button>
                    </form>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($depts as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td style="text-align: right; width: 50px;">
                                        <form action="classes.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="table_name" value="departments">
                                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_item" class="btn-delete" onclick="return confirm('Delete this?');">Del</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- COURSES / STRANDS -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; color: var(--acts-green);">Courses / Strands</h3>
                    <form action="classes.php" method="POST" class="mini-form">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="table_name" value="courses">
                        <input type="text" name="item_name" placeholder="Add Course/Strand..." required>
                        <button type="submit" name="add_item">+ Add</button>
                    </form>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($courses as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td style="text-align: right; width: 50px;">
                                        <form action="classes.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="table_name" value="courses">
                                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_item" class="btn-delete" onclick="return confirm('Delete this?');">Del</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- YEAR / GRADE LEVELS -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; color: var(--acts-green);">Year / Grade Levels</h3>
                    <form action="classes.php" method="POST" class="mini-form">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="table_name" value="year_levels">
                        <input type="text" name="item_name" placeholder="Add Year/Grade..." required>
                        <button type="submit" name="add_item">+ Add</button>
                    </form>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($years as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td style="text-align: right; width: 50px;">
                                        <form action="classes.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="table_name" value="year_levels">
                                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_item" class="btn-delete" onclick="return confirm('Delete this?');">Del</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- SECTIONS -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; color: var(--acts-green);">Sections</h3>
                    <form action="classes.php" method="POST" class="mini-form">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="table_name" value="sections">
                        <input type="text" name="item_name" placeholder="Add Section..." required>
                        <button type="submit" name="add_item">+ Add</button>
                    </form>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($sections as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td style="text-align: right; width: 50px;">
                                        <form action="classes.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="table_name" value="sections">
                                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_item" class="btn-delete" onclick="return confirm('Delete this?');">Del</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</body>
</html>