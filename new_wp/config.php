<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'event';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function showError($message) {
    session_start();
    $_SESSION['error'] = $message;
    header("Location: error.php");
    exit();
}
?>