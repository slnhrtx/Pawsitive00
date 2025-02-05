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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!hasPermission($pdo, 'Create Owner')) {
    echo "You do not have permission to access this page";
    exit;
}
$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['errors'], $_SESSION['success'], $_SESSION['form_data']);

$petTypes = [];
$petTypeQuery = "SELECT Id, SpeciesName FROM Species";

try {
    $stmt = $pdo->query($petTypeQuery);
    $petTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pet types: " . $e->getMessage());
    $petTypes = [];
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $create_account = $_POST['CreateAccount'] ?? 'no';
        $first_name = sanitizeInput($_POST['FirstName'] ?? '');
        $last_name = sanitizeInput($_POST['LastName'] ?? '');
        $email = filter_var($_POST['Email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone_number = sanitizeInput($_POST['Phone'] ?? '');
        $pet_name = sanitizeInput($_POST['Name'] ?? '');
        $species_id = filter_var($_POST['SpeciesId'] ?? 0, FILTER_VALIDATE_INT);
        $breed = sanitizeInput($_POST['Breed'] ?? '');
        $gender = sanitizeInput($_POST['Gender'] ?? '');
        $birthday_or_year = sanitizeInput($_POST['Birthday'] ?? '');
        $calculated_age = filter_var($_POST['CalculatedAge'] ?? 0, FILTER_VALIDATE_INT);
        $weight = filter_var($_POST['Weight'] ?? '', FILTER_VALIDATE_FLOAT);
        $last_visit = !empty($_POST['LastVisit']) ? sanitizeInput($_POST['LastVisit']) : null;

        if (empty($first_name)) {
            $errors['FirstName'] = "First name is required.";
        } elseif (strlen($first_name) < 2) {
            $errors['FirstName'] = "First name must be at least 2 characters long.";
        } elseif (!preg_match('/^[A-Za-z\s]+$/', $first_name)) {
            $errors['FirstName'] = "First name must contain only letters and spaces.";
        } elseif (strlen($first_name) > 50) {
            $errors['FirstName'] = "First name must not exceed 50 characters.";
        } elseif (preg_match('/\s{2,}/', $first_name)) {
            $errors['FirstName'] = "First name should not contain consecutive spaces.";
        }

        if (empty($last_name)) {
            $errors['LastName'] = "Last name is required.";
        } elseif (strlen($last_name) < 2) {
            $errors['LastName'] = "Last name must be at least 2 characters long.";
        } elseif (!preg_match('/^[A-Za-z\s]+$/', $last_name)) {
            $errors['LastName'] = "Last name must contain only letters and spaces.";
        } elseif (strlen($last_name) > 50) {
            $errors['LastName'] = "Last name must not exceed 50 characters.";
        } elseif (preg_match('/\s{2,}/', $first_name)) {
            $errors['LastName'] = "Last name should not contain consecutive spaces.";
        }

        if (empty($email)) {
            $errors['Email'] = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['Email'] = "Invalid email format.";
        } elseif (strlen($email) > 254) {
            $errors['Email'] = "Email address must not exceed 254 characters.";
        } elseif ($email !== trim($email)) {
            $errors['Email'] = "Email address should not start or end with spaces.";
        } elseif (preg_match('/\.{2,}/', $email)) {
            $errors['Email'] = "Email address should not contain consecutive dots.";
        }

        if (empty($pet_name)) {
            $errors['Name'] = "Pet name is required.";
        } elseif (strlen(trim($pet_name)) < 2) {
            $errors['Name'] = "Pet name must be at least 2 characters long.";
        } elseif (strlen($pet_name) > 50) {
            $errors['Name'] = "Pet name must not exceed 50 characters.";
        } elseif ($pet_name !== trim($pet_name)) {
            $errors['Name'] = "Pet name should not start or end with spaces.";
        } elseif (preg_match('/\s{2,}/', $pet_name)) {
            $errors['Name'] = "Pet name should not contain consecutive spaces.";
        } elseif (!preg_match('/^[A-Za-z\s\'-]+$/', $pet_name)) {
            $errors['Name'] = "Pet name can only contain letters, spaces, hyphens, and apostrophes.";
        }

        if (empty($species_id) || $species_id <= 0) {
            $errors['SpeciesId'] = "Please select a valid species.";
        }

        if (empty($phone_number)) {
            $errors['Phone'] = "Phone number is required.";
        } elseif (!preg_match('/^\+63\d{10}$/', $phone_number)) {
            $errors['Phone'] = "Phone number must start with +63 followed by exactly 10 digits.";
        }

        if (empty($birthday_or_year)) {
            $errors['BirthdayOrYear'] = "Birthday is required.";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday_or_year)) {
            $errors['BirthdayOrYear'] = "Invalid format. Please use YYYY-MM-DD.";
        } else {
            [$year, $month, $day] = explode('-', $birthday_or_year);
            $currentYear = (int) date('Y');

            if (!checkdate((int) $month, (int) $day, (int) $year)) {
                $errors['BirthdayOrYear'] = "Invalid date. Please provide a valid date.";
            } elseif ((int) $year < 1900 || (int) $year > $currentYear) {
                $errors['BirthdayOrYear'] = "Birth year must be between 1900 and $currentYear.";
            }
        }

        if (empty($weight) || $weight <= 0) {
            $errors['Weight'] = "Weight is required.";
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header('Location: register_owner.php');
            exit;
        }

        $pdo->beginTransaction();

        $sql = "INSERT INTO Owners (FirstName, LastName, Email, Phone) 
                VALUES (:FirstName, :LastName, :Email, :Phone)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':FirstName' => $first_name,
            ':LastName' => $last_name,
            ':Email' => $email,
            ':Phone' => $phone_number,
        ]);

        $owner_id = $pdo->lastInsertId();

        $sql = "INSERT INTO Pets (OwnerId, Name, SpeciesId, Breed, Gender, Birthday, CalculatedAge, Weight, LastVisit)
                VALUES (:OwnerId, :Name, :SpeciesId, :Breed, :Gender, :Birthday, :CalculatedAge, :Weight, :LastVisit)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':OwnerId' => $owner_id,
            ':Name' => $pet_name,
            ':SpeciesId' => $species_id,
            ':Breed' => $breed,
            ':Gender' => $gender,
            ':Birthday' => $birthday_or_year,
            ':CalculatedAge' => $calculated_age,
            ':Weight' => $weight,
            ':LastVisit' => $last_visit,
        ]);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'no-reply@vetpawsitive.com';
            $mail->Password = 'Pawsitive3.';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@vetpawsitive.com', 'Pawsitive');
            $mail->addReplyTo('support@vetpawsitive.com', 'Pawsitive Support');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Welcome to Pawsitive";
            $mail->Body = "
                <p>Hello {$first_name},</p>
                <p>We are pleased to inform you that your pet's information has been successfully registered in the Pawsitive system under Pet Adventure Clinic.</p>
                <p>Your pet's record will be used for the management of health information, appointment scheduling, and other related services at Pet Adventure Clinic. Rest assured that all information provided will be securely stored and used only for the purposes of ensuring the best care for your pet.</p>
                <p>If you have any questions, feel free to contact us at support@vetpawsitive.com.</p>
            ";


            $mail->send();
        } catch (Exception $mailException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Mailer Error: {$mail->ErrorInfo}");
            throw new Exception("Email could not be sent.");
        }
        logActivity($pdo, $userId, $userName, $role, 'register_owner.php', 'Added owner: ' . $first_name . ' ' . $last_name . ' and pet: ' . $pet_name);
        $pdo->commit();

        $_SESSION['success'] = "Owner and pet registered successfully! An email has been sent.";
        header('Location: record.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        echo "An error occurred: " . $e->getMessage();
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
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
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
            color: #155724;
            /* Green color for success text */
            background-color: #d4edda;
            /* Light green background */
            padding: 12px 15px;
            /* Add padding for better readability */
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            /* Green border */
            border-radius: 8px;
            /* Rounded corners */
            font-weight: bold;
            /* Make text bold */
            font-size: 14px;
            /* Consistent font size */
            display: flex;
            /* Flexbox for icon and text alignment */
            align-items: center;
            gap: 10px;
            /* Space between icon and text */
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
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></>
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
        <h1>Register Pet Owner</h1>
        <form class="staff-form" action="register_owner.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <!-- Optional Font Awesome icon -->
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <h2>Owner Information</h2>
            <br>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="FirstName">First Name:<span class="required">*</span></label>
                        <input type="text" id="FirstName" name="FirstName"
                            value="<?= htmlspecialchars($form_data['FirstName'] ?? '') ?>"
                            placeholder="Enter first name" required>
                        <?php if (isset($errors['FirstName'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['FirstName']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="LastName">Last Name:<span class="required">*</span></label>
                        <input type="text" id="LastName" name="LastName"
                            value="<?= htmlspecialchars($form_data['LastName'] ?? '') ?>" placeholder="Enter last name"
                            required>
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
                        <input type="email" id="Email" name="Email"
                            value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>" placeholder="Enter email"
                            required>
                        <?php if (isset($errors['Email'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="Phone">Phone Number:<span class="required">*</span></label>
                        <input type="text" id="Phone" name="Phone"
                            value="<?= isset($form_data['Phone']) ? htmlspecialchars($form_data['Phone']) : '+63' ?>"
                            placeholder="Enter phone number" required maxlength="13" pattern="^\+63\d{10}$"
                            title="Phone number should start with +63 followed by 10 digits">
                        <?php if (isset($errors['Phone'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <h2>Pet Information</h2>
            <br>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="Name">Pet Name:<span class="required-asterisk">*</span></label>
                        <input type="text" id="Name" name="Name"
                            value="<?= htmlspecialchars($form_data['Name'] ?? '') ?>" required minlength="3"
                            aria-invalid="false" placeholder="Enter pet name">
                        <?php if (isset($errors['Name'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="Gender">Gender:</label>
                        <select id="Gender" name="Gender" required>
                            <option value="">Select gender</option>
                            <option value="1" <?= isset($form_data['Gender']) && $form_data['Gender'] === '1' ? 'selected' : '' ?>>Male</option>
                            <option value="2" <?= isset($form_data['Gender']) && $form_data['Gender'] === '2' ? 'selected' : '' ?>>Female</option>
                        </select>
                        <?php if (isset($errors['Gender'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Gender']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="SpeciesId">Pet Type:<span class="required-asterisk">*</span></label>
                        <select id="SpeciesId" name="SpeciesId" required>
                            <option value="">Select pet type</option>
                            <?php foreach ($petTypes as $petType): ?>
                                <option value="<?= $petType['Id']; ?>" <?= (isset($form_data['SpeciesId']) && $form_data['SpeciesId'] == $petType['Id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($petType['SpeciesName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['SpeciesId'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['SpeciesId']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="input-container">
                        <label for="Breed">Breed:</label>
                        <select id="Breed" name="Breed">
                            <option value="">Select breed</option>
                            <?php if (isset($form_data['SpeciesId'])): ?>
                                <?php
                                $speciesId = $form_data['SpeciesId'];
                                $stmt = $pdo->prepare("SELECT BreedId, BreedName FROM Breeds WHERE SpeciesId = ?");
                                $stmt->execute([$speciesId]);
                                $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php foreach ($breeds as $breed): ?>
                                    <option value="<?= $breed['BreedId']; ?>" <?= (isset($form_data['Breed']) && $form_data['Breed'] == $breed['BreedId']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($breed['BreedName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['Breed'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Breed']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="Birthday">Birthday: <span class="required">*</span></label>
                        <input type="date" id="Birthday" name="Birthday"
                            value="<?= htmlspecialchars($form_data['Birthday'] ?? '') ?>" placeholder="YYYY-MM-DD"
                            required>
                        <?php if (isset($errors['BirthdayOrYear'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['BirthdayOrYear']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="CalculatedAge">Calculated Age:<span class="required">*</span></label>
                        <input type="text" id="CalculatedAge" name="CalculatedAge"
                            value="<?= htmlspecialchars($form_data['CalculatedAge'] ?? '') ?>" readonly>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="Weight">Weight (kg):<span class="required">*</span></label>
                        <input type="number" id="Weight" name="Weight"
                            value="<?= htmlspecialchars($form_data['Weight'] ?? '') ?>" step="0.1" min="0" max="50"
                            placeholder="Enter weight (e.g., 10.5)" required>
                        <?php if (isset($errors['Weight'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Weight']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="input-container">
                        <label for="LastVisit">Date of Last Visit:</label>
                        <input type="date" id="LastVisit" name="LastVisit">
                    </div>
                </div>
            </div>

            <div class="form-buttons">
                <button type="button" class="cancel-btn" onclick="window.location.href='record.php'">Cancel</button>
                <button type="button" class="confirm-btn" onclick="confirmSubmission()">Add Owner and Pet</button>
            </div>
            <script>
                function confirmSubmission() {
                    Swal.fire({
                        title: 'Confirm Adding',
                        text: "Are you sure you want to add this owner and pet?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#198754', // Green color for confirm button
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, register',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Submit the form after confirmation
                            document.querySelector('.staff-form').submit();
                        }
                    });
                }
            </script>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const phoneInput = document.getElementById("Phone");

                    // Ensure +63 is always present
                    if (!phoneInput.value.startsWith("+63")) {
                        phoneInput.value = "+63";
                    }

                    phoneInput.addEventListener("input", function () {
                        if (!this.value.startsWith("+63")) {
                            this.value = "+63";  // Restore +63 if deleted
                        }
                    });

                    phoneInput.addEventListener("keydown", function (event) {
                        // Prevent backspace/delete for the first 3 characters (+63)
                        if ((this.selectionStart <= 3) &&
                            (event.key === "Backspace" || event.key === "Delete")) {
                            event.preventDefault();
                        }
                    });
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    <?php if (!empty($message)): ?>
                        showToast("<?= htmlspecialchars($message) ?>");
                    <?php endif; ?>
                });

                function showToast(message) {
                    const toast = document.getElementById("successToast");
                    const toastBody = toast.querySelector(".toast-body");

                    toastBody.textContent = message;

                    // Add the show class
                    toast.classList.add("show");

                    // Remove the show class after 4 seconds
                    setTimeout(() => {
                        toast.classList.remove("show");
                    }, 4000);
                }
            </script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
            <script src="../assets/js/register_owner.js?v=1.0.1"></script>
            <script>
                document.getElementById('SpeciesId').addEventListener('change', function () {
                    const speciesId = this.value;

                    if (speciesId) {
                        fetch(`fetch_breeds.php?SpeciesId=${speciesId}`)
                            .then(response => response.json())
                            .then(breeds => {
                                const breedSelect = document.getElementById('Breed');
                                breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset options

                                // Populate dropdown
                                breeds.forEach(breed => {
                                    const option = document.createElement('option');
                                    option.value = breed.BreedId; // Use the `BreedId` as value
                                    option.textContent = breed.BreedName; // Use `BreedName` for display
                                    breedSelect.appendChild(option);
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching breeds:', error);
                                alert("Unable to fetch breeds. Please try again later.");
                            });
                    } else {
                        const breedSelect = document.getElementById('Breed');
                        breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset options
                    }
                });
            </script>
</body>

</html>