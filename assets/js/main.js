document.addEventListener("DOMContentLoaded", function () {
    const emailInput = document.getElementById("Email");
    const passwordInput = document.getElementById("password");
    const loginButton = document.querySelector(".login-btn");

    loginButton.disabled = true;
    loginButton.style.opacity = "0.5";
    loginButton.style.cursor = "not-allowed";

    function checkInputs() {
        if (emailInput.value.trim() !== "" && passwordInput.value.trim() !== "") {
            loginButton.disabled = false;
            loginButton.style.opacity = "1";
            loginButton.style.cursor = "pointer";
        } else {
            loginButton.disabled = true;
            loginButton.style.opacity = "0.5";
            loginButton.style.cursor = "not-allowed";
        }
    }

    emailInput.addEventListener("input", checkInputs);
    passwordInput.addEventListener("input", checkInputs);
});