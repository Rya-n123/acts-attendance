<?php
// config/db.php

$host = 'localhost';
$dbname = 'acts_attendance_db';
$username = 'root'; // Default username ng XAMPP
$password = '';     // Default password ng XAMPP ay blank

try {
    // Naka-set ang charset sa utf8mb4 para sa mga 'ñ' at special characters
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode to exception para madaling makita kung may mali sa query
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Gawing array ang default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>