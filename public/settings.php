<?php

$db_host = getenv('DB_HOST') ?: '127.0.0.1:3306';
$db_name = getenv('DB_NAME') ?: 'u709058963_pawsitive';
$db_user = getenv('DB_USER') ?: 'u709058963_pawsitive';
$db_pass = getenv('DB_PASS') ?: 'MagicTurtle002.';

ini_set("display_errors",1);
ini_set("display_startup_errors",1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';

session_start();

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

$fullPhoneNumber = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['CountryCode'], $_POST['PhoneNumber'])) {
    $fullPhoneNumber = $_POST['CountryCode'] . $_POST['PhoneNumber'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $command = "mysqldump -u $db_user -p$db_pass $db_name > $backup_filename";
        system($command);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
        readfile($backup_filename);

        unlink($backup_filename);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error during backup. Please try again later.']);
    }
}

// ✅ Only process if the file is uploaded and has a size greater than 0
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file']) && $_FILES['backup_file']['size'] > 0) {
    $uploaded_file = $_FILES['backup_file']['tmp_name'];

    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            // ✅ Restore Command
            $command = "mysql -u $db_user -p$db_pass $db_name < $uploaded_file";
            system($command);

            echo "Database restored successfully.";
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            echo "Database connection failed.";
        }
    } else {
        http_response_code(400);
        echo "Error uploading the backup file.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Only show this error if a POST request was made without a file
    http_response_code(400);
    echo "No valid file uploaded.";
}

try{
    $query = "SELECT * FROM Roles";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $roles = [];
}

try {
    $query = "SELECT * FROM Permissions";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $permissions = [];
}

try {
    $query = "SELECT * FROM Services ORDER BY ServiceId ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $services = [];
}

$query = "
        SELECT r.RoleId, r.RoleName, r.Description, GROUP_CONCAT(p.PermissionName SEPARATOR ', ') AS permissions
        FROM Roles r
        LEFT JOIN RolePermissions rp ON r.RoleId = rp.RoleId
        LEFT JOIN Permissions p ON rp.PermissionId = p.PermissionId
        GROUP BY r.RoleId
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'backup') {
            // Redirect or execute backup logic
            header("Location: ../config/backup.php");
            exit;
        } elseif ($_POST['action'] === 'restore') {
            // Redirect or execute restore logic
            header("Location: ../config/restore_backup.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/settings.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .alert {
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }

        .success-alert {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .error-alert {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
            <ul class="nav-links">
                <li><a href="main_dashboard.php">
                    <img src="../assets/images/Icons/Chart 1.png" alt="Chart Icon">Overview</a></li>
                <li><a href="record.php">
                    <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
                    <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></>
                <li><a href="invoice_billing_form.php">
                    <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';" class="active">
                <img src="../assets/images/Icons/Settings 3.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>
    <div class="settings-container">
        <aside class="settings-sidebar">
            <h1>Settings</h1>
            <ul>
                <li><a href="#user">User Profile</a></li>
                <!--<li><a href="#general" class="active">System Settings</a></li>-->
                <li><a href="#roles">Manage Roles</a></li>
                <!--<li><a href="#existing">Existing Roles</a></li>-->
                <li><a href="#features">Feature Access</a></li>
                <!--<li><a href="#service">Manage Services</a></li>-->
                <!--<li><a href="#password">Password Management</a></li>-->
                <!--<li><a href="#account">Account Settings</a></li>-->
                <li><a href="#backup-recovery">Backup and Recovery</a></li>
            </ul>
        </aside>
        <main class="settings-main">
            <section id="user">
                <h3>User Profile</h3>
                <form action="../src/update_profile.php" method="POST" enctype="multipart/form-data" id="profileForm" novalidate>
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert success-alert"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert error-alert"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    <input type="hidden" name="UserId" value="<?= htmlspecialchars($userId) ?>">
            
                    <label for="FirstName">First Name:</label>
                    <input type="text" id="FirstName" name="FirstName" value="<?= htmlspecialchars($_SESSION['FirstName'] ?? '') ?>" required>
            
                    <label for="LastName">Last Name:</label>
                    <input type="text" id="LastName" name="LastName" value="<?= htmlspecialchars($_SESSION['LastName'] ?? '') ?>" required>
            
                    <label for="Email">Email:</label>
                    <input type="email" id="Email" name="Email" value="<?= htmlspecialchars($_SESSION['Email'] ?? '') ?>" required>
            
                    <label for="PhoneNumber">Phone Number:</label>
                    <div style="display: flex;">
                        <!-- Read-only field for +63 -->
                        <input type="text" 
                            name="CountryCode" 
                            value="+63" 
                            readonly 
                            style="width: 70px; text-align: center; border: 1px solid var(--color-3); border-right: none; border-radius: 5px 0 0 5px; background-color: #f0f0f0;">

                        <!-- Editable field for the phone number -->
                        <input type="text" 
                            id="PhoneNumber" 
                            name="PhoneNumber" 
                            pattern="^[0-9]{10}$" 
                            maxlength="10" 
                            value="<?= htmlspecialchars(substr($_SESSION['PhoneNumber'] ?? '', 3)) ?>" 
                            required 
                            placeholder="9123456789"
                            style="flex: 1; border: 1px solid var(--color-3); border-radius: 0 5px 5px 0; padding: 8px;">
                    </div>
                    <div id="phone-error" style="color: red; font-size: 0.9em; margin-top: 5px;">
                        <?php 
                        if (isset($_SESSION['phone_error'])): 
                            echo htmlspecialchars($_SESSION['phone_error']); 
                            unset($_SESSION['phone_error']); // ✅ Clear the error after displaying
                        endif; 
                        ?>
                    </div>

                    <!--<label for="ProfilePicture">Profile Picture:</label>
                    <input type="file" id="ProfilePicture" name="ProfilePicture" accept="image/*">
                    <?php if (!empty($_SESSION['ProfilePicture'])): ?>
                        <img src="../uploads/profile_pictures/<?= htmlspecialchars($_SESSION['ProfilePicture']) ?>" alt="Profile Picture" width="100">
                    <?php endif; ?>-->
                    <button type="submit" name="update_profile">Save Changes</button>
                </form>
            </section>
            <section id="roles">
                <h3>Manage Roles</h3>
                <form action="../src/add_roles.php" method="POST">
                    <div class="form-group">
                        <label for="role_name">Role Name:</label>
                        <input type="text" id="role_name" name="role_name" placeholder="Enter role name" required>
                    </div>
                    <label for="permissions">Assign Permissions:</label>
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                <th>Allow</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permission): ?>
                                <tr>
                                    <td><?= htmlspecialchars($permission['PermissionName']); ?></td>
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="permissions[]" value="<?= $permission['PermissionId']; ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit">Add Role</button>
                </form>
            </section>
            <section id="features">
                <h3>Feature Access</h3>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert success-alert">
                        <?= htmlspecialchars($_SESSION['success_message']); ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?> <!-- Remove after displaying -->
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert error-alert">
                        <?= htmlspecialchars($_SESSION['error_message']); ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?> <!-- Remove after displaying -->
                <?php endif; ?>
                <form action="../src/update_permissions.php" method="POST">
                <div class="scrollable-container">
                    <table class="permissions-table">
                            <thead>
                                <tr>
                                    <th>Permission</th>
                                    <?php foreach ($roles as $role): ?>
                                        <th><?= htmlspecialchars($role['RoleName']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $rolesQuery = "SELECT * FROM Roles";
                                    $rolesStmt = $pdo->query($rolesQuery);
                                    $roles = $rolesStmt->fetchAll();

                                    $permissionsQuery = "SELECT * FROM Permissions";
                                    $permissionsStmt = $pdo->query($permissionsQuery);
                                    $permissions = $permissionsStmt->fetchAll();

                                    $rolePermissionsQuery = "SELECT RoleId, PermissionId FROM RolePermissions";
                                    $rolePermissionsStmt = $pdo->query($rolePermissionsQuery);
                                    $rolePermissions = $rolePermissionsStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
                                ?>
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
                                                <!-- Hidden input ensures unchecked checkboxes send data -->
                                                <input type="hidden" name="permissions[<?= $roleId; ?>][<?= $permissionId; ?>]" value="0">
                                                <input type="checkbox" 
                                                    name="permissions[<?= $roleId; ?>][<?= $permissionId; ?>]" 
                                                    value="1" 
                                                    <?= $hasPermission ? 'checked' : ''; ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                        <br>
                        <button type="submit">Save Changes</button>
                    </form>
                </section>

            <!--<section id="service">
                <h3>Manage Service</h3>
                <form action="../src/add_roles.php" method="POST">
                    <div class="form-group">
                        <label for="service_name">Add New Service</label>
                        <input type="text" id="service_name" name="service_name" placeholder="Enter Service Name" required>
                        <button type="submit">Add Service</button>
                    </div>

                    <br>

                    <label for="service">Existing Service:</label>
                        <table border="1" cellpadding="10">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Service Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="serviceTable">
                            <?php foreach ($services as $service): ?>
                                <tr data-id="<?= $service['ServiceId'] ?>">
                                    <td><?= htmlspecialchars($service['ServiceId']) ?></td>
                                    <td class="service-name"><?= htmlspecialchars($service['ServiceName']) ?></td>
                                    <td>
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </section>-->
            <section id="backup-recovery">
                <h3>Backup and Recovery</h3>
                <form id="backup-form">
                    <button type="button" id="create-backup">Create Backup</button>
                    <button type="button" id="restore-backup">Restore from Backup</button>
                </form>
            </section>
        </main>
    </div>
    <script>
        document.getElementById('PhoneNumber').addEventListener('input', function (e) {
            const errorMsg = document.getElementById('phone-error');

            // Remove non-digit characters
            this.value = this.value.replace(/\D/g, '');

            // Error display conditions
            if (/\D/.test(e.data)) {
                errorMsg.textContent = "Only numeric characters are allowed.";
            } else if (this.value.length > 10) {
                errorMsg.textContent = "Phone number cannot exceed 10 digits.";
            } else {
                errorMsg.textContent = '';
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
        const sidebarLinks = document.querySelectorAll('.settings-sidebar ul li a');

            sidebarLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    // Remove 'active' class from all links
                    sidebarLinks.forEach((link) => link.classList.remove('active'));

                    // Add 'active' class to the clicked link
                    event.currentTarget.classList.add('active');
                });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
        const resetPasswordForm = document.getElementById('reset-password-form');
        const forcePasswordForm = document.getElementById('force-password-form');
        const forceAllCheckbox = document.getElementById('force-all');
        const userSelection = document.getElementById('user-selection');

        forceAllCheckbox.addEventListener('change', () => {
            userSelection.style.display = forceAllCheckbox.checked ? 'none' : 'block';
        });

        resetPasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const userId = document.getElementById('user-id').value;
            const newPassword = document.getElementById('new-password').value;

            fetch('../../password/setting_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, new_password: newPassword }),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert(data.message);
                        resetPasswordForm.reset();
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch((error) => console.error('Error:', error));
        });

        // Handle force password update form submission
        forcePasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const forceAll = forceAllCheckbox.checked;
            const selectedUsers = Array.from(
                document.getElementById('selected-users').selectedOptions
            ).map((option) => option.value);

            fetch('../../password/force_password_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ force_all: forceAll, user_ids: selectedUsers }),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert(data.message);
                        forcePasswordForm.reset();
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch((error) => console.error('Error:', error));
        });
    });
    </script>

    <script>
    document.getElementById("create-backup").addEventListener("click", function () {
        Swal.fire({
            title: "Create Backup?",
            text: "This will create a new backup of the database.",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, create it!"
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("../config/backup.php", {
                    method: "POST",
                })
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = "backup_" + new Date().toISOString().slice(0, 19).replace(/:/g, "-") + ".sql";
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    Swal.fire(
                        "Backup Created!",
                        "Your database has been backed up successfully.",
                        "success"
                    );
                })
                .catch(error => {
                    console.error("Backup failed:", error);
                    Swal.fire(
                        "Backup Failed!",
                        "There was an error creating the backup. Please try again.",
                        "error"
                    );
                });
            }
        });
    });

        document.getElementById("restore-backup").addEventListener("click", function () {
        Swal.fire({
            title: "Restore Backup?",
            text: "This will overwrite the current database with the backup file.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, restore it!",
            html: `
                <input type="file" id="backup-file" class="swal2-file" accept=".sql" style="margin-top: 15px;">
            `
        }).then((result) => {
            if (result.isConfirmed) {
                const fileInput = document.getElementById("backup-file");
                const file = fileInput.files[0];

                if (!file) {
                    Swal.fire("No File Selected", "Please choose a backup file to restore.", "error");
                    return;
                }

                const formData = new FormData();
                formData.append("backup_file", file);

                fetch("../config/restore_backup.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.text())
                .then(result => {
                    Swal.fire(
                        "Restoration Complete!",
                        "The database has been successfully restored.",
                        "success"
                    );
                })
                .catch(error => {
                    console.error("Restore failed:", error);
                    Swal.fire(
                        "Restoration Failed!",
                        "An error occurred while restoring the database. Please try again.",
                        "error"
                    );
                });
            }
        });
    });
    </script>

</body>
</html>