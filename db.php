<?php
// Suppress HTML error output
error_reporting(0); // Disable all error reporting to prevent HTML output
ini_set('display_errors', '0'); // Ensure errors are not displayed

$host = "localhost";
$user = "root"; // Replace with secure username in production
$pass = ""; // Replace with secure password in production
$db = "kbp_portal";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}
?>