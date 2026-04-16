<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'event';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = null;
$candidatePorts = [3306, 3307];

foreach ($candidatePorts as $port) {
    try {
        $conn = new mysqli($host, $username, $password, $database, $port);
        if (!$conn->connect_error) {
            break;
        }
    } catch (Throwable $exception) {
        $conn = null;
    }
}

if (!$conn || $conn->connect_error) {
    showError('Database connection failed. Please start MySQL in XAMPP or verify the database port settings.');
}

function showError($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['error'] = $message;
    header("Location: error.php");
    exit();
}
?>