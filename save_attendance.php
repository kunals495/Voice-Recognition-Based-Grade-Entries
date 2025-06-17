<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit();
}

$division = $_POST['division'] ?? '';
$teacherId = $_POST['teacher_id'] ?? $_SESSION['user_id'];
$studentId = $_POST['student_id'] ?? '';
$monthStart = $_POST['month_start'] ?? 'January';
$monthEnd = $_POST['month_end'] ?? 'December';
$subject = $_POST['subject'] ?? '';
$attendance = $_POST['attendance'] ?? [];

if (empty($division) || empty($studentId) || empty($attendance) || empty($subject)) {
    header('Location: teacherdashboard.php');
    exit();
}

// Define months for validation
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthStartIndex = array_search($monthStart, $months);
$monthEndIndex = array_search($monthEnd, $months);
$selectedMonths = array_slice($months, $monthStartIndex, $monthEndIndex - $monthStartIndex + 1);

// Default values for year and semester
$year = 0;
$semester = 0;

// Begin transaction
$conn->begin_transaction();

try {
    // Delete existing attendance records for this student, division, teacher, subject, and months
    $stmt = $conn->prepare("DELETE FROM attendance 
                            WHERE student_id = ? 
                            AND division = ? 
                            AND teacher_id = ? 
                            AND subject = ? 
                            AND month IN (" . implode(',', array_fill(0, count($selectedMonths), '?')) . ")");
    $params = array_merge([$studentId, $division, $teacherId, $subject], $selectedMonths);
    $types = 'ssis' . str_repeat('s', count($selectedMonths));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    // Insert new attendance records
    $stmt = $conn->prepare("INSERT INTO attendance (student_id, division, year, semester, month, attendance, teacher_id, attendance_type, subject) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($attendance as $type => $months) {
        foreach ($months as $month => $att) {
            if ($att !== '') { // Only save if attendance is provided
                $stmt->bind_param('isiississ', $studentId, $division, $year, $semester, $month, $att, $teacherId, $type, $subject);
                $stmt->execute();
            }
        }
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    die("Error saving attendance: " . $e->getMessage());
}

$conn->close();
header('Location: teacherdashboard.php');
exit();
?>