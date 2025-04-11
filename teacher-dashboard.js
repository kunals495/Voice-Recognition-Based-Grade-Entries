
// Simulated teacher data
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

// Handle marks submission
document.getElementById('marksForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const semester = document.getElementById('semesterSelect').value;
  const studentRoll = document.getElementById('studentRoll').value;
  const marks = document.getElementById('marks').value;
  
  if (!semester || !studentRoll || !marks) {
    alert('Please fill all fields');
    return;
  }
  
  // Here you would typically send this data to a backend
  alert(`Marks submitted successfully!\nStudent: ${studentRoll}\nSemester: ${semester}\nMarks: ${marks}`);
  e.target.reset();
});
