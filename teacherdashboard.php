<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.html');
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, subject FROM teachers WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($name, $email, $teacherSubject);
$stmt->fetch();
$stmt->close();

if (empty($teacherSubject)) {
    die("Error: Teacher's subject is not set in the database.");
}

// Arrays for dropdowns
$divisions = ['A', 'B'];
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Get form data
$selectedDivision = $_POST['division'] ?? '';
$monthStart = $_POST['month_start'] ?? 'January';
$monthEnd = $_POST['month_end'] ?? 'December';
$selectedStudentId = $_POST['student_id'] ?? '';

// Get the range of months
$monthStartIndex = array_search($monthStart, $months);
$monthEndIndex = array_search($monthEnd, $months);
$selectedMonths = array_slice($months, $monthStartIndex, $monthEndIndex - $monthStartIndex + 1);

// Fetch students for the selected division
$students = [];
if ($selectedDivision) {
    $stmt = $conn->prepare("SELECT id, name, roll_number FROM students WHERE division = ?");
    $stmt->bind_param('s', $selectedDivision);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// Fetch existing attendance for the selected student and subject
$attendanceData = [];
if ($selectedStudentId && $selectedDivision) {
    $stmt = $conn->prepare("SELECT month, attendance, attendance_type 
                            FROM attendance 
                            WHERE student_id = ? 
                            AND division = ? 
                            AND month IN (" . implode(',', array_fill(0, count($selectedMonths), '?')) . ") 
                            AND teacher_id = ? 
                            AND subject = ?");
    $params = array_merge([$selectedStudentId, $selectedDivision], $selectedMonths, [$userId, $teacherSubject]);
    $types = 'ss' . str_repeat('s', count($selectedMonths)) . 'is';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendanceData[$row['attendance_type']][$row['month']] = $row['attendance'];
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .student-list {
            margin: 20px 0;
        }
        .student-list ul {
            list-style: none;
            padding: 0;
        }
        .student-list li {
            padding: 10px;
            background: #f9f9f9;
            margin-bottom: 5px;
            cursor: pointer;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .student-list li:hover {
            background: #e0e0e0;
        }
        .attendance-form {
            display: none;
            margin: 20px 0;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .attendance-table th, .attendance-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            font-size: 15px;
        }
        .attendance-table th {
            background: linear-gradient(90deg, #3182ce, #63b3ed);
            color: #ffffff;
            font-weight: 600;
        }
        .attendance-table tr:nth-child(even) {
            background: #f7fafc;
        }
        .attendance-table tr:hover {
            background: #e3f2fd;
        }
        .attendance-table input[type="number"] {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: border 0.3s ease;
        }
        .attendance-table input[type="number"].active-field {
            border: 2px solid #38a169;
            background: #f0fff4;
        }
        .month-range {
            margin: 15px 0;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .month-range label {
            font-weight: 500;
            color: #4a5568;
        }
        .month-range select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            cursor: pointer;
        }
        form button {
            background: linear-gradient(90deg, #38a169, #68d391);
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(56, 161, 105, 0.2);
            transition: all 0.3s ease;
        }
        form button:hover {
            background: linear-gradient(90deg, #2f855a, #48bb78);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);
        }
        .voice-controls {
            margin: 15px 0;
            display: flex;
            gap: 10px;
        }
        .voice-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        #startVoice {
            background: linear-gradient(90deg, #3182ce, #63b3ed);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(49, 130, 206, 0.2);
        }
        #startVoice:hover {
            background: linear-gradient(90deg, #2b6cb0, #4299e1);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);
        }
        #stopVoice {
            background: linear-gradient(90deg, #e53e3e, #f56565);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(229, 62, 62, 0.2);
        }
        #stopVoice:hover {
            background: linear-gradient(90deg, #c53030, #e53e3e);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
        }
        #stopVoice:disabled {
            background: #e2e8f0;
            color: #a0aec0;
            cursor: not-allowed;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Faculty</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="results.php">Results & Assessment</a></li>
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
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($teacherSubject); ?></p>
                    </div>
                </div>
            </header>
            <div class="content">
                <h3>Manage Attendance for <?php echo htmlspecialchars($teacherSubject); ?></h3>
                <form method="POST" action="teacherdashboard.php">
                    <label for="division">Division:</label>
                    <select name="division" id="division" required>
                        <option value="">Select Division</option>
                        <?php foreach ($divisions as $div) { ?>
                            <option value="<?php echo $div; ?>" <?php echo $selectedDivision == $div ? 'selected' : ''; ?>>
                                <?php echo $div; ?>
                            </option>
                        <?php } ?>
                    </select>

                    <div class="month-range">
                        <label for="month_start">Month Range Start:</label>
                        <select name="month_start" id="month_start">
                            <?php foreach ($months as $month) { ?>
                                <option value="<?php echo $month; ?>" <?php echo $monthStart == $month ? 'selected' : ''; ?>>
                                    <?php echo $month; ?>
                                </option>
                            <?php } ?>
                        </select>

                        <label for="month_end">Month Range End:</label>
                        <select name="month_end" id="month_end">
                            <?php foreach ($months as $month) { ?>
                                <option value="<?php echo $month; ?>" <?php echo $monthEnd == $month ? 'selected' : ''; ?>>
                                    <?php echo $month; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <button type="submit">Show Students</button>
                </form>

                <?php if (!empty($students)) { ?>
                    <h3>Students in Division <?php echo htmlspecialchars($selectedDivision); ?></h3>
                    <div class="student-list">
                        <ul>
                            <?php foreach ($students as $student) { ?>
                                <li onclick="showAttendanceForm(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>', '<?php echo htmlspecialchars($student['roll_number']); ?>')">
                                    <?php echo htmlspecialchars($student['name']); ?> (Roll No: <?php echo htmlspecialchars($student['roll_number']); ?>)
                                </li>
                            <?php } ?>
                        </ul>
                    </div>

                    <div class="attendance-form" id="attendanceForm">
                        <h4>Attendance for <span id="studentName"></span> (Roll No: <span id="studentRollNo"></span>) - Subject: <?php echo htmlspecialchars($teacherSubject); ?></h4>
                        <form method="POST" action="save_attendance.php">
                            <input type="hidden" name="division" value="<?php echo htmlspecialchars($selectedDivision); ?>">
                            <input type="hidden" name="teacher_id" value="<?php echo $userId; ?>">
                            <input type="hidden" name="student_id" id="selectedStudentId">
                            <input type="hidden" name="month_start" value="<?php echo htmlspecialchars($monthStart); ?>">
                            <input type="hidden" name="month_end" value="<?php echo htmlspecialchars($monthEnd); ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($teacherSubject); ?>">

                            <div class="voice-controls">
                                <button type="button" id="startVoice" class="voice-btn">Start Voice Input</button>
                                <button type="button" id="stopVoice" class="voice-btn" disabled>Stop Voice Input</button>
                            </div>

                            <h5>Lecture Attendance (Max 40)</h5>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $lectureTotal = 0;
                                    foreach ($selectedMonths as $month) { 
                                        $attendance = $attendanceData['Lecture'][$month] ?? '';
                                        $lectureTotal += (int)$attendance;
                                    ?>
                                        <tr>
                                            <td><?php echo $month; ?></td>
                                            <td>
                                                <input type="number" 
                                                       name="attendance[Lecture][<?php echo $month; ?>]" 
                                                       value="<?php echo $attendance; ?>" 
                                                       min="0" 
                                                       max="40" 
                                                       onchange="calculateTotal('Lecture')"
                                                       class="attendance-input lecture-input">
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td>
                                            <span class="total" id="total-Lecture"><?php echo $lectureTotal; ?></span>
                                            <input type="hidden" name="total[Lecture]" value="<?php echo $lectureTotal; ?>" class="total-input lecture-total">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <h5>Practical Attendance (Max 15)</h5>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $practicalTotal = 0;
                                    foreach ($selectedMonths as $month) { 
                                        $attendance = $attendanceData['Practical'][$month] ?? '';
                                        $practicalTotal += (int)$attendance;
                                    ?>
                                        <tr>
                                            <td><?php echo $month; ?></td>
                                            <td>
                                                <input type="number" 
                                                       name="attendance[Practical][<?php echo $month; ?>]" 
                                                       value="<?php echo $attendance; ?>" 
                                                       min="0" 
                                                       max="15" 
                                                       onchange="calculateTotal('Practical')"
                                                       class="attendance-input practical-input">
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td>
                                            <span class="total" id="total-Practical"><?php echo $practicalTotal; ?></span>
                                            <input type="hidden" name="total[Practical]" value="<?php echo $practicalTotal; ?>" class="total-input practical-total">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <button type="submit">Save Attendance</button>
                        </form>
                    </div>
                <?php } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { ?>
                    <p>No students found for the selected division.</p>
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

        function showAttendanceForm(studentId, studentName, rollNo) {
            document.getElementById('attendanceForm').style.display = 'block';
            document.getElementById('studentName').textContent = studentName;
            document.getElementById('studentRollNo').textContent = rollNo;
            document.getElementById('selectedStudentId').value = studentId;

            // Reset voice input state
            currentFieldIndex = 0;
            fields.forEach(field => field.classList.remove('active-field'));
            if (fields.length > 0) {
                fields[0].classList.add('active-field');
            }

            // Scroll to the form
            document.getElementById('attendanceForm').scrollIntoView({ behavior: 'smooth' });
        }

        function calculateTotal(type) {
            const inputs = document.querySelectorAll(`.${type.toLowerCase()}-input`);
            let total = 0;
            inputs.forEach(inp => {
                total += parseInt(inp.value) || 0;
            });
            const max = type === 'Lecture' ? 40 : 15;
            if (total > max) {
                alert(`Total ${type} attendance cannot exceed ${max}.`);
                total = max;
                inputs.forEach(inp => {
                    inp.value = parseInt(inp.value) || 0;
                });
            }
            document.getElementById(`total-${type}`).textContent = total;
            document.querySelector(`.${type.toLowerCase()}-total`).value = total;
        }

        // Voice Input Logic
        const startVoiceBtn = document.getElementById('startVoice');
        const stopVoiceBtn = document.getElementById('stopVoice');
        let recognition;
        let currentFieldIndex = 0;
        let fields = [];

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = false;
            recognition.lang = 'en-US';

            recognition.onstart = function() {
                startVoiceBtn.disabled = true;
                stopVoiceBtn.disabled = false;
                // Collect all input fields (lecture first, then practical)
                fields = [
                    ...document.querySelectorAll('.lecture-input'),
                    ...document.querySelectorAll('.practical-input')
                ];
                currentFieldIndex = 0;
                fields.forEach(field => field.classList.remove('active-field'));
                if (fields.length > 0) {
                    fields[0].classList.add('active-field');
                }
            };

            recognition.onresult = function(event) {
                const transcript = event.results[event.results.length - 1][0].transcript.trim();
                const number = parseInt(transcript);

                if (!isNaN(number) && fields[currentFieldIndex]) {
                    const field = fields[currentFieldIndex];
                    const type = field.classList.contains('lecture-input') ? 'Lecture' : 'Practical';
                    const max = type === 'Lecture' ? 40 : 15;

                    if (number >= 0 && number <= max) {
                        field.value = number;
                        calculateTotal(type);

                        // Move to the next field
                        currentFieldIndex++;
                        fields.forEach(f => f.classList.remove('active-field'));
                        if (fields[currentFieldIndex]) {
                            fields[currentFieldIndex].classList.add('active-field');
                        } else {
                            recognition.stop();
                            alert('All fields have been filled.');
                            startVoiceBtn.disabled = false;
                            stopVoiceBtn.disabled = true;
                        }
                    } else {
                        alert(`Please say a number between 0 and ${max} for ${type} attendance.`);
                    }
                }
            };

            recognition.onend = function() {
                startVoiceBtn.disabled = false;
                stopVoiceBtn.disabled = true;
                fields.forEach(field => field.classList.remove('active-field'));
            };

            recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                alert('Error occurred in speech recognition: ' + event.error);
                recognition.stop();
            };

            startVoiceBtn.addEventListener('click', () => {
                if (fields.length === 0) {
                    // Collect fields if form is visible
                    fields = [
                        ...document.querySelectorAll('.lecture-input'),
                        ...document.querySelectorAll('.practical-input')
                    ];
                }
                if (fields.length === 0) {
                    alert('Please select a student to show the attendance form first.');
                    return;
                }
                recognition.start();
            });

            stopVoiceBtn.addEventListener('click', () => {
                recognition.stop();
            });
        } else {
            startVoiceBtn.disabled = true;
            stopVoiceBtn.disabled = true;
            startVoiceBtn.textContent = 'Voice Input Not Supported';
            startVoiceBtn.style.background = '#e2e8f0';
            startVoiceBtn.style.color = '#a0aec0';
        }
    </script>
</body>
</html>