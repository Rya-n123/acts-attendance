<?php
// login.php
session_start();
require_once 'config/db.php';

// Kung naka-login na, i-redirect agad para hindi na makita ang login page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: scanner/index.php");
    }
    exit();
}

$error = '';
$login_success = false;
$role_redirect = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent Session Fixation Attack
            session_regenerate_id(true);
            
            // Success login! Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $login_success = true;
            $role_redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'scanner/index.php';
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acts Attendance System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="login-container">
        <h2>Acts Attendance</h2>
        <p>System Login</p>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Mag-login</button>
        </form>
    </div>

    <!-- SweetAlert2 Logic -->
    <script>
        // 1. Kapag nag-error sa login (Maling password/username)
        <?php if (!empty($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: <?php echo json_encode($error); ?>,
                confirmButtonColor: '#033500',
                heightAuto: false // <-- PUMIPIGIL SA PAG-ANGAT
            });
        <?php endif; ?>

        // 2. On SUCCESS login
        <?php if ($login_success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Login Successful!',
                text: 'Redirecting to the system...',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                heightAuto: false // <-- PUMIPIGIL SA PAG-ANGAT
            }).then(() => {
                window.location.href = '<?php echo $role_redirect; ?>';
            });
        <?php endif; ?>

        // 3. On LOGOUT
        <?php if (isset($_GET['status']) && $_GET['status'] == 'logged_out'): ?>
            Swal.fire({
                icon: 'info',
                title: 'Goodbye!',
                text: 'You have successfully logged out.',
                confirmButtonColor: '#033500',
                heightAuto: false // <-- PUMIPIGIL SA PAG-ANGAT
            });
            
            // Tanggalin ang "?status=logged_out" sa URL para malinis tingnan
            window.history.replaceState(null, null, window.location.pathname);
        <?php endif; ?>
    </script>
</body>
</html>