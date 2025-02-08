<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['errors'] = ["Invalid CSRF token."];
        header("Location: ../pages/owner_login.php");
        exit();
    }

    $email = filter_var($_POST['Email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['errors'] = ["Email and Password are required."];
        header("Location: ../pages/owner_login.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT OwnerId, FirstName, LastName, Email, Password FROM Owners WHERE Email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($owner && password_verify($password, $owner['Password'])) {
            $_SESSION['LoggedIn'] = true;
            $_SESSION['OwnerId'] = $owner['OwnerId'];
            $_SESSION['OwnerName'] = $owner['FirstName'] . ' ' . $owner['LastName'];
            $_SESSION['Email'] = $owner['Email'];
            
            header("Location: ../public/pet_owner/index.php");
            exit();
        } else {
            $_SESSION['errors'] = ["Invalid email or password."];
            header("Location: ../public/pet_owner/owner_login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['errors'] = ["Something went wrong. Please try again."];
        header("Location: ../public/pet_owner/owner_login.php");
        exit();
    }
} else {
    header("Location: ../public/owner_login.php");
    exit();
}
?>