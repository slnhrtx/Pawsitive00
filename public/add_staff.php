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

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo->query("SELECT RoleId, RoleName FROM Roles"); // Assuming 'roles' table contains role_id and role name
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $valid_role_ids = array_column($roles, 'role_id');
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $errors[] = "Could not fetch roles. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register_owner.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .required {
            color: red;
            font-weight: bold;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #198754;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1050;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9em;
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }

        .toast.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .toast:not(.show) {
            opacity: 0;
            transform: translateY(20px);
        }

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
                    <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                <li><a href="record.php">
                    <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>                   
                <li class="active"><a href="staff_view.php">
                    <img src="../assets/images/Icons/Staff 3.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Invoice and Billing</a></>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='../logout/logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>

    <div class="main-content">
    <h1>Add New Staff</h1>
    <form class="staff-form" action="../src/add_staff.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <h2>Profile Information</h2>
        <br>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="FirstName">First Name:<span class="required">*</span></label>
                    <input type="text" id="FirstName" name="FirstName" value ="<?= htmlspecialchars($form_data['FirstName'] ?? '') ?>" placeholder="Enter first name" required>
                    <?php if (isset($errors['FirstName'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['FirstName']) ?></span>
                    <?php endif; ?>
                </div>
            
                <div class="input-container">
                    <label for="LastName">Last Name:<span class="required">*</span></label>
                    <input type="text" id="LastName" name="LastName" value ="<?= htmlspecialchars($form_data['LastName'] ?? '') ?>" placeholder="Enter last name"required>
                    <?php if (isset($errors['LastName'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['LastName']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="Email">Email:<span class="required">*</span></label>
                    <input type="email" id="Email" name="Email" value ="<?= htmlspecialchars($form_data['Email'] ?? '') ?>" placeholder="Enter email" required>
                    <?php if (isset($errors['Email'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Email']) ?></span>
                    <?php endif; ?>
                </div>
            
                <div class="input-container">
                    <label for="Phone">Phone Number:<span class="required">*</span></label>
                    <input type="text" id="Phone" name="Phone" value ="<?= htmlspecialchars($form_data['Phone'] ?? '') ?>" placeholder="Enter phone number" required>
                    <?php if (isset($errors['Phone'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Phone']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>   
        <h2>Position Information</h2>
        <br>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="RoleId">Role:<span class="required">*</span></label>
                        <select id="RoleId" name="RoleId" required>
                        <option value="">Select role...</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['RoleId']) ?>">
                                <?= htmlspecialchars($role['RoleName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="input-container">
                <label for="EmploymentType">Employment Type:<span class="required">*</span></label>
                        <select id="EmploymentType" name="EmploymentType" required>
                        <option value="">Select type...</option>
                        <option value="full_time">Full-Time</option>
                        <option value="part_time">Part-Time</option>
                        <option value="contract">Contract</option>
                        <option value="internship">Internship</option>
                    </select>
                </div>
            </div>
        </div>   
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-buttons">
                <button type="button" class="cancel-btn" onclick="window.location.href='staff_view.php'">Cancel</button>
                <button type="submit" class="confirm-btn">Register Staff</button>
            </div>
    </form>
        <br>
        <div class="divider">
            <span>OR</span>
        </div>
        <br>
        <h2>Bulk Invite via Excel</h2>
        <br>
        <form action="invite_staff2.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <label for="excel_file">Upload Excel File:</label>
                        <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required>
                        
                        <button type="submit" class="confirm-btn upload-excel-btn">Upload Excel</button>
                        
                        <p style="margin-top: 10px;">
                            Download the <a href="../src/staff_invite_template.php" class="template-link">Excel Template</a> for bulk upload.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>