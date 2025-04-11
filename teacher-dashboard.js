
// Teacher profile data
const teacherData = {
  name: "Prof. Jane Smith",
  department: "Computer Engineering",
  subject: "Data Structures",
  email: "jane.smith@example.com"
};

// Load teacher profile data
document.getElementById('teacherName').textContent = teacherData.name;
document.getElementById('subject').textContent = `Subject: ${teacherData.subject}`;
document.getElementById('email').textContent = `Email: ${teacherData.email}`;

// Handle adding new student row
document.getElementById('addRow').addEventListener('click', () => {
  const tbody = document.getElementById('marksTableBody');
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td><input type="text" class="roll-no" placeholder="Roll No" required></td>
    <td><input type="text" class="student-name" placeholder="Student Name" required></td>
    <td><input type="number" class="marks-obtained" placeholder="Marks" required min="0"></td>
    <td><input type="number" class="marks-total" placeholder="Total" required min="0"></td>
  `;
  tbody.appendChild(newRow);
});

// Handle form submission
document.getElementById('marksForm').addEventListener('submit', (e) => {
  e.preventDefault();
  
  const semester = document.getElementById('semesterSelect').value;
  const subject = document.getElementById('subjectSelect').value;
  const examType = document.getElementById('examType').value;
  
  if (!semester || !subject || !examType) {
    alert('Please select semester, subject and exam type');
    return;
  }

  const marksData = [];
  const rows = document.getElementById('marksTableBody').getElementsByTagName('tr');
  
  for (let row of rows) {
    const inputs = row.getElementsByTagName('input');
    const entry = {
      rollNo: inputs[0].value,
      name: inputs[1].value,
      marksObtained: inputs[2].value,
      totalMarks: inputs[3].value
    };
    
    if (!entry.rollNo || !entry.name || !entry.marksObtained || !entry.totalMarks) {
      alert('Please fill all fields for each student');
      return;
    }
    
    marksData.push(entry);
  }

  // Here you would typically send this data to a backend
  alert(`Marks submitted successfully!\nSemester: ${semester}\nSubject: ${subject}\nExam: ${examType}\nTotal Students: ${marksData.length}`);
  
  // Clear the form
  document.getElementById('marksTableBody').innerHTML = '';
  document.getElementById('semesterSelect').value = '';
  document.getElementById('subjectSelect').value = '';
  document.getElementById('examType').value = '';
});
