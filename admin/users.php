<?php
// admin/users.php
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$message = '';
$error = '';

// KUNG MAY SINUBMIT NA BAGONG USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user']) && validateCsrfToken()) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'admin' o 'scanner'

    if (empty($username) || empty($password)) {
        $error = 'Please enter a username and password.';
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        
        if ($stmtCheck->fetch()) {
            $error = 'Username is already taken. Please choose another one.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            
            if ($stmtInsert->execute([$username, $hashed_password, $role])) {
                $message = "Success! Account created for " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ".";
                logActivity($pdo, 'Create User', "Created account: $username ($role)");
            } else {
                $error = 'Database error occurred while saving.';
            }
        }
    }
}

// DELETE USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user']) && validateCsrfToken()) {
    $delete_id = $_POST['user_id'];
    
    // Hindi pwedeng i-delete ang sarili mong account!
    if ($delete_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$delete_id]);
            $message = "User account has been deleted.";
            logActivity($pdo, 'Delete User', "Deleted user ID: $delete_id");
        } catch (Exception $e) {
            $error = "Error deleting user account.";
        }
    }
}

// CHANGE PASSWORD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password']) && validateCsrfToken()) {
    $target_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    if (empty($new_password) || strlen($new_password) < 4) {
        $error = "Password must be at least 4 characters long.";
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmtPwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtPwd->execute([$hashed, $target_id]);
            $message = "Password successfully changed.";
            logActivity($pdo, 'Change Password', "Changed password for user ID: $target_id");
        } catch (Exception $e) {
            $error = "Error changing password.";
        }
    }
}

// Kunin lahat ng registered users para i-display
$stmtDisplay = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY role ASC, username ASC");
$usersList = $stmtDisplay->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Acts Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="display: block; height: auto;">

    <div class="admin-layout">
                <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="top-header">
                <h2 style="margin: 0; color: var(--acts-green);">Manage System Accounts</h2>
            </div>

            <?php if (!empty($message)): ?>
                <script>Swal.fire('Success!', <?php echo json_encode($message); ?>, 'success');</script>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <script>Swal.fire('Error!', <?php echo json_encode($error); ?>, 'error');</script>
            <?php endif; ?>

            <!-- Add User Form -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">Create New Account</h3>
                <p style="font-size: 14px; color: #666;">Create a <b>Scanner</b> account to be used on mobile devices.</p>
                <form action="users.php" method="POST" style="flex-wrap: wrap;">
                    <?php echo csrfTokenField(); ?>
                    <input type="text" name="username" placeholder="Username" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <input type="password" name="password" placeholder="Password" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <select name="role" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <option value="scanner">Scanner (Mobile/Kiosk)</option>
                        <option value="admin">Admin (Full Access)</option>
                    </select>
                    <button type="submit" name="add_user" class="btn-primary">Add Account</button>
                </form>
            </div>

            <!-- Users List Table -->
            <div class="admin-card">
                <h3 style="margin-top: 0; color: var(--acts-green);">Registered Accounts (<?php echo count($usersList); ?>)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Date Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $row): ?>
                                <tr>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td>
                                        <?php if ($row['role'] === 'admin'): ?>
                                            <span style="background-color: var(--acts-green); color: var(--acts-yellow); padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">ADMIN</span>
                                        <?php else: ?>
                                            <span style="background-color: #6c757d; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">SCANNER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
                                    <td style="min-width: 180px;">
                                        <button class="btn-edit" style="background-color: #17a2b8; color: #fff; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold;" onclick="changePassword(<?php echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>')">🔑 Password</button>
                                        
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <form action="users.php" method="POST" style="display:inline;" id="delUserForm_<?php echo $row['id']; ?>">
                                                <?php echo csrfTokenField(); ?>
                                                <input type="hidden" name="delete_user" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <button type="button" style="background-color: #dc3545; color: #fff; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; margin-left: 5px;" onclick="confirmDeleteUser(<?php echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>')">🗑 Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: #999; margin-left: 5px;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    <!-- Hidden form para sa Change Password -->
    <form id="changePasswordForm" action="users.php" method="POST" style="display: none;">
        <?php echo csrfTokenField(); ?>
        <input type="hidden" name="change_password" value="1">
        <input type="hidden" name="user_id" id="pwd_user_id">
        <input type="hidden" name="new_password" id="pwd_new_password">
    </form>

    <script>
        function changePassword(userId, username) {
            Swal.fire({
                title: 'Change Password',
                html: `Set new password for <b>${username}</b>:`,
                input: 'password',
                inputPlaceholder: 'Enter new password (min 4 chars)',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                confirmButtonText: 'Change Password',
                inputValidator: (value) => {
                    if (!value || value.length < 4) {
                        return 'Password must be at least 4 characters!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('pwd_user_id').value = userId;
                    document.getElementById('pwd_new_password').value = result.value;
                    document.getElementById('changePasswordForm').submit();
                }
            });
        }

        function confirmDeleteUser(userId, username) {
            Swal.fire({
                title: 'Delete Account?',
                html: `Are you sure you want to delete <b>${username}</b>?<br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delUserForm_' + userId).submit();
                }
            });
        }
    </script>
</body>
</html>