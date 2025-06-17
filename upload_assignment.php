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

if ($_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit();
}

$teacherId = $_SESSION['user_id'];
$division = $_POST['division'] ?? '';
$branch = $_POST['branch'] ?? '';
$year = $_POST['year'] ?? '';

// Validate input
if (empty($division) || empty($branch) || empty($year)) {
    die("Error: Division, Branch, and Year are required.");
}

// Handle file upload
if (!isset($_FILES['assignment_file']) || $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK) {
    die("Error: File upload failed.");
}

$fileName = $_FILES['assignment_file']['name'];
$fileTmpName = $_FILES['assignment_file']['tmp_name'];
$fileSize = $_FILES['assignment_file']['size'];
$fileType = mime_content_type($fileTmpName);

// Validate file type and size
if ($fileType !== 'application/pdf') {
    die("Error: Only PDF files are allowed.");
}
if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
    die("Error: File size exceeds 5MB limit.");
}

// Read file content
$fileData = file_get_contents($fileTmpName);
if ($fileData === false) {
    die("Error: Unable to read file content.");
}

// Insert into assignments table
$stmt = $conn->prepare("INSERT INTO assignments (teacher_id, branch, year, division, file_name, file_data) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    die("Error preparing insert query: " . $conn->error);
}

$stmt->bind_param('isssss', $teacherId, $branch, $year, $division, $fileName, $fileData);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    // Redirect back to results.php without success message
    header("Location: results.php?division=" . urlencode($division) . "&branch=" . urlencode($branch) . "&year=" . urlencode($year));
    exit();
} else {
    die("Error uploading assignment: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>