<?php
require '../config/dbh.inc.php';

/**
 * Checks if the current user has the specified permission.
 *
 * @param PDO $pdo Database connection object.
 * @param string $permissionName The name of the permission to check.
 * @return bool True if the user has the permission, false otherwise.
 */
function hasPermission(PDO $pdo, string $permissionName): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['RoleId'])) {
        return false;
    }

    $roleId = (int)$_SESSION['RoleId'];

    $query = "
        SELECT 1
        FROM RolePermissions rp
        INNER JOIN Permissions p ON rp.PermissionId = p.PermissionId
        WHERE rp.RoleId = :roleId AND p.PermissionName = :permissionName
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':roleId'         => $roleId,
            ':permissionName' => $permissionName
        ]);

        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Permission Check Failed: " . $e->getMessage());
        return false;
    }
}