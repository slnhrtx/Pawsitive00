<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$successMessage = $_SESSION['successMessage'] ?? '';
$errors = $_SESSION['errors'] ?? [];

unset($_SESSION['successMessage'], $_SESSION['errors']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/forgot_password.css">
</head>

<body>
    <div class="login-container">
        <div class="left-panel">
            <h2 class="welcome-message">Hello! Welcome to</h2>
            <div class="branding">
                <img src="../assets/images/logo/LOGO 1 WHITE.png" alt="Pawsitive Logo" class="logo">
                <p>Your all-in-one system management<br>pet records, appointments, and more!</p>
            </div>
        </div>
        <div class="right-panel">
            <h3 class="form-title">Forgot Password</h3>
            <?php if (!empty($successMessage)): ?>
                <div class="success-notification">
                    <ul>
                        <li><i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($successMessage) ?>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error-notification">
                    <ul>
                        <li><i class="fas fa-exclamation-circle"></i>
                            <?php foreach ($errors as $error): ?>
                                <?= htmlspecialchars($error) ?><br>
                            <?php endforeach; ?>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            <p>Enter your email address to receive a password reset link.</p>
            <br>
            <form action="../src/forgot_password.php" method="POST" novalidate>
                <div class="form-group">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="email">Email Address:<span class="required-asterisk">*</span></label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                    <a href="staff_login.php" class="forgot-password-link">Back to Login</a>
                    <button type="submit" class="login-btn">Send Reset Password Link</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>