
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

document.querySelector('#loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = e.target.elements[0].value;
  const password = e.target.elements[1].value;
  
  try {
    // Add loading state
    const submitBtn = e.target.querySelector('button');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    // Here you would make an API call to verify credentials
    // For demo, we'll simulate a check
    if (!email || !password) {
      throw new Error('Please fill in all fields');
    }

    // Redirect to dashboard on success
    if (currentRole === 'student') {
      window.location.href = '/student-dashboard';
    } else {
      window.location.href = '/teacher-dashboard';
    }
  } catch (error) {
    alert(error.message || 'Login failed. Please try again.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Login';
  }
});

document.querySelector('#signupForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  try {
    const formData = {
      name: e.target.elements[0].value,
      rollNumber: currentRole === 'student' ? e.target.elements[1].value : null,
      prnNumber: currentRole === 'student' ? e.target.elements[2].value : null,
      subject: currentRole === 'teacher' ? e.target.elements[1].value : null,
      email: e.target.elements[3].value,
      password: e.target.elements[4].value,
      role: currentRole
    };

    // Add loading state
    const submitBtn = e.target.querySelector('button');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating Account...';

    // Validate fields
    if (!formData.name || !formData.email || !formData.password) {
      throw new Error('Please fill in all required fields');
    }

    if (currentRole === 'student' && (!formData.rollNumber || !formData.prnNumber)) {
      throw new Error('Roll number and PRN number are required for students');
    }

    if (currentRole === 'teacher' && !formData.subject) {
      throw new Error('Subject is required for teachers');
    }

    // Here you would make an API call to create the account
    // For demo, we'll simulate success
    showLogin();
    alert('Account created successfully! Please login.');

  } catch (error) {
    alert(error.message || 'Signup failed. Please try again.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Sign Up';
  }
});
