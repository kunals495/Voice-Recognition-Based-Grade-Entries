<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: index.html');
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch student details
$stmt = $conn->prepare("SELECT name, email, roll_number, division, branch, year FROM students WHERE id = ?");
if (!$stmt) {
    die("Error: Query preparation failed: " . $conn->error);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($name, $email, $rollNumber, $division, $branch, $year);
$stmt->fetch();
$stmt->close();

// Fetch marks for this student
$marksData = [];
$subjects = [];
$totalObtained = 0;
$totalMax = 190; // 20 + 20 + 100 + 50 per subject
$stmt = $conn->prepare("SELECT subject, unit_test_1, unit_test_2, end_semester, practical 
                        FROM marks 
                        WHERE student_id = ? AND division = ? AND branch = ?");
if (!$stmt) {
    die("Error preparing marks query: " . $conn->error);
}
$stmt->bind_param('iss', $userId, $division, $branch);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjectTotal = $row['unit_test_1'] + $row['unit_test_2'] + $row['end_semester'] + $row['practical'];
    $totalObtained += $subjectTotal;
    // Passing criteria: 40% in End Semester (40/100) and 40% overall (76/190)
    $endSemPass = $row['end_semester'] >= 40;
    $overallPass = $subjectTotal >= 76;
    $passStatus = ($endSemPass && $overallPass) ? 'Pass' : 'Fail';
    $marksData[] = [
        'subject' => $row['subject'],
        'unit_test_1' => $row['unit_test_1'],
        'unit_test_2' => $row['unit_test_2'],
        'end_semester' => $row['end_semester'],
        'practical' => $row['practical'],
        'total' => $subjectTotal,
        'pass_status' => $passStatus
    ];
    $subjects[] = $row['subject'];
}
$stmt->close();

// Calculate percentage
$totalMax *= count($marksData); // Adjust total max based on number of subjects
$percentage = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0;

// Fetch assignments for this student's division and branch
$assignments = [];
$stmt = $conn->prepare("SELECT a.id, a.division, a.branch, a.year, a.file_name, a.uploaded_at, t.name AS teacher_name 
                        FROM assignments a 
                        JOIN teachers t ON a.teacher_id = t.id 
                        WHERE a.division = ? AND a.branch = ? 
                        ORDER BY a.uploaded_at DESC");
if (!$stmt) {
    die("Error preparing assignments query: " . $conn->error);
}
$stmt->bind_param('ss', $division, $branch);
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
    <title>Student Results & Assessment</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* General Content Styling */
        .main-content {
            background: linear-gradient(135deg, #f5f7fa, #e2e8f0);
            min-height: 100vh;
            padding: 20px;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        h2 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        h2::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 3px;
            background: linear-gradient(90deg, #3182ce, #63b3ed);
            bottom: -5px;
            left: 0;
        }
        h3 {
            color: #4a5568;
            font-size: 22px;
            margin: 30px 0 15px;
            font-weight: 600;
        }
        p {
            color: #718096;
            font-size: 16px;
            text-align: center;
            padding: 15px;
            background: #edf2f7;
            border-radius: 8px;
        }

        /* Marksheet Table Styling */
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        .marks-table th, .marks-table td {
            padding: 15px;
            text-align: center;
            font-size: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .marks-table th {
            background: linear-gradient(90deg, #3182ce, #63b3ed);
            color: #ffffff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
        }
        .marks-table tr:nth-child(even) {
            background: #f7fafc;
        }
        .marks-table tr:hover {
            background: #e3f2fd;
            transition: background 0.3s ease;
        }
        .marks-table .total-label {
            text-align: right;
            font-weight: 600;
            color: #2d3748;
        }
        .marks-table td:last-child {
            font-weight: 600;
            color: #2d3748;
        }
        .marks-table tr:last-child td {
            border-bottom: none;
            background: #edf2f7;
            font-weight: 600;
        }

        /* Download Form Button */
        .download-form {
            text-align: center;
            margin: 20px 0;
        }
        .download-form button {
            background: linear-gradient(90deg, #38a169, #68d391);
            color: #ffffff;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(56, 161, 105, 0.2);
            transition: all 0.3s ease;
        }
        .download-form button:hover {
            background: linear-gradient(90deg, #2f855a, #48bb78);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(56, 161, 105, 0.3);
        }

        /* Assignments Table Styling */
        .assignments-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        .assignments-table th, .assignments-table td {
            padding: 15px;
            text-align: center;
            font-size: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .assignments-table th {
            background: linear-gradient(90deg, #805ad5, #a78bfa);
            color: #ffffff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
        }
        .assignments-table tr:nth-child(even) {
            background: #f7fafc;
        }
        .assignments-table tr:hover {
            background: #f5f3ff;
            transition: background 0.3s ease;
        }
        .assignments-table tr:last-child td {
            border-bottom: none;
        }
        .download-link {
            display: inline-block;
            background: linear-gradient(90deg, #e53e3e, #f56565);
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(229, 62, 62, 0.2);
            transition: all 0.3s ease;
        }
        .download-link:hover {
            background: linear-gradient(90deg, #c53030, #e53e3e);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                
            </div>
            <ul class="sidebar-menu">
                <li><a href="studentdashboard.php">Dashboard</a></li>
                <li><a href="student_results.php" class="active">Results & Assessment</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <header class="dashboard-header">
                <h1>Student</h1>
                <div class="profile-section">
                    <span class="profile-name"><?php echo htmlspecialchars($name); ?></span>
                    <div class="profile-icon" onclick="toggleProfileDropdown()">👤</div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                        <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($rollNumber); ?></p>
                        <p><strong>Division:</strong> <?php echo htmlspecialchars($division); ?></p>
                        <p><strong>Branch:</strong> <?php echo htmlspecialchars($branch); ?></p>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($year); ?></p>
                    </div>
                </div>
            </header>
            <div class="content">
                <h2>Results & Assessment</h2>
                <h3>Marksheet</h3>
                <?php if (empty($marksData)) { ?>
                    <p>No results available yet. Marks have not been entered by your teachers.</p>
                <?php } else { ?>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Unit Test 1 (20)</th>
                                <th>Unit Test 2 (20)</th>
                                <th>End Semester (100)</th>
                                <th>Practical (50)</th>
                                <th>Total (190)</th>
                                <th>Pass/Fail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marksData as $marks) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($marks['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['unit_test_1']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['unit_test_2']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['end_semester']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['practical']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['total']); ?></td>
                                    <td><?php echo htmlspecialchars($marks['pass_status']); ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td colspan="5" class="total-label"><strong>Total:</strong></td>
                                <td><strong><?php echo $totalObtained; ?> / <?php echo $totalMax; ?></strong></td>
                                <td><strong>Percentage: <?php echo number_format($percentage, 2); ?>%</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <form action="download_result.php" method="POST" class="download-form">
                        <input type="hidden" name="subjects" value='<?php echo json_encode($subjects); ?>'>
                        <?php foreach ($marksData as $index => $marks) { ?>
                            <input type="hidden" name="marks[<?php echo $index; ?>][subject]" value="<?php echo htmlspecialchars($marks['subject']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][unit_test_1]" value="<?php echo htmlspecialchars($marks['unit_test_1']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][unit_test_2]" value="<?php echo htmlspecialchars($marks['unit_test_2']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][end_semester]" value="<?php echo htmlspecialchars($marks['end_semester']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][practical]" value="<?php echo htmlspecialchars($marks['practical']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][total]" value="<?php echo htmlspecialchars($marks['total']); ?>">
                            <input type="hidden" name="marks[<?php echo $index; ?>][pass_status]" value="<?php echo htmlspecialchars($marks['pass_status']); ?>">
                        <?php } ?>
                        <input type="hidden" name="total_obtained" value="<?php echo $totalObtained; ?>">
                        <input type="hidden" name="total_max" value="<?php echo $totalMax; ?>">
                        <input type="hidden" name="percentage" value="<?php echo number_format($percentage, 2); ?>">
                        <input type="hidden" name="student_name" value="<?php echo htmlspecialchars($name); ?>">
                        <input type="hidden" name="roll_number" value="<?php echo htmlspecialchars($rollNumber); ?>">
                        <input type="hidden" name="division" value="<?php echo htmlspecialchars($division); ?>">
                        <input type="hidden" name="branch" value="<?php echo htmlspecialchars($branch); ?>">
                        <button type="submit">Download Result as PDF</button>
                    </form>
                <?php } ?>

                <h3>Download Assignments</h3>
                <?php if (empty($assignments)) { ?>
                    <p>No assignments uploaded by your teachers yet.</p>
                <?php } else { ?>
                    <table class="assignments-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
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
                                    <td><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['division']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['branch']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['year']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['file_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['uploaded_at']); ?></td>
                                    <td>
                                        <a href="download_assignment.php?id=<?php echo htmlspecialchars($assignment['id']); ?>" target="_blank" class="download-link">Download</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
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
    </script>
</body>
</html>