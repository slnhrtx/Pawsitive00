<?php
require '../config/dbh.inc.php';
session_start();

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    
    <!-- Link Font Awesome for icons -->
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
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #loadingOverlay .spinner {
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
            <h3 class="form-title">Create New Role</h3>
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
                <p>Select a suggested role or create your own:</p>

                <div class="suggested-roles">
                    <button type="button" onclick="fillRole('Veterinarian', 'Full access to medical records and appointments.')">Veterinarian</button>
                    <button type="button" onclick="fillRole('Receptionist', 'Manage appointments and client check-ins.')">Receptionist</button>
                    <button type="button" onclick="fillRole('Assistant', 'Limited access to health records.')">Assistant</button>
                    <button type="button" onclick="fillRole('Admin', 'Full system access.')">Admin</button>
                </div>

                <br>

                <form action="../src/onboarding_role_creation.php" method="POST" novalidate onsubmit="showLoading()">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="role_name">Role Name:<span class="required-asterisk">*</span></label>
                        <input type="text" id="role_name" name="role_name" placeholder="Enter role name" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="create_add_more" name="action" value="create_add_more">Create Role</button>
                        <button type="button" class="done-btn" onclick="showRoleSummary()">Done</button>
                    </div>
                </form>
            </form>
        </div>
        <div id="roleSummaryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
                            <div style="background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px;">
                                <h2>Roles Created</h2>
                                <ul id="roleSummaryList" style="list-style: none; padding: 0; max-height: 300px; overflow-y: auto;">

                                </ul>
                                <div style="margin-top: 20px; text-align: right;">
                                    <button onclick="closeModal()">Back</button>
                                    <button onclick="confirmRoles()">Confirm and Proceed</button>
                                </div>
                            </div>
                        </div>
    </div>
    <div id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    <script>
        function showLoading() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'flex';
        }

        function fillRole(roleName, description) {
            document.getElementById('role_name').value = roleName;
            document.getElementById('description').value = description;
        }

        function showRoleSummary() {
            const roles = <?= json_encode($_SESSION['created_roles'] ?? []) ?>; // Fetch roles from PHP session
            const roleSummaryList = document.getElementById('roleSummaryList');

            if (roles.length === 0) {
                alert('No roles have been added.');
                return;
            }

            roleSummaryList.innerHTML = roles.map(role => `
                <li style="margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <strong>Role Name:</strong> ${role.name}<br>
                    <strong>Description:</strong> ${role.description || 'No description provided'}
                </li>
            `).join('');

            const modal = document.getElementById('roleSummaryModal');
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('roleSummaryModal');
            modal.style.display = 'none';
        }

        function confirmRoles() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'flex';

            setTimeout(() => {
                window.location.href = 'onboarding_permissions.php';
            }, 1000);
        }
    </script>
    <script>
        function fillRole(roleName, roleDescription) {
            const roleNameInput = document.querySelector('input[placeholder="Enter role name"]');
            const roleDescriptionInput = document.querySelector('textarea[placeholder="Describe the role"]');
            
            roleNameInput.value = roleName;
            roleDescriptionInput.value = roleDescription;

            roleNameInput.style.fontFamily = "Poppins";
            roleDescriptionInput.style.fontFamily = "Poppins";
        }
    </script>
    <script src="../assets/js/password.js" async></script>
</body>
</html>