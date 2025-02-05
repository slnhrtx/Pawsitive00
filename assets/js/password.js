document.addEventListener("DOMContentLoaded", () => {
  const passwordInput = document.getElementById("password");
  const confirmPasswordGroup = document
    .getElementById("confirm_password")
    .closest(".form-group");
  const requirementsList = document.getElementById("password-requirements");

  const reqLength = document.getElementById("req-length");
  const reqUppercase = document.getElementById("req-uppercase");
  const reqLowercase = document.getElementById("req-lowercase");
  const reqDigit = document.getElementById("req-digit");
  const reqSpecial = document.getElementById("req-special");

  const lengthPattern = /.{8,}/;
  const uppercasePattern = /[A-Z]/;
  const lowercasePattern = /[a-z]/;
  const digitPattern = /\d/;
  const specialPattern = /[\W_]/;

  function updateRequirement(element, isValid) {
    const icon = element.querySelector("i");
    if (isValid) {
      icon.classList.remove("fa-times");
      icon.classList.add("fa-check");
      element.classList.add("valid");
      element.classList.remove("invalid");
    } else {
      icon.classList.remove("fa-check");
      icon.classList.add("fa-times");
      element.classList.add("invalid");
      element.classList.remove("valid");
    }
  }

  passwordInput.addEventListener("focus", () => {
    requirementsList.classList.add("visible"); // Add visible class
    confirmPasswordGroup.style.marginTop = "20px"; // Move Confirm Password field down
  });

  // Hide the password requirements list when the password field is blurred and empty
  passwordInput.addEventListener("blur", () => {
    if (!passwordInput.value) {
      requirementsList.classList.remove("visible"); // Remove visible class
      confirmPasswordGroup.style.marginTop = "0"; // Reset Confirm Password position
    }
  });

  passwordInput.addEventListener("input", () => {
    const password = passwordInput.value;

    updateRequirement(reqLength, lengthPattern.test(password));
    updateRequirement(reqUppercase, uppercasePattern.test(password));
    updateRequirement(reqLowercase, lowercasePattern.test(password));
    updateRequirement(reqDigit, digitPattern.test(password));
    updateRequirement(reqSpecial, specialPattern.test(password));
  });
});

function togglePassword(fieldId, eyeIcon) {
  const passwordField = document.getElementById(fieldId);
  const type =
    passwordField.getAttribute("type") === "password" ? "text" : "password";
  passwordField.setAttribute("type", type);

  if (type === "password") {
    eyeIcon.classList.remove("fa-eye-slash");
    eyeIcon.classList.add("fa-eye");
  } else {
    eyeIcon.classList.remove("fa-eye");
    eyeIcon.classList.add("fa-eye-slash");
  }
}

function validatePassword() {
  var password = document.getElementById("password").value;
  var confirmPassword = document.getElementById("confirm_password").value;
  var errorMessage = "";

  // Password validation checks
  if (password.length < 8) {
    errorMessage += "Password must be at least 8 characters long.\n";
  }
  if (!/[A-Z]/.test(password)) {
    errorMessage += "Password must include at least one uppercase letter.\n";
  }
  if (!/[a-z]/.test(password)) {
    errorMessage += "Password must include at least one lowercase letter.\n";
  }
  if (!/\d/.test(password)) {
    errorMessage += "Password must include at least one digit.\n";
  }
  if (!/[\W_]/.test(password)) {
    errorMessage += "Password must include at least one special character.\n";
  }

  // Check if passwords match
  if (password !== confirmPassword) {
    errorMessage += "Passwords do not match.\n";
  }

  // Show error message if validation fails
  if (errorMessage !== "") {
    alert(errorMessage); // You can replace this with a more user-friendly message on the page
    return false; // Prevent form submission
  }
  return true; // Allow form submission
}
