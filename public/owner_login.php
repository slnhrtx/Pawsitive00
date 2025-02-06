<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$email = '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Pawsitive, the pet management system.">
    <meta name="keywords" content="Pawsitive, Pet Management, Login">
    <meta name="author" content="Pawsitive">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/owner_login.css">
    <script src="../assets/js/index.js" defer></script>
</head>

<body>
    <div class="login-container">
        <!-- Right Panel First -->
        <div class="right-panel">
            <h3 class="form-title">Login your account</h3>
            <?php if (!empty($errors)): ?>
                    <div class="error-notification">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <form action="../../src/owner_login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group">
                    <label for="Email">Email:<span class="required-asterisk">*</span></label>
                    <input type="email" id="Email" name="Email" placeholder="Enter your email address" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:<span class="required-asterisk">*</span></label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye eye-icon" onclick="togglePassword('Password', this)"></i>
                    </div>
                    <a href="../password/forgot_password.php" class="forgot-password-link">Forgot password?</a>
                </div>
                <button type="submit" class="login-btn">Log in</button>
                <div class="form-group terms">
                    <label for="terms">
                        &copy; 2025 Pawsitive. All Rights Reserve.
                        <span><a href="privacy.php" target="_blank">Policy</a> and <a href="terms.php"
                            target="_blank">Terms</a>.</span> </label>
                    </div>
            </form>
        </div>
    
        <!-- Left Panel Second -->
        <div class="left-panel">
            <h2 class="welcome-message">Hello! Welcome to</h2>
            <div class="branding">
                <img src="../assets/images/logo/LOGO 1 WHITE.png" alt="Pawsitive Logo" class="logo">
                <p>Your all-in-one system management<br>pet records, appointments, and more!</p>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js?v=1.0.0" async></script>
    <script src="../assets/js/password.js" async></script>
</body>
</html>