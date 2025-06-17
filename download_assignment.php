<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once 'db.php';

// Debug session data
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: index.html');
    exit();
}

if ($_SESSION['role'] !== 'student') {
    header('Location: index.html');
    exit();
}

$studentId = $_SESSION['user_id'];
$assignmentId = $_GET['id'] ?? '';

if (empty($assignmentId)) {
    die("Error: Assignment ID is required.");
}

// Fetch student details to get division and branch
$stmt = $conn->prepare("SELECT division, branch FROM students WHERE id = ?");
if (!$stmt) {
    die("Error preparing student query: " . $conn->error);
}
$stmt->bind_param('i', $studentId);
$stmt->execute();
$stmt->bind_result($division, $branch);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Error: Student not found.");
}
$stmt->close();

// Fetch the assignment and verify it matches the student's division and branch
$stmt = $conn->prepare("SELECT file_name, file_data 
                        FROM assignments 
                        WHERE id = ? AND division = ? AND branch = ?");
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param('iss', $assignmentId, $division, $branch);
$stmt->execute();
$stmt->bind_result($fileName, $fileData);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Error: Assignment not found or you do not have permission to access it.");
}
$stmt->close();
$conn->close();

// Send the file to the browser for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . htmlspecialchars($fileName) . '"');
header('Content-Length: ' . strlen($fileData));
echo $fileData;
exit();
?>