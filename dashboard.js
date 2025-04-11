
// Simulated student data - replace with actual data from backend
const studentData = {
  name: "John Doe",
  rollNumber: "CS2023001",
  prnNumber: "PRN2023001",
  email: "john.doe@example.com"
};

// Load student profile data
document.getElementById('studentName').textContent = studentData.name;
document.getElementById('rollNumber').textContent = `Roll Number: ${studentData.rollNumber}`;
document.getElementById('prnNumber').textContent = `PRN Number: ${studentData.prnNumber}`;
document.getElementById('email').textContent = `Email: ${studentData.email}`;

// Simulated marks data - replace with actual data from backend
const marksData = {
  sem1: [
    { subject: "Mathematics I", marks: 85 },
    { subject: "Physics", marks: 78 },
    { subject: "Chemistry", marks: 82 },
    { subject: "Basic Electronics", marks: 75 }
  ],
  sem2: [
    { subject: "Mathematics II", marks: 88 },
    { subject: "Programming", marks: 92 },
    { subject: "Digital Electronics", marks: 85 }
  ],
  sem3: [
    { subject: "Data Structures", marks: 90 },
    { subject: "Database Systems", marks: 87 },
    { subject: "Computer Networks", marks: 82 }
  ]
};

// Load marks data
function displayMarks(semId, marks) {
  const marksList = document.querySelector(`#${semId} .marks-list`);
  marks.forEach(subject => {
    marksList.innerHTML += `
      <div class="subject-marks">
        <span>${subject.subject}</span>: 
        <span>${subject.marks}</span>
      </div>
    `;
  });
}

// Display marks for all semesters
Object.entries(marksData).forEach(([semId, marks]) => {
  displayMarks(semId, marks);
});
