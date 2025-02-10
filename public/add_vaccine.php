<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

if (!isset($_GET['pet_id']) || !is_numeric($_GET['pet_id'])) {
    die("Pet ID is missing or invalid.");
}
$pet_id = intval($_GET['pet_id']);

$errors = $_SESSION['errors'] ?? [];
$formData = $_SESSION['formData'] ?? [];
unset($_SESSION['errors'], $_SESSION['formData']);

$pet = [];
if (isset($_GET['pet_id'])) {
    $pet_id = intval($_GET['pet_id']);
    $stmt = $pdo->prepare("SELECT * FROM Pets WHERE PetId = ?");
    $stmt->execute([$pet_id]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $formData = [
        'pet_id' => $_POST['pet_id'] ?? '',
        'weight' => $_POST['weight'] ?? '',
        'vaccine_name' => $_POST['vaccine_name'] ?? '',
        'vaccination_date' => $_POST['vaccination_date'] ?? '',
        'manufacturer' => $_POST['manufacturer'] ?? '',
        'lot_number' => $_POST['lot_number'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $errors = [];

    $formData['pet_id'] = isset($_POST['pet_id']) ? (int)$_POST['pet_id'] : 0;

    if ($formData['pet_id'] <= 0) {
        $errors['pet_id'] = "Pet ID is required.";
    }

    if (empty($formData['vaccine_name'])) {
        $errors['vaccine_name'] = "Vaccine name is required.";
    } elseif (strlen($formData['vaccine_name']) < 2 || strlen($formData['vaccine_name']) > 50) {
        $errors['vaccine_name'] = "Vaccine name must be between 2 and 50 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9\s\-]+$/', $formData['vaccine_name'])) {
        $errors['vaccine_name'] = "Vaccine name can only contain letters, numbers, spaces, and hyphens.";
    }

    $today = date('Y-m-d');

    if (empty($formData['vaccination_date'])) {
        $errors['vaccination_date'] = "Vaccination date is required.";
    } elseif ($formData['vaccination_date'] !== $today) {
        $errors['vaccination_date'] = "Vaccination date must be today's date (" . $today . ").";
    }

    if (empty($formData['manufacturer'])) {
        $errors['manufacturer'] = "Manufacturer name is required.";
    } elseif (strlen($formData['manufacturer']) < 2 || strlen($formData['manufacturer']) > 50) {
        $errors['manufacturer'] = "Manufacturer name must be between 2 and 50 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9\s\-.]+$/', $formData['manufacturer'])) {
        $errors['manufacturer'] = "Manufacturer name can only contain letters, numbers, spaces, hyphens, and periods.";
    }

    if (empty($formData['lot_number'])) {
        $errors['lot_number'] = "Lot number is required.";
    } elseif (strlen($formData['lot_number']) > 15) {
        $errors['lot_number'] = "Lot number must not exceed 15 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9-_]{3,15}$/', $formData['lot_number'])) {
        $errors['lot_number'] = "Lot number must be 3-15 characters long and contain letters, numbers, dashes (-), or underscores (_).";
    }
    if (empty($formData['weight']) || floatval($formData['weight']) <= 0) $errors['weight'] = "Weight must be greater than zero.";

    $pet_check = $pdo->prepare("SELECT PetId FROM Pets WHERE PetId = ?");
    $pet_check->execute([$formData['pet_id']]);
    if ($pet_check->rowCount() === 0) $errors['pet_id'] = "Invalid Pet ID.";

    $duplicate_check = $pdo->prepare("
        SELECT * FROM PetVaccinations 
        WHERE PetId = ? AND VaccinationName = ? AND VaccinationDate = ?
    ");
    $duplicate_check->execute([$formData['pet_id'], $formData['vaccine_name'], $formData['vaccination_date']]);

    if ($duplicate_check->rowCount() > 0) {
        $errors['vaccination_date'] = "This pet has already received this vaccination on the selected date.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO PetVaccinations 
                (PetId, Weight, VaccinationName, VaccinationDate, Manufacturer, LotNumber, Notes, CreatedAt) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $formData['pet_id'],
                $formData['weight'],
                $formData['vaccine_name'],
                $formData['vaccination_date'],
                $formData['manufacturer'],
                $formData['lot_number'],
                $formData['notes']
            ]);

            if (floatval($formData['weight']) > 0) {
                $update_stmt = $pdo->prepare("UPDATE Pets SET Weight = ? WHERE PetId = ?");
                $update_stmt->execute([$formData['weight'], $formData['pet_id']]);
            }

            $invoiceStmt = $pdo->prepare("
                INSERT INTO Invoices (AppointmentId, PetId, InvoiceNumber, InvoiceDate, TotalAmount, Status) 
                VALUES (?, ?, ?, NOW(), ?, 'Pending')
            ");
            $invoiceNumber = 'INV-' . time(); // Generate a unique invoice number
            $vaccineCost = 50.00; // Adjust cost as needed

            $invoiceStmt->execute([
                $_GET['appointment_id'] ?? null, // Ensure appointment_id is provided
                $formData['pet_id'],
                $invoiceNumber,
                $vaccineCost
            ]);

            $_SESSION['success'] = "Vaccination record added successfully!";
            header("Location: pet_profile.php?pet_id=" . $formData['pet_id']);
            exit();
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $_SESSION['errors']['database'] = "Database error occurred.";
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['formData'] = $formData;
        header("Location: add_vaccine.php?pet_id=" . $formData['pet_id']);
        exit();
    }
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <li class="active"><a href="record.php">
                    <img src="../assets/images/Icons/Record 3.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
                    <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                    <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></s>
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
    <h2>Add Vaccination</h2>
    <form class="staff-form" action="add_vaccine.php?pet_id=<?= htmlspecialchars($pet['PetId']) ?>" method="POST" novalidate>
        <input type="hidden" name="pet_id" value="<?= htmlspecialchars($pet['PetId'] ?? '', ENT_QUOTES); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <h2>Vaccine Details</h2>
        <br>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="pet_id">Pet Name:<span class="required-asterisk">*</span></label>
                    <input type="text" value="<?= htmlspecialchars($pet['Name'] ?? 'Unknown Pet', ENT_QUOTES); ?>" readonly>
                    <span class="error-message"><?= $errors['pet_id'] ?? '' ?></span>
                </div>

                <div class="input-container">
                    <label for="weight">Weight (kg):<span class="required-asterisk">*</span></label>
                    <select name="weight" id="weight" required>
                        <option value="">Select Weight</option>
                        <?php
                        // Get the current weight from pet data or form submission
                        $selectedWeight = $formData['weight'] ?? ($pet['Weight'] ?? '');

                        // Generate dropdown options from 0.5 kg to 50 kg
                        for ($i = 0.5; $i <= 50; $i += 0.5): ?>
                            <option value="<?= $i ?>" <?= ($selectedWeight == $i) ? 'selected' : '' ?>>
                                <?= number_format($i, 1) ?> kg
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span class="error-message"><?= $errors['weight'] ?? '' ?></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="vaccine_name">Vaccine Name/Against:<span class="required-asterisk">*</span></label>
                    <input type="text" id="vaccine_name" name="vaccine_name" value="<?= htmlspecialchars($formData['vaccine_name'] ?? '', ENT_QUOTES); ?>" required>
                    <span class="error-message"><?= $errors['vaccine_name'] ?? '' ?></span>
                </div>

                <div class="input-container">
                    <label for="vaccination_date">Date:<span class="required-asterisk">*</span></label>
                    <input type="date" id="vaccination_date" name="vaccination_date" value="<?= htmlspecialchars($formData['vaccination_date'] ?? date('Y-m-d'), ENT_QUOTES); ?>" required>
                    <span class="error-message"><?= $errors['vaccination_date'] ?? '' ?></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="manufacturer">Manufacturer:<span class="required-asterisk">*</span></label>
                    <input type="text" id="manufacturer" name="manufacturer" value="<?= htmlspecialchars($formData['manufacturer'] ?? '', ENT_QUOTES); ?>" required>
                    <span class="error-message"><?= $errors['manufacturer'] ?? '' ?></span>
                </div>
                <div class="input-container">
                    <label for="lot_number">Lot Number:<span class="required-asterisk">*</span></label>
                    <input 
                        type="text" 
                        id="lot_number" 
                        name="lot_number" 
                        value="<?= htmlspecialchars($formData['lot_number'] ?? '', ENT_QUOTES); ?>" 
                        required 
                        maxlength="15" 
                        placeholder="Max 15 characters"
                    >
                    <span class="error-message"><?= $errors['lot_number'] ?? '' ?></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="form-row">
                <div class="input-container">
                    <label for="veterinarian">Veterinarian:<span class="required-asterisk">*</span></label>
                    <input type="text" id="veterinarian" name="veterinarian" 
                        value="<?= htmlspecialchars($_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] ?? 'Not Available', ENT_QUOTES); ?>" >
                </div>
                <div class="input-container">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="form-buttons">
            <button type="submit" class="confirm-btn" onclick="confirmSubmission()">Save Vaccine</button>
        </div>
    </form>
    </div>
    <script>
        document.getElementById("vaccination_date").value = new Date().toISOString().split('T')[0];
    </script>
    <script>
        function confirmSubmission() {
            console.log("Confirm button clicked.");  // Debugging log

            Swal.fire({
                title: 'Confirm Submission',
                text: "Are you sure you want to save this vaccination record?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Save',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log("Submitting form...");  // Debugging log
                    document.querySelector('.staff-form').submit();
                }
            });
        }

        // Display PHP Errors with SweetAlert
        <?php if (!empty($errors)): ?>
            const errors = <?= json_encode(array_values($errors)) ?>;
            Swal.fire({
                icon: 'error',
                title: 'Validation Errors',
                html: errors.map(err => `<p>${err}</p>`).join('')
            });
        <?php endif; ?>
    </script>
    <script>
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?= $_SESSION['success'] ?>',
            icon: 'success',
            confirmButtonText: 'View Invoice'
        }).then(() => {
            window.location.href = 'invoice_billing_form.php';
        });
        <?php unset($_SESSION['success']); // Clear success message ?>
    <?php endif; ?>
</script>
</body>
</html>