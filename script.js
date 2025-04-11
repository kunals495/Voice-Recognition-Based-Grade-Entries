
let currentRole = 'student';

function toggleRole(role) {
  currentRole = role;
  const buttons = document.querySelectorAll('.role-toggle button');
  buttons.forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');
  
  const studentFields = document.querySelectorAll('.student-field');
  const teacherFields = document.querySelectorAll('.teacher-field');
  
  if (role === 'student') {
    studentFields.forEach(f => f.classList.remove('hidden'));
    teacherFields.forEach(f => f.classList.add('hidden'));
  } else {
    studentFields.forEach(f => f.classList.add('hidden'));
    teacherFields.forEach(f => f.classList.remove('hidden'));
  }
}

function showSignup() {
  document.querySelector('#loginForm').parentElement.classList.add('hidden');
  document.querySelector('#signupBox').classList.remove('hidden');
}

function showLogin() {
  document.querySelector('#loginForm').parentElement.classList.remove('hidden');
  document.querySelector('#signupBox').classList.add('hidden');
}

document.querySelector('#loginForm').addEventListener('submit', (e) => {
  e.preventDefault();
  // Here you would handle login authentication
  const email = e.target.elements[0].value;
  const password = e.target.elements[1].value;
  console.log('Login:', { email, password, role: currentRole });
});

document.querySelector('#signupForm').addEventListener('submit', (e) => {
  e.preventDefault();
  // Here you would handle signup
  const formData = {
    name: e.target.elements[0].value,
    rollNumber: currentRole === 'student' ? e.target.elements[1].value : null,
    prnNumber: currentRole === 'student' ? e.target.elements[2].value : null,
    subject: currentRole === 'teacher' ? e.target.elements[1].value : null,
    email: e.target.elements[3].value,
    password: e.target.elements[4].value,
    role: currentRole
  };
  console.log('Signup:', formData);
});
