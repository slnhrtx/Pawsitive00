<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $first_name = filter_input(INPUT_POST, "FirstName", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, "LastName", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'Email', FILTER_VALIDATE_EMAIL);
    $role_id = filter_input(INPUT_POST, 'RoleId', FILTER_SANITIZE_NUMBER_INT);
    $employment_type = filter_input(INPUT_POST, 'EmploymentType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($first_name)){
        $errors['FirstName'] = "First name is required.";
    } elseif (strlen($first_name) < 2) {
        $errors['FirstName'] = "First name must be at least 2 characters long.";
    } 

    if (empty($last_name)) {
        $errors['LastName'] = "Last name is required.";
    } elseif (strlen($last_name) < 2) {
        $errors['LastName'] = "Last name must be at least 2 characters long.";
    }
    
    if (empty($email)) {
        $errors['Email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = "Invalid email format.";
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header('Location: ../public/add_staff.php');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE Email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            die("Email already exists.");
        }
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO Users (FirstName, LastName, Email, Password, RoleId, EmploymentType, Status, CreatedAt)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([$first_name, $last_name, $email, $hashed_password, $role_id, $employment_type]);

        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO UserRoles (UserId, RoleId) VALUES (?, ?)");
        $stmt->execute([$user_id, $role_id]);

        $pdo->commit();

        $activity = "Added new staff: $first_name $last_name with email $email";
        $loggedInUserId = $_SESSION['UserId'];

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'danvincentteodoro11@gmail.com';
        $mail->Password = 'fhvt onlo hdwm wjlx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('danvincentteodoro11@gmail.com', 'Pawsitive');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to Pawsitive!";
        $mail->Body = "
            <p>Welcome to Pawsitive!</p>
            <p>Thank you for joining us! You have been successfully added to the system.</p>
            <p>Your temporary password is: <strong>$temp_password</strong></p>
            <p>Please make sure to change your password after logging in.</p>
            <p>Thank you for trusting Pawsitive!</p>";

        $mail->send();

        logActivity($pdo, $userId, $userName, $role, 'Added Staff', "Added new staff: $first_name $last_name ($email)");

        $_SESSION['success'] = "Added new staff: $first_name $last_name with email $email";
        header("Location: ../public/staff_view.php?success=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        die("An error occurred.");
    }
}