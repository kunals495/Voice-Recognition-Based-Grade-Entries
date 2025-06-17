<?php
// Enable error logging but suppress display
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'error.log');

require_once 'db.php';

header('Content-Type: application/json');

$role = $_POST['role'] ?? '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($role) || empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit();
}

// Validate table
$table = $role === 'student' ? 'students' : 'teachers';
$checkTable = $conn->query("SHOW TABLES LIKE '$table'");
if ($checkTable->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Table '$table' does not exist"]);
    exit();
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
if (!$stmt) {
    error_log("Email check query preparation failed: " . $conn->error, 3, 'error.log');
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $stmt->close();
    exit();
}
$stmt->close();

$password = password_hash($password, PASSWORD_DEFAULT);

if ($role === 'student') {
    $roll = $_POST['roll_number'] ?? '';
    $prn = $_POST['prn_number'] ?? '';
    $division = $_POST['division'] ?? '';
    $year = $_POST['year'] ?? '';
    $branch = $_POST['branch'] ?? '';
    if (empty($roll) || empty($prn) || empty($division) || empty($year) || empty($branch)) {
        echo json_encode(['success' => false, 'message' => 'Roll number, PRN number, division, year, and branch are required']);
        exit();
    }
    // Validate division
    if (!in_array($division, ['A', 'B'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid division']);
        exit();
    }
    // Validate year
    if (!in_array($year, ['FE', 'SE', 'TE', 'BE'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid year']);
        exit();
    }
    // Validate branch
    if (!in_array($branch, ['CSE', 'E & TC', 'Civil', 'Mechanical'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid branch']);
        exit();
    }
    // Validate columns
    $requiredColumns = ['name', 'roll_number', 'prn_number', 'division', 'year', 'branch', 'email', 'password'];
    foreach ($requiredColumns as $col) {
        $result = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Column '$col' does not exist in table 'students'"]);
            exit();
        }
    }
    $stmt = $conn->prepare("INSERT INTO students (name, roll_number, prn_number, division, year, branch, email, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Student insert query preparation failed: " . $conn->error, 3, 'error.log');
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('ssssssss', $name, $roll, $prn, $division, $year, $branch, $email, $password);
} elseif ($role === 'teacher') {
    $subject = $_POST['subject'] ?? '';
    if (empty($subject)) {
        error_log("Teacher signup failed: Subject is required. Form data: " . print_r($_POST, true), 3, 'error.log');
        echo json_encode(['success' => false, 'message' => 'Subject is required']);
        exit();
    }
    // Validate columns
    $requiredColumns = ['name', 'subject', 'email', 'password'];
    foreach ($requiredColumns as $col) {
        $result = $conn->query("SHOW COLUMNS FROM teachers LIKE '$col'");
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Column '$col' does not exist in table 'teachers'"]);
            exit();
        }
    }
    $stmt = $conn->prepare("INSERT INTO teachers (name, subject, email, password) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Teacher insert query preparation failed: " . $conn->error, 3, 'error.log');
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('ssss', $name, $subject, $email, $password);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log("Query execution failed: " . $stmt->error, 3, 'error.log');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>