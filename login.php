<?php
// Suppress HTML error output
error_reporting(0);
ini_set('display_errors', '0');

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Validate input
$role = $_POST['role'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($role) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate table
$table = $role === 'student' ? 'students' : 'teachers';
$checkTable = $conn->query("SHOW TABLES LIKE '$table'");
if ($checkTable->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Table '$table' does not exist"]);
    exit();
}

// Validate columns
$result = $conn->query("SHOW COLUMNS FROM $table LIKE 'email'");
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Column 'email' does not exist in table '$table'"]);
    exit();
}

$stmt = $conn->prepare("SELECT id, password FROM $table WHERE email = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($userId, $hashedPassword);
    $stmt->fetch();
    if (password_verify($password, $hashedPassword)) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        echo json_encode(['success' => true, 'redirect' => $role === 'student' ? 'studentdashboard.php' : 'teacherdashboard.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
}

$stmt->close();
$conn->close();
?>