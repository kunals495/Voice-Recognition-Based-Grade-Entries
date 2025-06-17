document.addEventListener('DOMContentLoaded', () => {
    const activeRoleBtn = document.querySelector('.auth-box .role-toggle button.active');
    const role = activeRoleBtn ? activeRoleBtn.textContent.toLowerCase() : 'student';
    toggleRole(role);
});

function toggleRole(role) {
    role = role.toLowerCase();

    const allRoleToggles = document.querySelectorAll('.auth-box .role-toggle');

    allRoleToggles.forEach(toggleGroup => {
        const buttons = toggleGroup.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.classList.toggle('active', btn.textContent.toLowerCase() === role);
        });
    });

    const studentFields = document.querySelectorAll('.student-field');
    const teacherFields = document.querySelectorAll('.teacher-field');

    studentFields.forEach(el => el.classList.toggle('hidden', role !== 'student'));
    teacherFields.forEach(el => el.classList.toggle('hidden', role !== 'teacher'));

    teacherFields.forEach(el => {
        if (role === 'teacher' && el.getAttribute('data-required') === 'true') {
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
        }
    });

    studentFields.forEach(el => {
        if (role === 'student') {
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
        }
    });

    document.querySelectorAll('form').forEach(form => form.setAttribute('data-role', role));
}

function showSignup() {
    document.getElementById('signupBox').classList.remove('hidden');
    document.getElementById('loginBox').classList.add('hidden');

    const currentRoleBtn = document.querySelector('#signupBox .role-toggle button.active');
    const role = currentRoleBtn ? currentRoleBtn.textContent.toLowerCase() : 'student';
    toggleRole(role);
}

function showLogin() {
    document.getElementById('signupBox').classList.add('hidden');
    document.getElementById('loginBox').classList.remove('hidden');

    const currentRoleBtn = document.querySelector('#loginBox .role-toggle button.active');
    const role = currentRoleBtn ? currentRoleBtn.textContent.toLowerCase() : 'student';
    toggleRole(role);
}

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorDiv = document.getElementById('login-error');
    errorDiv.style.display = 'none';

    const role = this.getAttribute('data-role');
    const formData = new FormData(this);
    formData.append('role', role);

    const email = formData.get('email');
    const password = formData.get('password');
    if (!email || !password) {
        errorDiv.textContent = 'Please fill in all required fields.';
        errorDiv.style.display = 'block';
        return;
    }

    try {
        const res = await fetch('login.php', {
            method: 'POST',
            body: formData
        });

        let data;
        try {
            data = await res.json();
        } catch {
            const text = await res.text();
            console.error('Non-JSON response:', text);
            errorDiv.textContent = 'Server error: Invalid response format';
            errorDiv.style.display = 'block';
            return;
        }

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            errorDiv.textContent = data.message || 'Invalid credentials';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Error: ' + error.message;
        errorDiv.style.display = 'block';
    }
});

document.getElementById('signupForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorDiv = document.getElementById('signup-error');
    errorDiv.style.display = 'none';

    const role = this.getAttribute('data-role');
    const formData = new FormData(this);
    formData.append('role', role);

    const name = formData.get('name');
    const email = formData.get('email');
    const password = formData.get('password');

    if (!name || !email || !password) {
        errorDiv.textContent = 'Please fill in all required fields.';
        errorDiv.style.display = 'block';
        return;
    }

    if (role === 'student') {
        const roll = formData.get('roll_number');
        const prn = formData.get('prn_number');
        const division = formData.get('division');
        const year = formData.get('year');
        const branch = formData.get('branch');

        if (!roll || !prn || !division || !year || !branch) {
            errorDiv.textContent = 'Please provide roll number, PRN number, division, year, and branch.';
            errorDiv.style.display = 'block';
            return;
        }
    } else if (role === 'teacher') {
        const subject = formData.get('subject');
        if (!subject) {
            errorDiv.textContent = 'Please provide a subject.';
            errorDiv.style.display = 'block';
            return;
        }
    }

    try {
        const res = await fetch('signup.php', {
            method: 'POST',
            body: formData
        });

        let data;
        try {
            data = await res.json();
        } catch {
            const text = await res.text();
            console.error('Non-JSON response:', text);
            errorDiv.textContent = 'Server error: Invalid response format';
            errorDiv.style.display = 'block';
            return;
        }

        if (data.success) {
            showLogin();
        } else {
            errorDiv.textContent = data.message || 'Signup failed';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Error: ' + error.message;
        errorDiv.style.display = 'block';
    }
});
