<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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

// Fetch teacher's subject
$stmt = $conn->prepare("SELECT subject FROM teachers WHERE id = ?");
if (!$stmt) {
    error_log("Teacher query prepare failed: " . $conn->error);
    die("Error preparing teacher query: " . $conn->error);
}
$stmt->bind_param('i', $teacherId);
if (!$stmt->execute()) {
    error_log("Teacher query execute failed: " . $stmt->error);
    die("Error executing teacher query: " . $stmt->error);
}
$stmt->bind_result($subject);
if (!$stmt->fetch()) {
    error_log("Teacher not found for ID: $teacherId");
    die("Error: Teacher not found.");
}
$stmt->close();

// Get form data
$studentId = $_POST['student_id'] ?? '';
$rollNumber = $_POST['roll_number'] ?? '';
$division = $_POST['division'] ?? '';
$branch = $_POST['branch'] ?? '';
$year = $_POST['year'] ?? '';
$unitTest1 = $_POST['unit_test_1'] ?? 0;
$unitTest2 = $_POST['unit_test_2'] ?? 0;
$endSemester = $_POST['end_semester'] ?? 0;
$practical = $_POST['practical'] ?? 0;

// Cast student_id to integer
$studentId = (int)$studentId;

// Validate input
if (empty($studentId) || empty($rollNumber) || empty($division) || empty($branch) || empty($year)) {
    die("Error: All fields are required.");
}

// Validate numeric values
$unitTest1 = min(max((int)$unitTest1, 0), 20);
$unitTest2 = min(max((int)$unitTest2, 0), 20);
$endSemester = min(max((int)$endSemester, 0), 100);
$practical = min(max((int)$practical, 0), 50);

// Check if marks already exist for this student, year, branch, division, and subject
$stmt = $conn->prepare("SELECT id FROM marks WHERE student_id = ? AND year = ? AND branch = ? AND division = ? AND subject = ?");
if (!$stmt) {
    error_log("Check query prepare failed: " . $conn->error);
    die("Error preparing check query: " . $conn->error);
}
$stmt->bind_param('issss', $studentId, $year, $branch, $division, $subject);
if (!$stmt->execute()) {
    error_log("Check query execute failed: " . $stmt->error);
    die("Error executing check query: " . $stmt->error);
}
$result = $stmt->get_result();
$marksExist = $result->num_rows > 0;
$stmt->close();

if ($marksExist) {
    // Update existing marks
    $stmt = $conn->prepare("UPDATE marks SET teacher_id = ?, roll_number = ?, unit_test_1 = ?, unit_test_1_total = ?, unit_test_2 = ?, unit_test_2_total = ?, end_semester = ?, end_semester_total = ?, practical = ?, practical_total = ? 
                            WHERE student_id = ? AND year = ? AND branch = ? AND division = ? AND subject = ?");
    if (!$stmt) {
        error_log("Update query prepare failed: " . $conn->error);
        die("Error preparing update query: " . $conn->error);
    }
    $params = [
        &$teacherId, &$rollNumber, &$unitTest1, 20, &$unitTest2, 20, &$endSemester, 100, &$practical, 50,
        &$studentId, &$year, &$branch, &$division, &$subject
    ];
    $types = 'isiiiiiiiissss';
    $bindResult = call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
    if (!$bindResult) {
        error_log("Bind param failed: " . $stmt->error);
        die("Error binding update query parameters: " . $stmt->error);
    }
} else {
    // Insert new marks
    $stmt = $conn->prepare("INSERT INTO marks (student_id, teacher_id, subject, roll_number, division, branch, year, unit_test_1, unit_test_1_total, unit_test_2, unit_test_2_total, end_semester, end_semester_total, practical, practical_total) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Insert query prepare failed: " . $conn->error);
        die("Error preparing insert query: " . $conn->error);
    }
    $params = [
        &$studentId, &$teacherId, &$subject, &$rollNumber, &$division, &$branch, &$year,
        &$unitTest1, 20, &$unitTest2, 20, &$endSemester, 100, &$practical, 50
    ];
    $types = 'iissssiiiiiiiii';
    $bindResult = call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
    if (!$bindResult) {
        error_log("Bind param failed: " . $stmt->error);
        die("Error binding insert query parameters: " . $stmt->error);
    }
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Error saving marks: " . $stmt->error);
}

$stmt->close();
$conn->close();
header("Location: results.php?division=" . urlencode($division) . "&branch=" . urlencode($branch) . "&year=" . urlencode($year) . "&roll_number=" . urlencode($studentId) . "&success=1");
exit();
?>