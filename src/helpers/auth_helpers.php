<?php
/**
 * Check if the user is authenticated and has completed onboarding.
 *
 * @param PDO $pdo Database connection object.
 * @param string $loginRedirect Path to redirect unauthenticated users.
 * @param string $onboardingRedirect Path for users with incomplete onboarding.
 */
function checkAuthentication(PDO $pdo, string $loginRedirect = '../../public/staff_login.php', string $onboardingRedirect = 'onboarding.php'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['LoggedIn'])) {
        header("Location: $loginRedirect");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT OnboardingComplete FROM Users WHERE UserId = :UserId LIMIT 1");
        $stmt->execute([':UserId' => $_SESSION['UserId']]);
        $userStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userStatus || (int) $userStatus['OnboardingComplete'] !== 1) {
            header("Location: $onboardingRedirect");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error in checkAuthentication: " . $e->getMessage());
        header('Location: ../../public/error_page.php'); // Redirect to a user-friendly error page
        exit();
    }
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getUserByEmail(PDO $pdo, string $email)
{
    try {
        $stmt = $pdo->prepare("
            SELECT Users.*, Roles.RoleName 
            FROM Users 
            INNER JOIN UserRoles ON Users.UserId = UserRoles.UserId 
            INNER JOIN Roles ON UserRoles.RoleId = Roles.RoleId 
            WHERE Email = :email
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error in getUserByEmail: " . $e->getMessage());
        return false;
    }
}

function hasTooManyLoginAttempts(PDO $pdo, string $ip, int $limit = 5, int $intervalMinutes = 15)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM LoginAttempts 
            WHERE IPAddress = :ip AND AttemptTime > NOW() - INTERVAL :interval MINUTE
        ");
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':interval', $intervalMinutes, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() >= $limit;
    } catch (PDOException $e) {
        error_log("Database Error in hasTooManyLoginAttempts: " . $e->getMessage());
        return false;
    }
}

function recordLoginAttempt(PDO $pdo, string $ip)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO LoginAttempts (IPAddress, AttemptTime) 
            VALUES (:ip, NOW())
        ");
        $stmt->execute([':ip' => $ip]);
    } catch (PDOException $e) {
        error_log("Database Error in recordLoginAttempt: " . $e->getMessage());
    }
}