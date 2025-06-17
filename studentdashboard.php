<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.html');
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, roll_number, division, branch, year FROM students WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($name, $rollNumber, $division, $branch, $year);
$stmt->fetch();
$stmt->close();

// Fetch attendance data with teacher names
$attendanceData = [];
$stmt = $conn->prepare("SELECT a.subject, a.month, a.attendance, a.attendance_type, a.teacher_id, t.name AS teacher_name 
                        FROM attendance a 
                        JOIN teachers t ON a.teacher_id = t.id 
                        WHERE a.student_id = ? AND a.division = ? 
                        ORDER BY a.subject, a.teacher_id, a.attendance_type, a.month");
$stmt->bind_param('is', $userId, $division);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $key = $row['subject'] . '-' . $row['teacher_id'];
    $attendanceData[$key]['subject'] = $row['subject'];
    $attendanceData[$key]['teacher_name'] = $row['teacher_name'];
    $attendanceData[$key][$row['attendance_type']][$row['month']] = $row['attendance'];
}
$stmt->close();
$conn->close();

// Constants for totals
$totalLecturesPerSubject = 40;
$totalPracticalsPerSubject = 15;

// Calculate total attendance for all subjects
$totalAttended = 0;
$totalSubjects = count($attendanceData); // Count all subjects
$totalPossible = ($totalLecturesPerSubject + $totalPracticalsPerSubject) * $totalSubjects;

foreach ($attendanceData as $key => $data) {
    // Calculate lecture total
    $lectureTotal = 0;
    $lectureMonths = $data['Lecture'] ?? [];
    if (!empty($lectureMonths)) {
        foreach ($lectureMonths as $month => $att) {
            $lectureTotal += $att;
        }
    }
    $totalAttended += $lectureTotal;

    // Calculate practical total
    $practicalTotal = 0;
    $practicalMonths = $data['Practical'] ?? [];
    if (!empty($practicalMonths)) {
        foreach ($practicalMonths as $month => $att) {
            $practicalTotal += $att;
        }
    }
    $totalAttended += $practicalTotal;
}

$attendancePercentage = $totalPossible > 0 ? ($totalAttended / $totalPossible) * 100 : 0;
$circleOffset = 314 - ($attendancePercentage / 100) * 314; // 314 is the circumference of the circle (2 * π * 50)
$circleColor = $attendancePercentage >= 80 ? '#28a745' : '#dc3545'; // Green if ≥ 80%, red otherwise
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .attendance-overview {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .attendance-circle {
            position: relative;
            width: 120px;
            height: 120px;
            margin-right: 20px;
        }
        .attendance-circle svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }
        .attendance-circle .circle-bg {
            fill: none;
            stroke: #e0e0e0;
            stroke-width: 10;
        }
        .attendance-circle .circle-progress {
            fill: none;
            stroke-linecap: round;
            stroke-width: 10;
            stroke-dasharray: 314;
            stroke-dashoffset: 314;
            transition: stroke-dashoffset 1s ease, stroke 0.3s ease;
        }
        .attendance-circle .percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .attendance-details {
            text-align: left;
        }
        .attendance-details h3 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .attendance-details p {
            margin: 5px 0 0;
            font-size: 16px;
            color: #666;
        }
        .attendance-container {
            margin: 20px 0;
        }
        .subject-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            background-color: #f9f9f9;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .subject-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, #6a11cb, #2575fc);
            color: white;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            margin: -15px -15px 15px -15px;
        }
        .subject-header h4, .subject-header p {
            margin: 0;
            font-size: 18px;
        }
        .attendance-boxes {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .attendance-box {
            width: 48%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .attendance-box:hover {
            border-color: #2575fc;
            box-shadow: 0 8px 20px rgba(37, 117, 252, 0.2);
        }
        .attendance-box h5 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        .attendance-box p {
            margin: 0 0 10px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .attendance-table th, .attendance-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-size: 14px;
        }
        .attendance-table th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        .attendance-table td {
            background-color: #fff;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Student</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="studentdashboard.php">Dashboard</a></li>
                <li><a href="student_results.php">Results & Assessment</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <header class="dashboard-header">
                <div class="profile-section">
                    <span class="profile-name"><?php echo htmlspecialchars($name); ?></span>
                    <div class="profile-icon" onclick="toggleProfileDropdown()">👤</div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
                        <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($rollNumber); ?></p>
                        <p><strong>Division:</strong> <?php echo htmlspecialchars($division); ?></p>
                        <p><strong>Branch:</strong> <?php echo htmlspecialchars($branch); ?></p>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($year); ?></p>
                    </div>
                </div>
            </header>
            <div class="content">
                <h2>Dashboard</h2>
                <div class="attendance-overview">
                    <div class="attendance-circle">
                        <svg>
                            <circle class="circle-bg" cx="60" cy="60" r="50"></circle>
                            <circle class="circle-progress" cx="60" cy="60" r="50" style="stroke: <?php echo $circleColor; ?>; stroke-dashoffset: <?php echo $circleOffset; ?>;"></circle>
                        </svg>
                        <div class="percentage"><?php echo number_format($attendancePercentage, 0); ?>%</div>
                    </div>
                    <div class="attendance-details">
                        <h3>Total Attendance</h3>
                        <p><?php echo $totalAttended; ?> / <?php echo $totalPossible; ?> (All Subjects)</p>
                    </div>
                </div>

                <h3>Your Attendance</h3>
                <div class="attendance-container">
                    <?php if (!empty($attendanceData)) { ?>
                        <?php foreach ($attendanceData as $key => $data) { ?>
                            <div class="subject-box">
                                <div class="subject-header">
                                    <h4>Subject: <?php echo htmlspecialchars($data['subject']); ?></h4>
                                    <p>Teacher: <?php echo htmlspecialchars($data['teacher_name']); ?></p>
                                </div>
                                <div class="attendance-boxes">
                                    <div class="attendance-box">
                                        <h5>Lecture Attendance (Out of <?php echo $totalLecturesPerSubject; ?>)</h5>
                                        <?php 
                                        $lectureTotal = 0;
                                        $lectureMonths = $data['Lecture'] ?? [];
                                        if (!empty($lectureMonths)) {
                                            foreach ($lectureMonths as $month => $att) {
                                                $lectureTotal += $att;
                                            }
                                            $lecturePercentage = ($lectureTotal / $totalLecturesPerSubject) * 100;
                                        ?>
                                            <p><strong>Total:</strong> <?php echo $lectureTotal; ?> / <?php echo $totalLecturesPerSubject; ?> (<?php echo number_format($lecturePercentage, 2); ?>%)</p>
                                            <table class="attendance-table">
                                                <thead>
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Attendance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($lectureMonths as $month => $att) { ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($month); ?></td>
                                                            <td><?php echo htmlspecialchars($att); ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        <?php } else { ?>
                                            <p>No lecture attendance recorded.</p>
                                        <?php } ?>
                                    </div>
                                    <div class="attendance-box">
                                        <h5>Practical Attendance (Out of <?php echo $totalPracticalsPerSubject; ?>)</h5>
                                        <?php 
                                        $practicalTotal = 0;
                                        $practicalMonths = $data['Practical'] ?? [];
                                        if (!empty($practicalMonths)) {
                                            foreach ($practicalMonths as $month => $att) {
                                                $practicalTotal += $att;
                                            }
                                            $practicalPercentage = ($practicalTotal / $totalPracticalsPerSubject) * 100;
                                        ?>
                                            <p><strong>Total:</strong> <?php echo $practicalTotal; ?> / <?php echo $totalPracticalsPerSubject; ?> (<?php echo number_format($practicalPercentage, 2); ?>%)</p>
                                            <table class="attendance-table">
                                                <thead>
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Attendance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($practicalMonths as $month => $att) { ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($month); ?></td>
                                                            <td><?php echo htmlspecialchars($att); ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        <?php } else { ?>
                                            <p>No practical attendance recorded.</p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <p>No attendance data available.</p>
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
    </script>
</body>
</html>