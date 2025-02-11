<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
session_start();

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

$roles = [];
$permissions = [];
$rolePermissions = [];

try {
    $stmtRoles = $pdo->query("SELECT RoleId, RoleName FROM Roles");
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    $stmtPermissions = $pdo->query("SELECT PermissionId, PermissionName FROM Permissions");
    $permissions = $stmtPermissions->fetchAll(PDO::FETCH_ASSOC);

    $stmtRolePermissions = $pdo->query("SELECT RoleId, PermissionId FROM RolePermissions");
    $rolePermissionsData = $stmtRolePermissions->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rolePermissionsData as $rp) {
        $rolePermissions[$rp['RoleId']][] = $rp['PermissionId'];
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}
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
    <link rel="stylesheet" href="../assets/css/new_onboarding.css">
    <script src="../assets/js/index.js" defer></script>
    <style>
        .success-message {
            color: #155724; /* Green color for success text */
            background-color: #d4edda; /* Light green background */
            padding: 12px 15px; /* Add padding for better readability */
            margin-bottom: 15px;
            border: 1px solid #c3e6cb; /* Green border */
            border-radius: 8px; /* Rounded corners */
            font-weight: bold; /* Make text bold */
            font-size: 14px; /* Consistent font size */
            display: flex; /* Flexbox for icon and text alignment */
            align-items: center;
            gap: 10px; /* Space between icon and text */
        }
        .error-message {
            color: #842029; /* Darker red for better contrast */
            background-color: #f8d7da; /* Subtle red background */
            padding: 12px 15px; /* Slightly larger padding for better readability */
            margin-bottom: 15px;
            border: 1px solid #f5c6cb; /* Matching border color */
            border-radius: 8px; /* Softer border radius */
            font-weight: 600; /* Slightly less bold for readability */
            font-size: 14px; /* Ensure consistent font size */
            display: flex; /* Flexbox for icon and text alignment */
            align-items: center;
            gap: 10px; /* Space between icon and text */
        }

        /* Hover effect for interactivity */
        .error-message:hover {
            background-color: #f5c2c7; /* Slightly darker background on hover */
            border-color: #f1aeb5; /* Subtle hover border effect */
        }
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>
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
            <h3 class="form-title">Assign Permissions to Role</h3>
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <!-- Optional Font Awesome icon -->
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <ul class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <!-- Optional Font Awesome icon -->
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <form action="../src/onboarding_permissions.php" method="POST">
            <div class="table-container">
                <table class="permissions-table">
                    <thead>
                        <tr>
                            <th>PERMISSION</th>
                            <?php foreach ($roles as $role): ?>
                                <th><?= htmlspecialchars($role['RoleName']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $permission): ?>
                            <tr>
                                <td><?= htmlspecialchars($permission['PermissionName']); ?></td>
                                <?php foreach ($roles as $role): ?>
                                    <?php
                                        $roleId = $role['RoleId'];
                                        $permissionId = $permission['PermissionId'];
                                        $hasPermission = in_array($permissionId, $rolePermissions[$roleId] ?? []);
                                    ?>
                                    <td style="text-align: center;">
                                        <input type="checkbox" 
                                            name="permissions[<?= $roleId; ?>][]" 
                                            value="<?= $permissionId; ?>"
                                            <?= $hasPermission ? 'checked' : ''; ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                <button type="submit" class="save_changes-btn" name="action" value="save_changes">Save Changes</button>
            </form>
            <form action="staff_invitation_form.php" method="GET" onsubmit="showLoadingAndSubmit(this)">
                <button type="submit" class="continue_invite" name="action" value="continue_invite">Continue to Invite Staff</button>
            </form>
        </div>
    </div>
    <div id="loadingOverlay" style="display: none;">
        <div class="spinner"></div>
        <p style="color: white; margin-top: 15px;">Loading, please wait...</p>
    </div>
    <script>
            function fillRole(roleName, description) {
                document.getElementById('role_name').value = roleName;
                document.getElementById('description').value = description;
            }
            function showLoading() {
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.style.display = 'flex';
            }

            function showLoadingAndSubmit(form) {
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.style.display = 'flex';

                // Delay the form submission slightly to ensure the overlay is shown
                setTimeout(() => {
                    form.submit();
                }, 100);
            }
    </script>
    <script>
        function fillRole(roleName, roleDescription) {
            const roleNameInput = document.querySelector('input[placeholder="Enter role name"]');
            const roleDescriptionInput = document.querySelector('textarea[placeholder="Describe the role"]');
            
            roleNameInput.value = roleName;
            roleDescriptionInput.value = roleDescription;
            
            // Apply Poppins font to the filled text
            roleNameInput.style.fontFamily = "Poppins";
            roleDescriptionInput.style.fontFamily = "Poppins";
        }
    </script>
    <script src="../assets/js/password.js" async></script>
</body>
</html>