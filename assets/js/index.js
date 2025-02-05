function openLoginModal() {
    document.getElementById('loginModal').style.display = 'block';
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
}

function validateField(field, pattern, errorMessage) {
    const value = field.value;
    const errorField = document.getElementById(field.id + '-error');
    if (!pattern.test(value)) {
        errorField.textContent = errorMessage;
        field.classList.add('invalid');
    } else {
        errorField.textContent = '';
        field.classList.remove('invalid');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const clinicNameField = document.getElementById('clinic_name');
    const firstNameField = document.getElementById('first_name');
    const middleNameField = document.getElementById('middle_name');
    const lastNameField = document.getElementById('last_name');
    const cityField = document.getElementById('city');
    const stateField = document.getElementById('state');
    const zipField = document.getElementById('zip');
    const licenseNumberField = document.getElementById('license_number');
    const phoneNumberField = document.getElementById('phone_number');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');

    const namePattern = /^[a-zA-Z\s]{2,}$/;
    const zipPattern = /^\d{4}$/;
    const licensePattern = /^[a-zA-Z0-9]{5,}$/;
    const phonePattern = /^\+63\d{10}$/;
    const passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    clinicNameField.addEventListener('input', () => validateField(clinicNameField, namePattern, 'Clinic name must be at least 2 characters long and contain only letters and spaces.'));
    firstNameField.addEventListener('input', () => validateField(firstNameField, namePattern, 'First name must be at least 2 characters long and contain only letters and spaces.'));
    middleNameField.addEventListener('input', () => validateField(middleNameField, namePattern, 'Middle name must be at least 2 characters long and contain only letters and spaces.'));
    lastNameField.addEventListener('input', () => validateField(lastNameField, namePattern, 'Last name must be at least 2 characters long and contain only letters and spaces.'));
    cityField.addEventListener('input', () => validateField(cityField, namePattern, 'City must be at least 2 characters long and contain only letters and spaces.'));
    stateField.addEventListener('input', () => validateField(stateField, namePattern, 'State must be at least 2 characters long and contain only letters and spaces.'));
    zipField.addEventListener('input', () => validateField(zipField, zipPattern, 'Zip code must be a 4-digit number.'));
    licenseNumberField.addEventListener('input', () => validateField(licenseNumberField, licensePattern, 'License number must be at least 5 characters long and contain only letters and numbers.'));
    phoneNumberField.addEventListener('input', () => validateField(phoneNumberField, phonePattern, 'Phone number must start with +63 and be followed by 10 digits.'));
    passwordField.addEventListener('input', () => validateField(passwordField, passwordPattern, ''));
    confirmPasswordField.addEventListener('input', () => {
        const errorField = document.getElementById('confirm_password-error');
        if (passwordField.value !== confirmPasswordField.value) {
            errorField.textContent = 'Passwords do not match.';
            confirmPasswordField.classList.add('invalid');
        } else {
            errorField.textContent = '';
            confirmPasswordField.classList.remove('invalid');
        }
    });
});

function validateForm() {
    const clinicNameField = document.getElementById('clinic_name');
    const firstNameField = document.getElementById('first_name');
    const lastNameField = document.getElementById('last_name');
    const cityField = document.getElementById('city');
    const stateField = document.getElementById('state');
    const zipField = document.getElementById('zip');
    const licenseNumberField = document.getElementById('license_number');
    const phoneNumberField = document.getElementById('phone_number');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');

    const namePattern = /^[a-zA-Z\s]{2,}$/;
    const zipPattern = /^\d{4}$/;
    const licensePattern = /^[a-zA-Z0-9]{5,}$/;
    const phonePattern = /^\+63\d{10}$/;
    const passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;
    
    let isValid = true;

    if (!namePattern.test(clinicNameField.value)) {
        isValid = false;
    }
    if (!namePattern.test(firstNameField.value)) {
        isValid = false;
    }
    if (!namePattern.test(lastNameField.value)) {
        isValid = false;
    }
    if (!namePattern.test(cityField.value)) {
        isValid = false;
    }
    if (!namePattern.test(stateField.value)) {
        isValid = false;
    }
    if (!zipPattern.test(zipField.value)) {
        isValid = false;
    }
    if (!licensePattern.test(licenseNumberField.value)) {
        isValid = false;
    }
    if (!phonePattern.test(phoneNumberField.value)) {
        isValid = false;
    }
    if (!passwordPattern.test(passwordField.value)) {
        isValid = false;
    }
    if (passwordField.value !== confirmPasswordField.value) {
        isValid = false;
    }

    return isValid;
}

function validateLoginForm() {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    let isValid = true;

    if (!emailPattern.test(emailField.value)) {
        isValid = false;
        document.getElementById('email-error').textContent = 'Invalid email address.';
        emailField.classList.add('invalid');
    } else {
        document.getElementById('email-error').textContent = '';
        emailField.classList.remove('invalid');
    }

    if (!passwordPattern.test(passwordField.value)) {
        isValid = false;
        document.getElementById('password-error').textContent = 'Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.';
        passwordField.classList.add('invalid');
    } else {
        document.getElementById('password-error').textContent = '';
        passwordField.classList.remove('invalid');
    }

    return isValid;
}