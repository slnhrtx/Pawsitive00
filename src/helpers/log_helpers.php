<?php
/**
 * Logs user activity securely into the ActivityLog table.
 *
 * @param PDO        $pdo           PDO database connection object.
 * @param int        $userId        User ID performing the action.
 * @param string     $userName      Full name of the user.
 * @param string     $role          Role of the user (e.g., Admin, Staff).
 * @param string     $pageAccessed  The page where the action occurred.
 * @param string     $actionDetails Description of the action performed.
 * @param DateTime|null $timestamp  Optional timestamp; defaults to current time.
 *
 * @return bool      Returns true if logged successfully, false otherwise.
 */
function logActivity(PDO $pdo, int $userId, string $userName, string $role, string $pageAccessed, string $actionDetails, ?DateTime $timestamp = null): bool {
    try {
        // Validate required fields
        if (!$userId || !$userName || !$role || !$pageAccessed) {
            throw new InvalidArgumentException("Missing required parameters for activity logging.");
        }

        // Prepare the SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO ActivityLog (UserId, UserName, Role, PageAccessed, ActionDetails, CreatedAt)
            VALUES (:UserId, :UserName, :Role, :PageAccessed, :ActionDetails, :CreatedAt)
        ");

        // Use current timestamp if none provided
        $createdAt = $timestamp ? $timestamp->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        // Execute the query securely
        $stmt->execute([
            ':UserId'        => $userId,
            ':UserName'      => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
            ':Role'          => htmlspecialchars($role, ENT_QUOTES, 'UTF-8'),
            ':PageAccessed'  => htmlspecialchars($pageAccessed, ENT_QUOTES, 'UTF-8'),
            ':ActionDetails' => htmlspecialchars($actionDetails, ENT_QUOTES, 'UTF-8'),
            ':CreatedAt'     => $createdAt
        ]);

        return true; // Logging successful

    } catch (PDOException $e) {
        logError("Database Error: " . $e->getMessage());
        return false;
    } catch (InvalidArgumentException $e) {
        logError("Validation Error: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        logError("General Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Logs error messages to a daily rotating log file.
 *
 * @param string $errorMessage Error message to log.
 */
function logError(string $errorMessage): void {
    // Log file path with date format (e.g., activity_errors_2024-02-05.log)
    $logDirectory = __DIR__ . '/../../logs';
    $logFile = $logDirectory . '/activity_errors_' . date('Y-m-d') . '.log';

    // Ensure the logs directory exists
    if (!file_exists($logDirectory)) {
        mkdir($logDirectory, 0755, true);
    }

    // Format the log message
    $formattedMessage = "[" . date('Y-m-d H:i:s') . "] " . $errorMessage . PHP_EOL;

    // Write the log message
    error_log($formattedMessage, 3, $logFile);
}