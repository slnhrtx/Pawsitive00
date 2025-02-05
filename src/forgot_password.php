<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/helpers/session_helpers.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

enhanceSessionSecurity();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if (empty($token) || $token !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please refresh the page and try again.";
    }

    if (empty($_POST['email'])) {
        $errors[] = "Please enter your email address.";
    } elseif (!$email) {
        $errors[] = "Invalid email address.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM Users WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $pdo->prepare("DELETE FROM PasswordResets WHERE ExpiresAt < NOW()")->execute();

                $stmt = $pdo->prepare("SELECT CreatedAt FROM PasswordResets WHERE Email = ? ORDER BY CreatedAt DESC LIMIT 1");
                $stmt->execute([$email]);
                $lastRequest = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($lastRequest && !empty($lastRequest['CreatedAt'])) {
                    date_default_timezone_set('Asia/Manila');
                    $createdTime = strtotime($lastRequest['CreatedAt']);
                    $currentTime = time();
                    $timeDifference = $currentTime - $createdTime;

                    if ($timeDifference < 900) {
                        $errors[] = "A reset request was already sent. Please wait before trying again.";
                    }
                }

                if (empty($errors)) {
                    $resetToken = bin2hex(random_bytes(32));
                    $expiresAt = date("Y-m-d H:i:s", strtotime('+1 hour'));

                    $stmt = $pdo->prepare("
                        INSERT INTO PasswordResets (Email, Token, ExpiresAt) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE Token = ?, ExpiresAt = ?
                    ");
                    $stmt->execute([$email, $resetToken, $expiresAt, $resetToken, $expiresAt]);

                    if (sendResetEmail($email, $resetToken)) {
                        $_SESSION['successMessage'] = "A password reset link has been sent to your email.";
                        header("Location: ../public/forgot_password.php");
                        exit();
                    } else {
                        $errors[] = "We could not send the email. Please try again later.";
                    }
                }
            } else {
                $errors[] = "This email is not registered in our system.";
            }
        } catch (Exception $e) {
            error_log("Error processing password reset: " . $e->getMessage());
            $errors[] = "Something went wrong. Please try again later.";
        }
    }
    $_SESSION['errors'] = $errors;
    $_SESSION['old_email'] = $_POST['email'] ?? '';
    header("Location: ../public/forgot_password.php");
    exit();
}

/**
 * Sends a password reset email using Hostinger SMTP.
 *
 * @param string $email Recipient email address.
 * @param string $token Password reset token.
 * @return bool True if email sent successfully, False otherwise.
 */
function sendResetEmail($email, $token)
{
    $resetLink = "https://vetpawsitive.com/public/reset_password.php?token=" . urlencode($token);
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@vetpawsitive.com';
        $mail->Password = 'Pawsitive3.';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@vetpawsitive.com', 'Pawsitive Support');
        $mail->addReplyTo('support@vetpawsitive.com', 'Pawsitive Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your Secure Password Reset Request - Pawsitive';
        $mail->Body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333; background-color: #f9f9f9; padding: 20px;'>
                <table align='center' width='100%' cellpadding='0' cellspacing='0' style='max-width: 600px; background: #ffffff; padding: 20px; border-radius: 8px;'>
                    <tr>
                        <td align='center'>
                            <h2 style='color: #008CBA;'>Reset Your Password</h2>
                            <p>Hello,</p>
                            <p>You recently requested to reset your password for your <strong>Pawsitive</strong> account.</p>
                            <p>Click the button below to securely reset your password:</p>
                            <a href='$resetLink' style='display: inline-block; background-color:#008CBA; color:white; padding:12px 18px; text-decoration:none; border-radius:5px; font-size:16px;'>Reset Password</a>
                            <p>If the button does not work, copy and paste this link into your browser:</p>
                            <p><a href='$resetLink' style='word-wrap: break-word;'>$resetLink</a></p>
                            <p>If you did not request this, please ignore this email.</p>
                            <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #666;'>Pawsitive Support | 123 Pet Care Street, PetCity, Country</p>
                            <p style='font-size: 12px;'><a href='https://vetpawsitive.com/unsubscribe.php?email=$email' style='color: #008CBA;'>Unsubscribe</a></p>
                        </td>
                    </tr>
                </table>
            </body>
        </html>";

        $mail->AltBody = "Hello,\n\nYou recently requested to reset your password for your Pawsitive account. Click the link below to securely reset your password:\n\n$resetLink\n\nIf you did not request this, please ignore this email.\n\nPawsitive Support";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent to $email: {$mail->ErrorInfo}");
        return false;
    }
}