<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/helpers/auth_helpers.php';
require __DIR__ . '/helpers/log_helpers.php';

$errors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['Email']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    if (!validateCSRFToken($_POST['csrf_token'])) {
        error_log("CSRF validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
        logActivity($pdo, 0, 'Unknown', 'Guest', 'staff_login.php', 'CSRF validation failed');
        header("Location: ../public/staff_login.php");
        exit();
    }

    if (hasTooManyLoginAttempts($pdo, $ip)) {
        error_log("Too many login attempts from: " . $ip);
        logActivity($pdo, 0, 'Unknown', 'Guest', 'staff_login.php', "Blocked due to too many login attempts from IP: $ip");
        $_SESSION['errors'][] = "Too many failed attempts. Please try again later.";
        header("Location: ../public/staff_login.php");
        exit();
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
        $errors[] = "Incorrect email or password. Please try again.";
    }

    if (empty($errors)) {
        try {
            $user = getUserByEmail($pdo, $email);

            if ($user && password_verify($password, $user['Password'])) {
                if (!$user['EmailVerified']) {
                    $errors[] = "Please verify your email before logging in.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['UserId'] = $user['UserId'];
                    $_SESSION['FirstName'] = $user['FirstName'];
                    $_SESSION['LastName'] = $user['LastName'];
                    $_SESSION['Email'] = $user['Email'];
                    $_SESSION['RoleId'] = $user['RoleId'];
                    $_SESSION['Role'] = $user['RoleName'];
                    $_SESSION['LoggedIn'] = true;
                    $_SESSION['OnboardingComplete'] = $user['OnboardingComplete'];

                    $stmtPermissions = $pdo->prepare("
                        SELECT p.PermissionName
                        FROM Permissions p
                        INNER JOIN RolePermissions rp ON p.PermissionId = rp.PermissionId
                        WHERE rp.RoleId = :RoleId
                    ");
                    $stmtPermissions->execute([':RoleId' => $user['RoleId']]);
                    $_SESSION['Permissions'] = array_map('strtolower', $stmtPermissions->fetchAll(PDO::FETCH_COLUMN));

                    if (empty($_SESSION['OnboardingComplete']) || $_SESSION['OnboardingComplete'] != 1) {
                        if ($_SESSION['Role'] === 'Super Admin') {
                            header("Location: ../public/onboarding_role_creation.php");
                        } else {
                            header("Location: ../public/onboarding_new_password.php");
                        }
                        exit();
                    }

                    logActivity($pdo, $user['UserId'], $user['FirstName'] . ' ' . $user['LastName'], $user['RoleName'], 'staff_login.php', 'Successful login');

                    header("Location: ../public/main_dashboard.php");
                    exit();
                }
            } else {
                recordLoginAttempt($pdo, $ip);
                logActivity($pdo, 0, 'Unknown', 'Guest', 'staff_login.php', "Failed login attempt for email: $email from IP: $ip");
                $errors[] = "Incorrect email or password. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
        }
    }
}
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header("Location: ../public/staff_login.php");
    exit();
}