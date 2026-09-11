<?php
// config/session.php

// =========================================
// SECURITY HEADERS (For A+ Security Grade)
// =========================================
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Naka-allow ang camera dito para sa QR/Barcode Scanner natin
header("Permissions-Policy: camera=(self), microphone=(), geolocation=()");

// Secure session cookie settings
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => true // Naka-ON na ito dahil naka-HTTPS na ang Acts Attendance online!
]);

session_start();

// =========================================
// CSRF TOKEN FUNCTIONS
// =========================================
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function validateCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// =========================================
// ACTIVITY LOG FUNCTION
// =========================================
function logActivity($pdo, $action, $details = '') {
    try {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';
        
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, username, action, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $username, $action, $details]);
    } catch (Exception $e) {
        // Huwag hayaang masira ang main function kung mag-fail ang logging
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

// Function para i-check kung naka-login ba ang user
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Kung hindi naka-login, ibato pabalik sa login page
        header("Location: ../login.php");
        exit();
    }
}

// Function para protektahan ang ADMIN pages
function requireAdmin() {
    checkLogin(); // Check muna kung naka-login
    
    if ($_SESSION['role'] !== 'admin') {
        // Kung staff/scanner siya tapos pinilit pasukin ang admin page, ibalik sa scanner
        header("Location: ../scanner/index.php");
        exit();
    }
}

// Function para protektahan ang SCANNER pages
function requireScanner() {
    checkLogin(); 
    // Hahayaan nating makapasok ang admin dito in case kailangan din nilang mag-scan,
    // pero protected ito laban sa mga hindi naka-login.
}
?>