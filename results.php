<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once 'db.php';

// Debug session data
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "Session not set. Redirecting to index.html...";
    header('Location: index.html');
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    echo "Invalid role. Redirecting to index.html...";
    header('Location: index.html');
    exit();
}

$userId = $_SESSION['user_id'];

// Debug: Check user ID
if (empty($userId)) {
    echo "User ID is empty. Redirecting to index.html...";
    header('Location: index.html');
    exit();
}

// Fetch teacher details
$checkTable = $conn->query("SHOW TABLES LIKE 'teachers'");
if ($checkTable->num_rows === 0) {
    die("Error: Table 'teachers' does not exist in database 'kbp_portal'.");
}
$checkTable->free();

$requiredColumns = ['name', 'email', 'subject'];
$missingColumns = [];
foreach ($requiredColumns as $col) {
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE '$col'");
    if ($result->num_rows === 0) {
        $missingColumns[] = $col;
    }
    $result->free();
}
if (!empty($missingColumns)) {
    die("Error: Missing columns in 'teachers' table: " . implode(', ', $missingColumns));
}

$stmt = $conn->prepare("SELECT name, email, subject FROM teachers WHERE id = ?");
if (!$stmt) {
    die("Error: Query preparation failed: " . $conn->error);
}

$stmt->bind_param('i', $userId);
if (!$stmt->execute()) {
    die("Error: Query execution failed: " . $stmt->error);
}

$stmt->bind_result($name, $email, $subject);
$stmt->fetch();
$stmt->close();

// Fetch unique divisions, branches, and years from students table
$divisions = [];
$branches = [];
$years = [];

$result = $conn->query("SELECT DISTINCT division FROM students ORDER BY division");
if (!$result) {
    die("Error fetching divisions: " . $conn->error);
}
while ($row = $result->fetch_assoc()) {
    $divisions[] = $row['division'];
}
$result->free();

$result = $conn->query("SELECT DISTINCT branch FROM students ORDER BY branch");
if (!$result) {
    die("Error fetching branches: " . $conn->error);
}
while ($row = $result->fetch_assoc()) {
    $branches[] = $row['branch'];
}
$result->free();

$result = $conn->query("SELECT DISTINCT year FROM students");
if (!$result) {
    die("Error fetching years: " . $conn->error);
}
while ($row = $result->fetch_assoc()) {
    $years[] = $row['year'];
}
$result->free();

// Fetch roll numbers based on selected filters
$rollNumbers = [];
$selectedDivision = $_POST['division'] ?? $_GET['division'] ?? $divisions[0] ?? '';
$selectedBranch = $_POST['branch'] ?? $_GET['branch'] ?? $branches[0] ?? '';
$selectedYear = $_POST['year'] ?? $_GET['year'] ?? $years[0] ?? '';
$selectedStudentId = $_POST['roll_number'] ?? $_GET['roll_number'] ?? '';

if ($selectedDivision && $selectedBranch && $selectedYear) {
    $stmt = $conn->prepare("SELECT id, roll_number FROM students WHERE division = ? AND branch = ? AND year = ? ORDER BY roll_number");
    if (!$stmt) {
        die("Error preparing roll numbers query: " . $conn->error);
    }
    $stmt->bind_param('sss', $selectedDivision, $selectedBranch, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rollNumbers[$row['id']] = $row['roll_number'];
    }
    $stmt->close();
}

// Fetch existing marks for the selected student
$existingMarks = [];
if ($selectedStudentId) {
    $stmt = $conn->prepare("SELECT unit_test_1, unit_test_2, end_semester, practical 
                            FROM marks 
                            WHERE student_id = ? AND year = ? AND branch = ? AND division = ?");
    if (!$stmt) {
        die("Error preparing marks query: " . $conn->error);
    }
    $stmt->bind_param('isss', $selectedStudentId, $selectedYear, $selectedBranch, $selectedDivision);
    $stmt->execute();
    $stmt->bind_result($unitTest1, $unitTest2, $endSemester, $practical);
    if ($stmt->fetch()) {
        $existingMarks = [
            'unit_test_1' => $unitTest1,
            'unit_test_2' => $unitTest2,
            'end_semester' => $endSemester,
            'practical' => $practical
        ];
    }
    $stmt->close();
}

// Fetch uploaded assignments for this teacher
$assignments = [];
$stmt = $conn->prepare("SELECT id, division, branch, year, file_name, uploaded_at FROM assignments WHERE teacher_id = ? ORDER BY uploaded_at DESC");
if (!$stmt) {
    die("Error preparing assignments query: " . $conn->error);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results & Assessment</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                
            </div>
            <ul class="sidebar-menu">
                <li><a href="teacherdashboard.php">Dashboard</a></li>
                <li><a href="results.php" class="active">Results & Assessment</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <header class="dashboard-header">
                <h1>Faculty</h1>
                <div class="profile-section">
                    <span class="profile-name"><?php echo htmlspecialchars($name); ?></span>
                    <div class="profile-icon" onclick="toggleProfileDropdown()">👤</div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($subject); ?></p>
                    </div>
                </div>
            </header>
            <div class="content">
                <h2>Results & Assessment</h2>
                <form id="filterForm" method="POST" action="results.php">
                    <div class="filter-section">
                        <label for="division">Division:</label>
                        <select name="division" id="division" onchange="document.getElementById('filterForm').submit()">
                            <?php foreach ($divisions as $div) { ?>
                                <option value="<?php echo htmlspecialchars($div); ?>" <?php echo $selectedDivision === $div ? 'selected' : ''; ?>><?php echo htmlspecialchars($div); ?></option>
                            <?php } ?>
                        </select>

                        <label for="branch">Branch:</label>
                        <select name="branch" id="branch" onchange="document.getElementById('filterForm').submit()">
                            <?php foreach ($branches as $br) { ?>
                                <option value="<?php echo htmlspecialchars($br); ?>" <?php echo $selectedBranch === $br ? 'selected' : ''; ?>><?php echo htmlspecialchars($br); ?></option>
                            <?php } ?>
                        </select>

                        <label for="year">Year:</label>
                        <select name="year" id="year" onchange="document.getElementById('filterForm').submit()">
                            <?php foreach ($years as $yr) { ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo $selectedYear === $yr ? 'selected' : ''; ?>><?php echo htmlspecialchars($yr); ?></option>
                            <?php } ?>
                        </select>

                        <label for="roll_number">Roll Number:</label>
                        <select name="roll_number" id="roll_number" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Select Roll Number</option>
                            <?php foreach ($rollNumbers as $id => $roll) { ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $selectedStudentId == $id ? 'selected' : ''; ?>><?php echo htmlspecialchars($roll); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedStudentId) { ?>
                    <form id="marksForm" method="POST" action="save_marks.php">
                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selectedStudentId); ?>">
                        <input type="hidden" name="roll_number" value="<?php echo htmlspecialchars($rollNumbers[$selectedStudentId]); ?>">
                        <input type="hidden" name="division" value="<?php echo htmlspecialchars($selectedDivision); ?>">
                        <input type="hidden" name="branch" value="<?php echo htmlspecialchars($selectedBranch); ?>">
                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Assessment</th>
                                    <th>Marks Scored</th>
                                    <th>Out of</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Unit Test 1</td>
                                    <td><input type="number" name="unit_test_1" id="unit_test_1" value="<?php echo htmlspecialchars($existingMarks['unit_test_1'] ?? 0); ?>" min="0" max="20" required></td>
                                    <td>20</td>
                                </tr>
                                <tr>
                                    <td>Unit Test 2</td>
                                    <td><input type="number" name="unit_test_2" id="unit_test_2" value="<?php echo htmlspecialchars($existingMarks['unit_test_2'] ?? 0); ?>" min="0" max="20" required></td>
                                    <td>20</td>
                                </tr>
                                <tr>
                                    <td>End Semester</td>
                                    <td><input type="number" name="end_semester" id="end_semester" value="<?php echo htmlspecialchars($existingMarks['end_semester'] ?? 0); ?>" min="0" max="100" required></td>
                                    <td>100</td>
                                </tr>
                                <tr>
                                    <td>Practical</td>
                                    <td><input type="number" name="practical" id="practical" value="<?php echo htmlspecialchars($existingMarks['practical'] ?? 0); ?>" min="0" max="50" required></td>
                                    <td>50</td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" id="voiceInputBtn">🎙️ Voice Input</button>
                        <button type="submit">Save Marks</button>
                    </form>
                <?php } ?>

                <div class="assignment-section">
                    <h3>Upload Assignment</h3>
                    <button id="addAssignmentBtn" onclick="document.getElementById('assignmentForm').style.display='block'">+</button>
                    <form id="assignmentForm" method="POST" action="upload_assignment.php" enctype="multipart/form-data" style="display: none;">
                        <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($userId); ?>">
                        <label for="assignment_division">Division:</label>
                        <select name="division" id="assignment_division" required>
                            <?php foreach ($divisions as $div) { ?>
                                <option value="<?php echo htmlspecialchars($div); ?>"><?php echo htmlspecialchars($div); ?></option>
                            <?php } ?>
                        </select>
                        <label for="assignment_branch">Branch:</label>
                        <select name="branch" id="assignment_branch" required>
                            <?php foreach ($branches as $br) { ?>
                                <option value="<?php echo htmlspecialchars($br); ?>"><?php echo htmlspecialchars($br); ?></option>
                            <?php } ?>
                        </select>
                        <label for="assignment_year">Year:</label>
                        <select name="year" id="assignment_year" required>
                            <?php foreach ($years as $yr) { ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo $selectedYear === $yr ? 'selected' : ''; ?>><?php echo htmlspecialchars($yr); ?></option>
                            <?php } ?>
                        </select>
                        <label for="assignment_file">Upload PDF:</label>
                        <input type="file" name="assignment_file" id="assignment_file" accept="application/pdf" required>
                        <button type="submit">Upload</button>
                    </form>

                    <h3>Uploaded Assignments</h3>
                    <?php if (empty($assignments)) { ?>
                        <p>No assignments uploaded yet.</p>
                    <?php } else { ?>
                        <table class="assignments-table">
                            <thead>
                                <tr>
                                    <th>Division</th>
                                    <th>Branch</th>
                                    <th>Year</th>
                                    <th>File Name</th>
                                    <th>Uploaded At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['division']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['branch']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['year']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['file_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['uploaded_at']); ?></td>
                                        <td>
                                            <a href="download_assignment.php?id=<?php echo htmlspecialchars($assignment['id']); ?>" target="_blank">Download</a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        window.onclick = function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const icon = document.querySelector('.profile-icon');
            if (!icon.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        };

        // Voice input functionality
        const voiceInputBtn = document.getElementById('voiceInputBtn');
        if (voiceInputBtn) {
            voiceInputBtn.addEventListener('click', () => {
                if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
                    alert('Speech recognition not supported in this browser. Please use Chrome or Edge.');
                    return;
                }

                const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
                recognition.lang = 'en-US';
                recognition.interimResults = false;
                recognition.maxAlternatives = 1;

                recognition.onstart = () => {
                    voiceInputBtn.textContent = '🎙️ Listening...';
                    voiceInputBtn.disabled = true;
                };

                recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript.trim();
                    const numbers = transcript.split(/\s+/).map(num => parseInt(num)).filter(num => !isNaN(num));

                    if (numbers.length === 4) {
                        document.getElementById('unit_test_1').value = Math.min(numbers[0], 20);
                        document.getElementById('unit_test_2').value = Math.min(numbers[1], 20);
                        document.getElementById('end_semester').value = Math.min(numbers[2], 100);
                        document.getElementById('practical').value = Math.min(numbers[3], 50);
                        alert('Marks populated: ' + numbers.join(', ') + '\nClick "Save Marks" to store them in the database.');
                    } else {
                        alert('Please speak exactly 4 numbers in sequence (e.g., "15 18 85 45").');
                    }
                };

                recognition.onend = () => {
                    voiceInputBtn.textContent = '🎙️ Voice Input';
                    voiceInputBtn.disabled = false;
                };

                recognition.onerror = (event) => {
                    alert('Error with speech recognition: ' + event.error);
                    voiceInputBtn.textContent = '🎙️ Voice Input';
                    voiceInputBtn.disabled = false;
                };

                recognition.start();
            });
        }
    </script>
    <style>
        .assignments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .assignments-table th, .assignments-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .assignments-table th {
            background-color: #f2f2f2;
        }
        .assignments-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .assignments-table a {
            color: #007bff;
            text-decoration: none;
        }
        .assignments-table a:hover {
            text-decoration: underline;
        }
    </style>
</body>
</html>