<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$appointment_id = $_POST['appointment_id'] ?? $_GET['appointment_id'] ?? null;
$pet_id = $_POST['pet_id'] ?? $_GET['pet_id'] ?? null;

$errors = [];
$successMessage = "";
$appointmentDetails = [];
$latestVaccineName = '';

if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.Name AS PetName,
                p.Weight,
                p.Temperature,
                a.AppointmentDate,
                s.ServiceName
            FROM Appointments a
            INNER JOIN Pets p ON a.PetId = p.PetId
            INNER JOIN Services s ON a.ServiceId = s.ServiceId
            WHERE a.AppointmentId = :appointment_id AND p.PetId = :pet_id
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $appointmentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

if ($appointment_id) {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE AppointmentId = :appointment_id");
    $stmtCheck->execute([':appointment_id' => $appointment_id]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists == 0) {
        $errors[] = "Invalid Appointment ID: No matching record found.";
    }
}

$latestVaccinationDate = '';
if ($pet_id) {
    $stmt = $pdo->prepare("
        SELECT VaccinationDate ,VaccinationName
        FROM PetVaccinations 
        WHERE PetId = :pet_id 
        ORDER BY VaccinationDate DESC 
        LIMIT 1
    ");
    $stmt->execute([':pet_id' => $pet_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $latestVaccinationDate = $result['VaccinationDate'];
        $latestVaccineName = $result['VaccinationName'];
    }
}

$patientRecord = [];
if ($appointment_id && $pet_id) {
    try {
        $stmtRecord = $pdo->prepare("
            SELECT * FROM PatientRecords 
            WHERE AppointmentId = :appointment_id AND PetId = :pet_id
        ");
        $stmtRecord->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $patientRecord = $stmtRecord->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$medicalHistory = [];
if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT mh.* 
            FROM MedicalHistory AS mh
            INNER JOIN PatientRecords AS pr ON mh.PetId = pr.PetId
            WHERE pr.AppointmentId = :appointment_id AND pr.PetId = :pet_id
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $medicalHistory = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$recordId = $_GET['record_id'] ?? null;

$physicalExam = [];

if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM PhysicalExams
            WHERE RecordId = (
                SELECT RecordId FROM PatientRecords 
                WHERE AppointmentId = :appointment_id AND PetId = :pet_id
            )
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $physicalExam = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$recordId = $_GET['record_id'] ?? null;
$labTests = [];

if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM LaboratoryTests
            WHERE RecordId = (
                SELECT RecordId FROM PatientRecords 
                WHERE AppointmentId = :appointment_id AND PetId = :pet_id
            )
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $labTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$uploadedFiles = [];

$stmt = $pdo->prepare("SELECT TestName, FilePath FROM LaboratoryTests WHERE RecordId = (SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id)");
$stmt->execute([
    ':appointment_id' => $_GET['appointment_id'] ?? 0,
    ':pet_id' => $_GET['pet_id'] ?? 0
]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['FilePath'])) {
        $uploadedFiles[$row['TestName']] = $row['FilePath'];
    }
}

$recordId = $_GET['record_id'] ?? null;
$diagnosisData = [];
if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*
            FROM Diagnoses d
            INNER JOIN PatientRecords pr ON d.RecordId = pr.RecordId
            WHERE pr.AppointmentId = :appointment_id AND pr.PetId = :pet_id
            ORDER BY d.DiagnosisId DESC LIMIT 1
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $diagnosisData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$medicationsGiven = [];
if ($appointment_id && $pet_id) {
    $stmt = $pdo->prepare("SELECT MedicationName FROM MedicationsGiven WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
    $stmt->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);
    $medicationsGiven = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$_SESSION['medication_data']['medications'] = $medicationsGiven;
$selectedMedications = $_SESSION['medication_data']['medications'] ?? [];

$followUpRecords = [];
if ($appointment_id && $pet_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT FollowUpDate, FollowUpNotes 
            FROM FollowUps 
            WHERE RecordId = (
                SELECT RecordId FROM PatientRecords 
                WHERE AppointmentId = :appointment_id AND PetId = :pet_id
            )
        ");
        $stmt->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ]);
        $followUpRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

// If database returns results, use them instead of session data
if (!empty($followUpRecords)) {
    $follow_up_dates = array_column($followUpRecords, 'FollowUpDate');
    $follow_up_notes = array_column($followUpRecords, 'FollowUpNotes');
}

// If database returns results, use them instead of session data
if (!empty($followUpRecords)) {
    $_SESSION['form_data']['follow_up_dates'] = array_column($followUpRecords, 'FollowUpDate');
    $_SESSION['form_data']['follow_up_notes'] = array_column($followUpRecords, 'FollowUpNotes');
}

// Ensure session data exists before using
if (!isset($_SESSION['form_data']['follow_up_dates'])) {
    $_SESSION['form_data']['follow_up_dates'] = [''];
}
if (!isset($_SESSION['form_data']['follow_up_notes'])) {
    $_SESSION['form_data']['follow_up_notes'] = [''];
}

$follow_up_dates = $_SESSION['form_data']['follow_up_dates'];
$follow_up_notes = $_SESSION['form_data']['follow_up_notes'];
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
    <link rel="stylesheet" href="../assets/css/patient_records.css">
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
        <button class="btn" onclick="goBack()">Back</button>
        <h1 style="text-align: center; font-size: 45px;">Patient Record</h1>
        
        <form class="staff-form" method="POST">
        <h2>General Details</h2>
            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="Name"><b>Pet Name:</b></label>
                        <input type="text" id="Name" name="Name" 
                            value="<?= htmlspecialchars($appointmentDetails['PetName'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                
                    <div class="input-container">
                        <label for="Weight"><b>Weight (kg):</b></label>
                        <input type="number" id="Weight" name="Weight" step="0.1"
                            value="<?= htmlspecialchars($appointmentDetails['Weight'] ?? 'N/A'); ?>" readonly>
                    </div>

                    <div class="input-container">
                        <label for="Temperature"><b>Temperature (C):</b></label>
                        <input type="number" id="Temperature" name="Temperature" step="0.1"
                            value="<?= htmlspecialchars($appointmentDetails['Temperature'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="appointment_date"><b>Appointment Date:</b></label>
                        <input type="text" id="appointment_date" name="appointment_date" 
                                value="<?= htmlspecialchars($appointmentDetails['AppointmentDate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                
                    <div class="input-container">
                        <label for="service"><b>Service:</b></label>
                        <input type="text" id="service" name="service" 
                            value="<?= htmlspecialchars($appointmentDetails['ServiceName'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                </div>
            </div>
        </form>



        <form class="staff-form" action="../src/chief_complaint_process.php" method="POST" novalidate>
        <h2>Chief Complaint</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">
            <div class="form-row">
                    <div class="input-container">
                        <label for="chief_complaint"><b>Primary Concern:<span class="required">*</span></b></label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="3" maxlength="300"
                            placeholder="Describe the main reason for today's visit..."
                            required><?= htmlspecialchars($patientRecord['ChiefComplaint'] ?? '', ENT_QUOTES); ?></textarea>
                        <small id="char-count">0 / 300 characters</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="onset"><b>Onset of Symptoms:</b>
                            <span class="tooltip">
                                <span class="tooltip-icon">?</span>
                                <span class="tooltip-text">Select the date when the symptoms first appeared.</span>
                            </span>
                        </label>
                        <input type="date" id="onset" name="onset" max="<?= date('Y-m-d'); ?>" 
                            value="<?= htmlspecialchars($patientRecord['OnsetDate'] ?? '', ENT_QUOTES); ?>">
                    </div>
                
                    <div class="input-container">
                        <label for="duration"><b>Duration (Days):</b></label>
                        <select id="duration" name="duration" onchange="toggleCustomDuration(this)">
                            <option value="">Select Duration</option>
                            <option value="1" <?= ($patientRecord['DurationDays'] ?? '') == '1' ? 'selected' : ''; ?>>1 Day</option>
                            <option value="2" <?= ($patientRecord['DurationDays'] ?? '') == '2' ? 'selected' : ''; ?>>2 Days</option>
                            <option value="3" <?= ($patientRecord['DurationDays'] ?? '') == '3' ? 'selected' : ''; ?>>3 Days</option>
                            <option value="5" <?= ($patientRecord['DurationDays'] ?? '') == '5' ? 'selected' : ''; ?>>5 Days</option>
                            <option value="7" <?= ($patientRecord['DurationDays'] ?? '') == '7' ? 'selected' : ''; ?>>1 Week</option>
                            <option value="14" <?= ($patientRecord['DurationDays'] ?? '') == '14' ? 'selected' : ''; ?>>2 Weeks</option>
                            <option value="30" <?= ($patientRecord['DurationDays'] ?? '') == '30' ? 'selected' : ''; ?>>1 Month</option>
                            <option value="Other" <?= (!in_array($patientRecord['DurationDays'] ?? '', ['1', '2', '3', '5', '7', '14', '30']) && !empty($patientRecord['DurationDays'])) ? 'selected' : ''; ?>>
                                Other (Specify)
                            </option>
                        </select>

                        <input type="number" id="custom-duration" name="custom_duration" min="1"
                            placeholder="Specify duration (days)" 
                            value="<?= (!in_array($patientRecord['DurationDays'] ?? '', ['1', '2', '3', '5', '7', '14', '30']) && !empty($patientRecord['DurationDays'])) 
                                ? htmlspecialchars($patientRecord['DurationDays'], ENT_QUOTES) 
                                : ''; ?>"
                            style="display: <?= (!in_array($patientRecord['DurationDays'] ?? '', ['1', '2', '3', '5', '7', '14', '30']) && !empty($patientRecord['DurationDays'])) ? 'block' : 'none'; ?>;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Observed Symptoms:</b></label>
                        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                            <?php
                            // List of predefined symptoms
                            $symptoms = ['Vomiting', 'Diarrhea', 'Lethargy', 'Coughing', 'Sneezing'];

                            // Fetch stored symptoms from database
                            $storedSymptoms = isset($patientRecord['ObservedSymptoms']) ? explode(', ', $patientRecord['ObservedSymptoms']) : [];

                            foreach ($symptoms as $symptom) :
                                $checked = in_array($symptom, $storedSymptoms) ? 'checked' : '';
                            ?>
                                <label>
                                    <input type="checkbox" name="symptoms[]" value="<?= $symptom ?>" <?= $checked; ?>>
                                    <?= $symptom ?>
                                </label>
                            <?php endforeach; ?>

                            <!-- "Other" Symptom Input -->
                            <label>
                                <input type="checkbox" id="other_checkbox" name="symptoms[]" value="Other"
                                    <?= in_array('Other', $storedSymptoms) ? 'checked' : ''; ?>
                                    onclick="toggleOtherSymptom()">
                                Other
                            </label>
                            <input type="text" id="other_symptom" name="other_symptom" placeholder="Specify other symptoms"
                                value="<?= htmlspecialchars($patientRecord['OtherSymptom'] ?? '', ENT_QUOTES); ?>"
                                style="display: <?= (!empty($patientRecord['OtherSymptom'])) ? 'block' : 'none'; ?>; 
                                width: 49%; 
                                margin-top: -10px;  /* Moves the field up */
                                position: relative;
                                top: -5px;  /* Alternative fine-tuning */
                                ">
                        </div>
                    </div>
                </div>
                    <div class="form-row">
                        <div class="input-container">
                            <label for="appetite"><b>Appetite:</b></label>
                            <select id="appetite" name="appetite">
                                <option value="">Select Appetite</option>
                                <?php
                                $appetiteOptions = ['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'none' => 'None'];
                                $storedAppetite = $patientRecord['Appetite'] ?? ''; // ✅ Fetched from the patient record
                        
                                foreach ($appetiteOptions as $value => $label) :
                                ?>
                                    <option value="<?= $value ?>" <?= ($storedAppetite == $value) ? 'selected' : ''; ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="input-container">
                            <label for="diet"><b>Diet:</b></label>
                            <select id="diet" name="diet" onchange="toggleDietInput()">
                                <option value="">Select Diet</option>
                                <?php
                                $dietOptions = [
                                    "Dry Dog Food (Kibble)", "Wet Dog Food (Canned Food)", "Semi-Moist Dog Food",
                                    "Fresh or Homemade Diet", "Raw Diet (BARF - Biologically Appropriate Raw Food)",
                                    "Grain-Free Diet", "Specialized Diets", "Treats and Snacks", "Other (Specify)"
                                ];
                                $storedDiet = $patientRecord['Diet'] ?? ''; // ✅ Fetched from the patient record
                        
                                foreach ($dietOptions as $option) :
                                ?>
                                    <option value="<?= $option ?>" <?= ($storedDiet == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        
                            <!-- "Other" Diet Input -->
                            <input type="text" id="custom-diet" name="custom_diet" placeholder="Specify diet"
                                value="<?= ($storedDiet && !in_array($storedDiet, $dietOptions)) ? htmlspecialchars($storedDiet, ENT_QUOTES) : ''; ?>"
                                style="display: <?= ($storedDiet && !in_array($storedDiet, $dietOptions)) ? 'block' : 'none'; ?>;">
                        </div>

                        <div class="input-container">
                            <label for="frequency"><b>Urine Frequency:</b></label>
                            <select id="frequency" name="frequency" onchange="toggleUrineFrequencyInput()">
                                <option value="">Select Urine Frequency</option>
                                <?php
                                $urineFrequencies = ['normal' => 'Normal', 'increased' => 'Increased', 'decreased' => 'Decreased', 'none' => 'No Urination', 'Other' => 'Other (Specify)'];
                                $storedFrequency = $patientRecord['UrineFrequency'] ?? ''; // ✅ Fetched from patient record
                        
                                foreach ($urineFrequencies as $value => $label) :
                                ?>
                                    <option value="<?= $value ?>" <?= ($storedFrequency == $value) ? 'selected' : ''; ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        
                            <!-- "Other" Frequency Input -->
                            <input type="text" id="custom-frequency" name="custom_frequency" placeholder="Specify urine frequency"
                                value="<?= ($storedFrequency && !array_key_exists($storedFrequency, $urineFrequencies)) ? htmlspecialchars($storedFrequency, ENT_QUOTES) : ''; ?>"
                                style="display: <?= ($storedFrequency && !array_key_exists($storedFrequency, $urineFrequencies)) ? 'block' : 'none'; ?>;">
                        </div>

                        <div class="input-container">
                            <label for="color"><b>Urine Color:</b></label>
                            <select id="color" name="color" onchange="toggleUrineColorInput()">
                                <option value="">Select Urine Color</option>
                                <?php
                                $urineColors = [
                                    'pale_yellow' => 'Pale Yellow (Normal)',
                                    'dark_yellow' => 'Dark Yellow',
                                    'brown_reddish' => 'Brown/Reddish',
                                    'cloudy' => 'Cloudy',
                                    'Other' => 'Other (Specify)'
                                ];
                                $storedColor = $patientRecord['UrineColor'] ?? ''; // ✅ Using patient record data
                        
                                foreach ($urineColors as $value => $label) :
                                ?>
                                    <option value="<?= $value ?>" <?= ($storedColor == $value) ? 'selected' : ''; ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        
                            <!-- "Other" Color Input -->
                            <input type="text" id="custom-color" name="custom_color" placeholder="Specify urine color"
                                value="<?= ($storedColor && !array_key_exists($storedColor, $urineColors)) ? htmlspecialchars($storedColor, ENT_QUOTES) : ''; ?>"
                                style="display: <?= ($storedColor && !array_key_exists($storedColor, $urineColors)) ? 'block' : 'none'; ?>;">
                        </div>
                    </div>
                    <div class="form-row">
                        <!-- Water Intake -->
                        <div class="input-container">
                            <label for="water_intake"><b>Water Intake:</b></label>
                            <select id="water_intake" name="water_intake">
                                <option value="">Select Water Intake</option>
                                <?php
                                $waterIntakeOptions = ['normal' => 'Normal', 'increased' => 'Increased', 'decreased' => 'Decreased', 'none' => 'Not Drinking'];
                                $storedWaterIntake = $patientRecord['WaterIntake'] ?? ''; // ✅ Using patient record data
                        
                                foreach ($waterIntakeOptions as $value => $label) :
                                ?>
                                    <option value="<?= $value ?>" <?= ($storedWaterIntake == $value) ? 'selected' : ''; ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Environment -->
                        <div class="input-container">
                            <label for="environment"><b>Environment:</b></label>
                            <select id="environment" name="environment">
                                <option value="">Select Environment</option>
                                <?php
                                $environmentOptions = ['outdoor-exposure' => 'Outdoor Exposure', 'interaction-with-other-pets' => 'Interaction with other pets'];
                                $storedEnvironment = $patientRecord['Environment'] ?? ''; // ✅ Using patient record data
                        
                                foreach ($environmentOptions as $value => $label) :
                                ?>
                                    <option value="<?= $value ?>" <?= ($storedEnvironment == $value) ? 'selected' : ''; ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <!-- Fecal Score -->
                        <div class="input-container" style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px; width: 100%;">
                            <label for="fecal-score"><b>Fecal Score (Bristol):</b>
                                <span class="tooltip">
                                    <span class="tooltip-icon">?</span>
                                    <span class="tooltip-text">1-2: Hard, 3-4: Normal, 5-7: Loose or diarrhea</span>
                                </span>
                            </label>
                        
                            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <?php 
                                $storedFecalScore = $patientRecord['FecalScore'] ?? ''; // ✅ Using patient record data
                                for ($i = 1; $i <= 7; $i++): ?>
                                    <label style="display: flex; align-items: center;">
                                        <input type="radio" name="fecal-score" value="<?= $i ?>" <?= ($storedFecalScore == (string)$i) ? 'checked' : ''; ?>>
                                        <?= $i ?>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Pain Level -->
                        <div class="input-container">
                            <label for="pain_level"><b>Pain Level (1-10):</b></label>
                            
                            <?php 
                                $storedPainLevel = $patientRecord['PainLevel'] ?? '5'; // ✅ Using patient record data with default to 5
                            ?>
                        
                            <input type="range" id="pain_level" name="pain_level" min="1" max="10" step="1" 
                                value="<?= htmlspecialchars($storedPainLevel, ENT_QUOTES, 'UTF-8'); ?>" 
                                oninput="updatePainValue(this.value)">
                        
                            <div id="pain_value_display"><?= htmlspecialchars($storedPainLevel, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <label for="medication"><b>Medication given prior to check-up:</b></label>
                            <textarea id="medication" name="medication" rows="2" maxlength="300"><?= htmlspecialchars($patientRecord['MedicationPriorCheckup'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <small id="medication-char-count">0 / 300 characters</small>
                            <?php if (!empty($errors['medication'])): ?>
                                <span class="error-message" style="color: red;"><?= htmlspecialchars($errors['medication']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="confirm-btn">Save Chief Complaint</button>
                    </div>
        </form>





        <form class="staff-form" action="../src/medical_history_process.php" method="POST">
        <h2>Medical History</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">
            
            <div class="form-row">
                    <div class="input-container">
                        <label for="last_vaccination"><b>Last Vaccination Date:</b></label>
                        <input type="date" id="last_vaccination" name="last_vaccination" 
                            value="<?= htmlspecialchars($medicalHistory['LastVaccinationDate'] ?? '', ENT_QUOTES); ?>">
                    </div>

                    <div class="input-container">
                        <label for="Vaccine"><b>Select Vaccine:</b></label>
                        <select id="Vaccine" name="Vaccine" onchange="toggleVaccineInput(this)">
                            <option value="" <?= empty($medicalHistory['VaccinesGiven']) ? 'selected' : ''; ?>>Select a vaccine</option>
                            <?php 
                            $vaccines = ['Rabies', 'Parvovirus', 'Distemper', 'Hepatitis', 'Bordetella', 'Leptospirosis', 'Other (Specify)'];
                            $selectedVaccine = $medicalHistory['VaccinesGiven'] ?? ''; 
                            foreach ($vaccines as $vaccine) {
                                $selected = (strcasecmp($selectedVaccine, $vaccine) === 0) ? 'selected' : '';
                                echo "<option value=\"$vaccine\" $selected>$vaccine</option>";
                            }
                            ?>
                        </select>
                        <input type="text" id="VaccineInput" name="VaccineInput" placeholder="Specify vaccine"
                            value="<?= htmlspecialchars(($selectedVaccine === 'Other') ? ($medicalHistory['CustomVaccine'] ?? '') : '', ENT_QUOTES); ?>"
                            style="display: <?= ($selectedVaccine === 'Other') ? 'block' : 'none'; ?>;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-container">
                        <label for="last_deworming"><b>Last Deworming Date:</b></label>
                        <input type="date" id="last_deworming" name="last_deworming" 
                            value="<?= htmlspecialchars($medicalHistory['LastDewormingDate'] ?? '', ENT_QUOTES); ?>">
                    </div>

                    <div class="input-container">
                        <label for="Dewormer"><b>Dewormer Used:</b></label>
                        <select id="Dewormer" name="Dewormer" onchange="toggleDewormerInput(this)">
                            <option value="" <?= empty($medicalHistory['DewormerUsed']) ? 'selected' : ''; ?>>Select a dewormer</option>
                            <?php
                            $dewormers = ['Drontal', 'Panacur', 'Strongid', 'Cestex', 'Milbemax', 'Other (Specify)'];
                            $selectedDewormer = $medicalHistory['DewormerUsed'] ?? '';
                            foreach ($dewormers as $dewormer) {
                                $selected = ($selectedDewormer === $dewormer) ? 'selected' : '';
                                echo "<option value=\"$dewormer\" $selected>$dewormer</option>";
                            }
                            ?>
                        </select>
                        <input type="text" id="DewormerInput" name="DewormerInput" placeholder="Specify dewormer"
                            value="<?= htmlspecialchars($medicalHistory['CustomDewormer'] ?? '', ENT_QUOTES); ?>"
                            style="display: <?= ($selectedDewormer === 'Other') ? 'block' : 'none'; ?>;">
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="FleaTickPrevention"><b>Flea & Tick Prevention:</b></label>
                        <input type="text" id="FleaTickPrevention" name="FleaTickPrevention" 
                            placeholder="e.g., NexGard, Frontline"
                            value="<?= htmlspecialchars($medicalHistory['FleaTickPrevention'] ?? '', ENT_QUOTES); ?>">
                    </div>
                    
                    <div class="input-container">
                        <label for="HeartwormPrevention"><b>Heartworm Prevention:</b></label>
                        <input type="text" id="HeartwormPrevention" name="HeartwormPrevention" 
                            placeholder="e.g., Heartgard Plus, Interceptor"
                            value="<?= htmlspecialchars($medicalHistory['HeartwormPrevention'] ?? '', ENT_QUOTES); ?>">
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="GeneticConditions"><b>Genetic Conditions:</b></label>
                        <textarea id="GeneticConditions" name="GeneticConditions" rows="2" placeholder="e.g., Hip dysplasia"><?= htmlspecialchars($medicalHistory['GeneticConditions'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                    
                    <div class="input-container">
                        <label for="FoodAllergies"><b>Food Allergies:</b></label>
                        <textarea id="FoodAllergies" name="FoodAllergies" rows="2" placeholder="e.g., Chicken, Wheat"><?= htmlspecialchars($medicalHistory['FoodAllergies'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="MedicationAllergies"><b>Medication Allergies:</b></label>
                        <textarea id="MedicationAllergies" name="MedicationAllergies" rows="2" placeholder="e.g., Penicillin"><?= htmlspecialchars($medicalHistory['MedicationAllergies'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                    
                    <div class="input-container">
                        <label for="PastIllnesses"><b>Past Illnesses:</b></label>
                        <textarea id="PastIllnesses" name="PastIllnesses" rows="2" placeholder="e.g., Parvovirus in 2023"><?= htmlspecialchars($medicalHistory['PastIllnesses'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="PastSurgeries"><b>Past Surgeries:</b></label>
                        <textarea id="PastSurgeries" name="PastSurgeries" rows="2" placeholder="e.g., Neutering, Tooth extraction"><?= htmlspecialchars($medicalHistory['PastSurgeries'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                    
                    <div class="input-container">
                        <label for="Hospitalizations"><b>Hospitalizations:</b></label>
                        <textarea id="Hospitalizations" name="Hospitalizations" rows="2" placeholder="e.g., Hospitalized for dehydration (2022)"><?= htmlspecialchars($medicalHistory['Hospitalizations'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="CurrentMedications"><b>Current Medications:</b></label>
                        <textarea id="CurrentMedications" name="CurrentMedications" rows="2" placeholder="e.g., Carprofen, Omega-3 supplements"><?= htmlspecialchars($medicalHistory['CurrentMedications'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                
                    <div class="input-container">
                        <label for="BehavioralIssues"><b>Behavioral Issues:</b></label>
                        <textarea id="BehavioralIssues" name="BehavioralIssues" rows="2" maxlength="300"
                            placeholder="e.g., Aggression towards other pets"
                            oninput="updateCharCount('BehavioralIssues', 'BehavioralIssues-char-count')"><?= 
                            htmlspecialchars($medicalHistory['BehavioralIssues'] ?? '', ENT_QUOTES); ?></textarea>
                        <small id="BehavioralIssues-char-count" class="char-counter">0 / 300 characters</small>
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="LastHeatCycle"><b>Last Heat Cycle:</b></label>
                        <input type="date" id="LastHeatCycle" name="LastHeatCycle" 
                            value="<?= htmlspecialchars($medicalHistory['LastHeatCycle'] ?? '', ENT_QUOTES); ?>">
                    </div>
                
                    <div class="input-container">
                        <label for="SpayedNeutered"><b>Spayed/Neutered:</b></label>
                        <select id="SpayedNeutered" name="SpayedNeutered">
                            <option value="" <?= !isset($medicalHistory['SpayedNeutered']) ? 'selected' : ''; ?>>Select</option>
                            <option value="1" <?= (isset($medicalHistory['SpayedNeutered']) && $medicalHistory['SpayedNeutered'] == '1') ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?= (isset($medicalHistory['SpayedNeutered']) && $medicalHistory['SpayedNeutered'] == '0') ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
            
                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Medical History</button>
                </div>
        </form>




        <form class="staff-form" action="../src/physical_exam_process.php" method="POST">
        <h2>Physical Examination</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">
            <h3 style="color: #156f77;">Vital Signs</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="pulse"><b>Pulse (bpm):</b></label>
                    <?php 
                        $storedPulse = $physicalExam['Pulse'] ?? ''; 
                        $pulseOptions = [60, 70, 80, 90, 100];
                        $isCustomPulse = !in_array($storedPulse, $pulseOptions) && $storedPulse !== '';
                    ?>
                    <select id="pulse" name="pulse" onchange="toggleInput('pulse', 'pulseInput')">
                        <option value="">Select a pulse</option>
                        <?php foreach ($pulseOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedPulse == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                        <option value="Other" <?= $isCustomPulse ? 'selected' : ''; ?>>Other (Specify)</option>
                    </select>
                    <input type="number" id="pulseInput" name="pulseInput" min="40" max="250"
                        placeholder="Specify pulse" 
                        value="<?= htmlspecialchars($isCustomPulse ? $storedPulse : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomPulse ? 'block' : 'none'; ?>;">
                </div>
                
                <div class="input-container">
                    <label for="heart-rate"><b>Heart Rate (bpm):</b></label>
                    <?php 
                        $storedHeartRate = $physicalExam['HeartRate'] ?? '';
                        $heartRateOptions = [60, 70, 80, 90, 100];
                        $isCustomHeartRate = !in_array($storedHeartRate, $heartRateOptions) && $storedHeartRate !== '';
                    ?>
                    <select id="heart-rate" name="heart-rate" onchange="toggleInput('heart-rate', 'heartRateInput')">
                        <option value="">Select a heart rate</option>
                        <?php foreach ($heartRateOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedHeartRate == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                        <option value="Other" <?= $isCustomHeartRate ? 'selected' : ''; ?>>Other (Specify)</option>
                    </select>
                    <input type="number" id="heartRateInput" name="heartRateInput" step="1" min="40" max="300"
                        placeholder="Specify heart rate"
                        value="<?= htmlspecialchars($isCustomHeartRate ? $storedHeartRate : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomHeartRate ? 'block' : 'none'; ?>;">
                </div>

                <div class="input-container">
                    <label for="respiratory-rate"><b>Respiratory Rate (brpm):</b></label>
                    <?php 
                        $storedRespiratoryRate = $physicalExam['RespiratoryRate'] ?? ''; 
                        $respiratoryOptions = [10, 15, 20, 25, 30];
                        $isCustomRespiratoryRate = !in_array($storedRespiratoryRate, $respiratoryOptions) && $storedRespiratoryRate !== '';
                    ?>
                    <select id="respiratory-rate" name="respiratory-rate" onchange="toggleInput('respiratory-rate', 'respiratoryInput')">
                        <option value="">Select Respiratory Rate</option>
                        <?php foreach ($respiratoryOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedRespiratoryRate == $option) ? 'selected' : ''; ?>>
                                <?= $option ?> brpm
                            </option>
                        <?php endforeach; ?>
                        <option value="Other" <?= $isCustomRespiratoryRate ? 'selected' : ''; ?>>Other (Specify)</option>
                    </select>
                    <input type="number" id="respiratoryInput" name="respiratoryInput" min="10" max="100" step="1"
                        placeholder="Enter custom rate"
                        value="<?= htmlspecialchars($isCustomRespiratoryRate ? $storedRespiratoryRate : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomRespiratoryRate ? 'block' : 'none'; ?>;">
                </div>
            </div>

            <h3 style="color: #156f77;">Heart & Lung Sounds</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="heart-sound"><b>Heart Sound:</b></label>
                    <?php 
                        $storedHeartSound = $physicalExam['HeartSound'] ?? ''; 
                        $heartSoundOptions = [
                            "normal" => "Normal",
                            "murmur" => "Murmur",
                            "arrhythmia" => "Arrhythmia",
                            "gallop" => "Gallop Rhythm",
                            "Other" => "Other (Specify)"
                        ];
                        $isCustomHeartSound = !in_array($storedHeartSound, array_keys($heartSoundOptions)) && $storedHeartSound !== '';
                    ?>
                    <select id="heart-sound" name="heart-sound" onchange="toggleInput('heart-sound', 'heartSoundInput')">
                        <option value="">Select Heart Sound</option>
                        <?php foreach ($heartSoundOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($storedHeartSound === $value) ? 'selected' : ''; ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
            
                    <input type="text" id="heartSoundInput" name="heartSoundInput"
                        placeholder="Specify heart sound"
                        value="<?= htmlspecialchars($isCustomHeartSound ? $storedHeartSound : '', ENT_QUOTES); ?>"
                        style="display: <?= ($isCustomHeartSound || $storedHeartSound === 'Other') ? 'block' : 'none'; ?>;">
                </div>


                <div class="input-container">
                    <label for="lung-sound"><b>Lung Sound:</b></label>
                    <?php 
                        $storedLungSound = $physicalExam['LungSound'] ?? ''; 
                        $lungSoundOptions = [
                            "normal" => "Normal",
                            "wheezing" => "Wheezing",
                            "crackles" => "Crackles",
                            "stridor" => "Stridor",
                            "Other" => "Other (Specify)"
                        ];
                        $isCustomLungSound = !in_array($storedLungSound, array_keys($lungSoundOptions)) && $storedLungSound !== '';
                    ?>
                    <select id="lung-sound" name="lung-sound" onchange="toggleInput('lung-sound', 'lungSoundInput')">
                        <option value="">Select Lung Sound</option>
                        <?php foreach ($lungSoundOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($storedLungSound === $value) ? 'selected' : ''; ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
            
                    <input type="text" id="lungSoundInput" name="lungSoundInput"
                        placeholder="Specify lung sound"
                        value="<?= htmlspecialchars($isCustomLungSound ? $storedLungSound : '', ENT_QUOTES); ?>"
                        style="display: <?= ($isCustomLungSound || $storedLungSound === 'Other') ? 'block' : 'none'; ?>;">
                </div>
            </div>

            <h3 style="color: #156f77;">Mucous Membrane & CRT</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="mucous-membrane"><b>Mucous Membrane:</b></label>
                    <?php 
                        $storedMucousMembrane = $physicalExam['MucousMembrane'] ?? ''; 
                        $mucousOptions = [
                            "normal"    => "Normal (Pink & Moist)", 
                            "pale"      => "Pale", 
                            "cyanotic"  => "Cyanotic (Blue)", 
                            "icteric"   => "Icteric (Yellow)", 
                            "brick-red" => "Brick Red", 
                            "Other"     => "Other (Specify)"
                        ];
                        $isCustomMucous = !in_array($storedMucousMembrane, array_keys($mucousOptions)) && $storedMucousMembrane !== '';
                    ?>
                    <select id="mucous-membrane" name="mucous-membrane" onchange="toggleInput('mucous-membrane', 'mucousMembraneInput')">
                        <option value="">Select Mucous Membrane</option>
                        <?php foreach ($mucousOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($storedMucousMembrane === $value) ? 'selected' : ''; ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                
                    <input type="text" id="mucousMembraneInput" name="mucousMembraneInput"
                        placeholder="Specify color"
                        value="<?= htmlspecialchars($isCustomMucous ? $storedMucousMembrane : '', ENT_QUOTES); ?>"
                        style="display: <?= ($isCustomMucous || $storedMucousMembrane === 'Other') ? 'block' : 'none'; ?>;">
                </div>
                <div class="input-container">
                    <label for="capillary-refill-time"><b>Capillary Refill Time (sec):</b></label>
                    <?php 
                        $storedCRT = $physicalExam['CapillaryRefillTime'] ?? ''; 
                        $crtOptions = [
                            "0.5"  => "0.5 sec", 
                            "1"    => "1 sec", 
                            "1.5"  => "1.5 sec", 
                            "2"    => "2 sec (Normal)", 
                            "3"    => "3 sec (Delayed)", 
                            "4"    => "4 sec (Severe delay)", 
                            "Other"=> "Other (Specify)"
                        ];
                        $isCustomCRT = !in_array($storedCRT, array_keys($crtOptions)) && $storedCRT !== '';
                    ?>
                    <select id="capillary-refill-time" name="capillary-refill-time" onchange="toggleInput('capillary-refill-time', 'crtInput')">
                        <option value="">Select CRT</option>
                        <?php foreach ($crtOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($storedCRT === $value) ? 'selected' : ''; ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                
                    <input type="number" id="crtInput" name="crtInput" placeholder="Enter custom CRT"
                        step="0.1" min="0" max="5"
                        value="<?= htmlspecialchars($isCustomCRT ? $storedCRT : '', ENT_QUOTES); ?>"
                        style="display: <?= ($isCustomCRT || $storedCRT === 'Other') ? 'block' : 'none'; ?>;">
                </div>
            </div>

            <h3 style="color: #156f77;">Head & Sensory Functions</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="eyes"><b>Eyes:</b></label>
                    <?php 
                        $storedEyes = $physicalExam['Eyes'] ?? '';
                        $eyeOptions = ["Normal", "Redness", "Discharge", "Cloudiness", "Other (Specify)"];
                        $isCustomEyes = !in_array($storedEyes, $eyeOptions) && $storedEyes !== '';
                    ?>
                    <select id="eyes" name="eyes" onchange="toggleInput('eyes', 'eyesInput')">
                        <option value="">Select Eye Condition</option>
                        <?php foreach ($eyeOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedEyes == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="eyesInput" name="eyesInput"
                        placeholder="Specify eye condition"
                        value="<?= htmlspecialchars($isCustomEyes ? $storedEyes : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomEyes ? 'block' : 'none'; ?>;">
                </div>

                <div class="input-container">
                    <label for="ears"><b>Ears:</b></label>
                    <?php 
                        $storedEars = $physicalExam['Ears'] ?? '';
                        $earOptions = ["Normal", "Discharge", "Odor", "Swelling", "Other (Specify)"];
                        $isCustomEars = !in_array($storedEars, $earOptions) && $storedEars !== '';
                    ?>
                    <select id="ears" name="ears" onchange="toggleInput('ears', 'earsInput')">
                        <option value="">Select Ear Condition</option>
                        <?php foreach ($earOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedEars == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="earsInput" name="earsInput"
                        placeholder="Specify ear condition"
                        value="<?= htmlspecialchars($isCustomEars ? $storedEars : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomEars ? 'block' : 'none'; ?>;">
                </div>
            </div>

            <h3 style="color: #156f77;">Tracheal & Abdominal Palpation</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="tracheal-pinch"><b>Tracheal Pinch:</b></label>
                    <?php 
                        $storedTrachealPinch = $physicalExam['TrachealPinch'] ?? '';
                        $trachealOptions = ["Positive", "Negative"];
                    ?>
                    <select id="tracheal-pinch" name="tracheal-pinch">
                        <option value="">Select Tracheal Pinch</option>
                        <?php foreach ($trachealOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedTrachealPinch == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-container">
                    <label for="abdominal-palpation"><b>Abdominal Palpation:</b></label>
                    <?php 
                        $storedAbdominalPalpation = $physicalExam['AbdominalPalpation'] ?? '';
                        $abdominalOptions = ["Normal", "Painful", "Firm", "Distended", "Other (Specify)"];
                        $isCustomAbdominal = !in_array($storedAbdominalPalpation, $abdominalOptions) && $storedAbdominalPalpation !== '';
                    ?>
                    <select id="abdominal-palpation" name="abdominal-palpation" onchange="toggleInput('abdominal-palpation', 'abdominalInput')">
                        <option value="">Select Abdominal Palpation</option>
                        <?php foreach ($abdominalOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedAbdominalPalpation == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="abdominalInput" name="abdominalInput"
                        placeholder="Specify abdominal palpation"
                        value="<?= htmlspecialchars($isCustomAbdominal ? $storedAbdominalPalpation : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomAbdominal ? 'block' : 'none'; ?>;">
                </div>
            </div>

            <h3 style="color: #156f77;">Lymph Nodes & BCS</h3>
            <div class="form-row">
                <div class="input-container">
                    <label for="ln"><b>Lymph Nodes (LN):</b></label>
                    <?php 
                        $storedLN = $physicalExam['LN'] ?? '';
                        $lnOptions = ["Normal", "Enlarged", "Painful", "Other (Specify)"];
                        $isCustomLN = !in_array($storedLN, $lnOptions) && $storedLN !== '';
                    ?>
                    <select id="ln" name="ln" onchange="toggleInput('ln', 'lnInput')">
                        <option value="">Select LN Condition</option>
                        <?php foreach ($lnOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedLN == $option) ? 'selected' : ''; ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="lnInput" name="lnInput"
                        placeholder="Specify LN condition"
                        value="<?= htmlspecialchars($isCustomLN ? $storedLN : '', ENT_QUOTES); ?>"
                        style="display: <?= $isCustomLN ? 'block' : 'none'; ?>;">
                </div>

                <div class="input-container">
                    <label for="bcs"><b>Body Condition Score (BCS):</b></label>
                    <?php 
                        $storedBCS = $physicalExam['BCS'] ?? '';
                        $bcsOptions = [1, 2, 3, 4, 5, 6, 7, 8, 9];
                    ?>
                    <select id="bcs" name="bcs">
                        <option value="">Select BCS</option>
                        <?php foreach ($bcsOptions as $option): ?>
                            <option value="<?= $option ?>" <?= ($storedBCS == $option) ? 'selected' : ''; ?>><?= $option ?>/9</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="confirm-btn">Save Physical Exam</button>
            </div>
        </form>





        <form class="staff-form" action="../src/laboratory_test_process.php" method="POST" enctype="multipart/form-data">
        <h2>Laboratory Examination</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

                <h3 style="color: #156f77;">Blood Tests</h3>
                <div class="form-row">
                    <div class="form-group">
                        <?php
                        // Ensure $storedTests is always an array
                        $storedTests = $_SESSION['lab_exam_data']['lab-tests'] ?? [];
                        if (!is_array($storedTests)) {
                            $storedTests = [];
                        }

                        $bloodTests = ["CBC", "Blood Chemistry", "Blood Smear", "Other Blood Test"];
                        
                        foreach ($bloodTests as $test) {
                            $isChecked = in_array($test, $storedTests);
                            $fileInputId = str_replace(" ", "-", strtolower($test)) . "-file";
                            $customInputId = str_replace(" ", "-", strtolower($test)) . "-detail";
                            $testDetail = $_SESSION['lab_exam_data'][$test . '-detail'] ?? '';

                            // Ensure uploaded files array is set
                            $uploadedFile = $uploadedFiles[$test] ?? null;
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="lab-tests[]" value="<?= $test ?>" <?= $isChecked ? 'checked' : ''; ?>
                                    onchange="toggleFileUpload(this, '<?= $fileInputId ?>', '<?= $customInputId ?>')">
                                <?= $test ?>
                            </label>

                            <?php if (!empty($uploadedFile) && file_exists("../uploads/lab_tests/$uploadedFile")): ?>
                                <p>📂 <a href="../uploads/lab_tests/<?= htmlspecialchars($uploadedFile); ?>" target="_blank">
                                    View Uploaded File</a></p>
                                <input type="hidden" name="existing-files[<?= $test ?>]" value="<?= htmlspecialchars($uploadedFile); ?>">
                            <?php else: ?>
                                <input type="file" id="<?= $fileInputId ?>" name="lab-results[<?= $test ?>]" 
                                    style="display: <?= $isChecked ? 'block' : 'none'; ?>;">
                            <?php endif; ?>

                            <?php if ($test === "Other Blood Test"): ?>
                                <input type="text" id="<?= $customInputId ?>" name="blood-test-detail"
                                    placeholder="Specify other blood test"
                                    value="<?= htmlspecialchars($testDetail, ENT_QUOTES); ?>"
                                    style="display: <?= $isChecked ? 'block' : 'none'; ?>;">
                            <?php endif; ?>
                        <?php } ?>
                    </div>
                </div>

                <h3 style="color: #156f77;">Imaging</h3>
                <div class="form-row">
                    <div class="form-group">
                        <?php
                        $imagingTests = ["X-ray", "Ultrasound", "CT Scan", "Other Imaging"];
                        foreach ($imagingTests as $test) {
                            $checked = in_array($test, $storedTests) ? 'checked' : '';
                            $fileInputId = str_replace(" ", "-", strtolower($test)) . "-file";
                            $customInputId = str_replace(" ", "-", strtolower($test)) . "-detail";
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="lab-tests[]" value="<?= $test ?>" <?= $checked ?>
                                    onchange="toggleFileUpload(this, '<?= $fileInputId ?>', '<?= $customInputId ?>')">
                                <?= $test ?>
                            </label>

                            <!-- File Upload Field -->
                            <input type="file" id="<?= $fileInputId ?>" name="lab-results[]" 
                                style="display: <?= $checked ? 'block' : 'none'; ?>;">

                            <!-- Custom Input for 'Other Imaging' -->
                            <?php if ($test === "Other Imaging"): ?>
                                <input type="text" id="<?= $customInputId ?>" name="imaging-other-detail"
                                    placeholder="Specify other imaging"
                                    value="<?= htmlspecialchars($_SESSION['lab_exam_data']['imaging-other-detail'] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= in_array('Other Imaging', $storedTests) ? 'block' : 'none'; ?>;">
                            <?php endif; ?>
                        <?php } ?>
                    </div>
                </div>

                <h3 style="color: #156f77;">Microbiology</h3>
                <div class="form-row">
                    <div class="form-group">
                        <?php
                        $microbiologyTests = ["Ear Swab", "Skin Scrape", "Fungal Test", "Other Microbiology"];
                        foreach ($microbiologyTests as $test) {
                            $checked = in_array($test, $storedTests) ? 'checked' : '';
                            $fileInputId = str_replace(" ", "-", strtolower($test)) . "-file";
                            $customInputId = str_replace(" ", "-", strtolower($test)) . "-detail";
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="lab-tests[]" value="<?= $test ?>" <?= $checked ?>
                                    onchange="toggleFileUpload(this, '<?= $fileInputId ?>', '<?= $customInputId ?>')">
                                <?= $test ?>
                            </label>

                            <!-- File Upload Field -->
                            <input type="file" id="<?= $fileInputId ?>" name="lab-results[]" 
                                style="display: <?= $checked ? 'block' : 'none'; ?>;">

                            <!-- Custom Input for 'Other Microbiology' -->
                            <?php if ($test === "Other Microbiology"): ?>
                                <input type="text" id="<?= $customInputId ?>" name="microbiology-other-detail"
                                    placeholder="Specify other microbiology"
                                    value="<?= htmlspecialchars($_SESSION['lab_exam_data']['microbiology-other-detail'] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= in_array('Other Microbiology', $storedTests) ? 'block' : 'none'; ?>;">
                            <?php endif; ?>
                        <?php } ?>
                    </div>
                </div>
                
                <h3 style="color: #156f77;">Other Tests</h3>
                <div class="form-row">
                    <div class="form-group">
                        <?php
                        $otherTests = ["Otoscope", "Vaginal Smear", "Other"];
                        foreach ($otherTests as $test) {
                            $checked = in_array($test, $storedTests) ? 'checked' : '';
                            $fileInputId = str_replace(" ", "-", strtolower($test)) . "-file";
                            $customInputId = str_replace(" ", "-", strtolower($test)) . "-detail";
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="lab-tests[]" value="<?= $test ?>" <?= $checked ?>
                                    onchange="toggleFileUpload(this, '<?= $fileInputId ?>', '<?= $customInputId ?>')">
                                <?= $test ?>
                            </label>

                            <!-- File Upload Field -->
                            <input type="file" id="<?= $fileInputId ?>" name="lab-results[]"
                                style="display: <?= $checked ? 'block' : 'none'; ?>;">

                            <!-- Custom Input for 'Other' -->
                            <?php if ($test === "Other"): ?>
                                <input type="text" id="<?= $customInputId ?>" name="etc-detail"
                                    placeholder="Specify other tests"
                                    value="<?= htmlspecialchars($_SESSION['lab_exam_data']['etc-detail'] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= in_array('Other', $storedTests) ? 'block' : 'none'; ?>;">
                            <?php endif; ?>
                        <?php } ?>
                    </div>
                </div>
                    <div class="form-buttons">
                        <button type="submit" class="confirm-btn">Save Laboratory Exam</button>
                    </div>
        </form>
            




        <form class="staff-form" action="../src/process_diagnosis.php" method="POST">
        <h2>Diagnosis</h2>
        <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? '') ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? '') ?>">

                <?php
                $storedData = $_SESSION['diagnosis_data'] ?? [];
                ?>

                <div class="form-row">
                    <div class="input-container">
                        <label for="diagnosis-type"><b>Is this a Final or Tentative Diagnosis?</b></label>
                        <select id="diagnosis-type" name="diagnosis-type" required>
                            <option value="final" <?= isset($diagnosisData['DiagnosisType']) && $diagnosisData['DiagnosisType'] === 'final' ? 'selected' : ''; ?>>Final Diagnosis</option>
                            <option value="tentative" <?= isset($diagnosisData['DiagnosisType']) && $diagnosisData['DiagnosisType'] === 'tentative' ? 'selected' : ''; ?>>Tentative Diagnosis</option>
                        </select>
                    </div>
            
                    <div class="input-container">
                        <label for="prognosis"><b>Prognosis:</b></label>
                        <select id="prognosis" name="prognosis">
                            <option value="">Select Prognosis</option>
                            <?php
                            $prognosisOptions = ['Excellent', 'Good', 'Fair', 'Poor', 'Questionable', 'Grave'];
                            foreach ($prognosisOptions as $option) {
                                $selected = isset($diagnosisData['Prognosis']) && $diagnosisData['Prognosis'] === $option ? 'selected' : '';
                                echo "<option value='$option' $selected>$option</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="diagnosis"><b>Diagnosis:</b></label>
                        <textarea id="diagnosis" name="diagnosis" rows="3" placeholder="Enter diagnosis here..." required><?= htmlspecialchars($diagnosisData['Diagnosis'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>

                    <div class="input-container">
                        <label for="treatment"><b>Treatment:</b></label>
                        <textarea id="treatment" name="treatment" rows="3" placeholder="Enter treatment here..." required><?= htmlspecialchars($diagnosisData['Treatment'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Diagnosis</button>
                </div>
        </form>
            




        <form class="staff-form" action="../src/process_medication.php" method="POST">
        <h2>Medications Given</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

            <div class="form-group">
                <label><b>Select Medications Given:</b></label>
                <div style="display: flex; flex-direction: column;">
                    <?php
                    $medications = [
                        "Amoxicillin", "Cefalexin", "Doxycycline", "Metronidazole",
                        "Carprofen", "Meloxicam", "Prednisone", "Vitamin B12",
                        "Omega-3 Fatty Acids", "Calcium Supplements", "Other"
                    ];
                    
                    foreach ($medications as $medication) :
                        $checked = in_array($medication, $selectedMedications) ? 'checked' : '';
                    ?>
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="medications[]" value="<?= $medication ?>" <?= $checked; ?> onclick="toggleOtherMedication()">
                            <?= $medication ?>
                        </label>
                    <?php endforeach; ?>

                    <!-- "Other" Option with Custom Input -->
                    <input type="text" id="other_medication" name="other_medication" placeholder="Specify other medication"
                        value="<?= htmlspecialchars($patientRecord['OtherMedication'] ?? '', ENT_QUOTES); ?>"
                        style="display: <?= (!empty($patientRecord['OtherMedication'])) ? 'block' : 'none'; ?>; width: 49%;">
                </div>
            </div>

                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Medications</button>
                </div>
        </form>





        <form class="staff-form" action="../src/process_prescribe_medication.php" method="POST">
        <h2>Prescribe Medication</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

            <div id="medications-container">
                    <?php
                    // Load stored session data for user input retention
                    $storedMedications = $_SESSION['medication_data']['medications'] ?? [];
                    $storedDosages = $_SESSION['medication_data']['dosages'] ?? [];
                    $storedDurations = $_SESSION['medication_data']['durations'] ?? [];

                    if (empty($storedMedications)) {
                        $storedMedications[] = "";
                        $storedDosages[] = "";
                        $storedDurations[] = "";
                    }

                    foreach ($storedMedications as $index => $medication) :
                    ?>
                        <div class="form-row medication-item">
                            <div class="input-container">
                                <label><b>Medication:</b></label>
                                <select name="medications[]" class="medication-select" onchange="toggleCustomMedication(this)">
                                    <option value="">Select Medication</option>
                                    <optgroup label="Antibiotics">
                                        <option value="Amoxicillin" <?= ($medication == 'Amoxicillin') ? 'selected' : ''; ?>>Amoxicillin</option>
                                        <option value="Cefalexin" <?= ($medication == 'Cefalexin') ? 'selected' : ''; ?>>Cefalexin</option>
                                        <option value="Doxycycline" <?= ($medication == 'Doxycycline') ? 'selected' : ''; ?>>Doxycycline</option>
                                        <option value="Enrofloxacin" <?= ($medication == 'Enrofloxacin') ? 'selected' : ''; ?>>Enrofloxacin (Baytril)</option>
                                        <option value="Cefovecin" <?= ($medication == 'Cefovecin') ? 'selected' : ''; ?>>Cefovecin (Convenia)</option>
                                    </optgroup>
                                    <optgroup label="Pain Relievers & Anti-Inflammatories">
                                        <option value="Carprofen" <?= ($medication == 'Carprofen') ? 'selected' : ''; ?>>Carprofen (Rimadyl)</option>
                                        <option value="Meloxicam" <?= ($medication == 'Meloxicam') ? 'selected' : ''; ?>>Meloxicam (Metacam)</option>
                                        <option value="Gabapentin" <?= ($medication == 'Gabapentin') ? 'selected' : ''; ?>>Gabapentin</option>
                                        <option value="Tramadol" <?= ($medication == 'Tramadol') ? 'selected' : ''; ?>>Tramadol</option>
                                    </optgroup>
                                    <optgroup label="Vitamins & Supplements">
                                        <option value="Fish Oil" <?= ($medication == 'Fish Oil') ? 'selected' : ''; ?>>Fish Oil (Omega-3)</option>
                                        <option value="Glucosamine" <?= ($medication == 'Glucosamine') ? 'selected' : ''; ?>>Glucosamine & Chondroitin</option>
                                        <option value="Probiotics" <?= ($medication == 'Probiotics') ? 'selected' : ''; ?>>Probiotics</option>
                                        <option value="Vitamin B12 Injection" <?= ($medication == 'Vitamin B12 Injection') ? 'selected' : ''; ?>>Vitamin B12 Injection</option>
                                    </optgroup>
                                    <option value="Other" <?= ($medication == 'Other') ? 'selected' : ''; ?>>Other (Specify)</option>
                                </select>
                                <input type="text" name="custom-medications[]" placeholder="Specify medication" class="custom-medication"
                                    value="<?= htmlspecialchars($storedCustomMedications[$index] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= ($medication == 'Other') ? 'block' : 'none'; ?>;">
                            </div>

                            <div class="input-container">
                                <label><b>Dosage:</b></label>
                                <select name="dosages[]" class="dosage-select" onchange="toggleCustomDosage(this)">
                                    <option value="">Select Dosage</option>
                                    <optgroup label="Liquid (mL)">
                                        <option value="0.5 mL" <?= (($storedDosages[$index] ?? '') == '0.5 mL') ? 'selected' : ''; ?>>0.5 mL</option>
                                        <option value="1 mL" <?= (($storedDosages[$index] ?? '') == '1 mL') ? 'selected' : ''; ?>>1 mL</option>
                                        <option value="2 mL" <?= (($storedDosages[$index] ?? '') == '2 mL') ? 'selected' : ''; ?>>2 mL</option>
                                        <option value="5 mL" <?= (($storedDosages[$index] ?? '') == '5 mL') ? 'selected' : ''; ?>>5 mL</option>
                                    </optgroup>
                                    <optgroup label="Tablet (mg)">
                                        <option value="25 mg" <?= (($storedDosages[$index] ?? '') == '25 mg') ? 'selected' : ''; ?>>25 mg</option>
                                        <option value="50 mg" <?= (($storedDosages[$index] ?? '') == '50 mg') ? 'selected' : ''; ?>>50 mg</option>
                                        <option value="100 mg" <?= (($storedDosages[$index] ?? '') == '100 mg') ? 'selected' : ''; ?>>100 mg</option>
                                        <option value="200 mg" <?= (($storedDosages[$index] ?? '') == '200 mg') ? 'selected' : ''; ?>>200 mg</option>
                                    </optgroup>
                                    <optgroup label="Injection (IU)">
                                        <option value="1 IU" <?= (($storedDosages[$index] ?? '') == '1 IU') ? 'selected' : ''; ?>>1 IU</option>
                                        <option value="2 IU" <?= (($storedDosages[$index] ?? '') == '2 IU') ? 'selected' : ''; ?>>2 IU</option>
                                        <option value="5 IU" <?= (($storedDosages[$index] ?? '') == '5 IU') ? 'selected' : ''; ?>>5 IU</option>
                                    </optgroup>
                                    <option value="Other" <?= (($storedDosages[$index] ?? '') == 'Other') ? 'selected' : ''; ?>>Other (Specify)</option>
                                </select>
                                <input type="text" name="custom-dosages[]" placeholder="Specify dosage" class="custom-dosage"
                                    value="<?= htmlspecialchars($storedCustomDosages[$index] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= (($storedDosages[$index] ?? '') == 'Other') ? 'block' : 'none'; ?>;">
                            </div>

                            <div class="input-container">
                                <label><b>Duration:</b></label>
                                <select name="durations[]" class="duration-select" onchange="toggleCustomDuration(this)">
                                    <option value="">Select Duration</option>
                                    <option value="3 Days" <?= (($storedDurations[$index] ?? '') == '3 Days') ? 'selected' : ''; ?>>3 Days</option>
                                    <option value="5 Days" <?= (($storedDurations[$index] ?? '') == '5 Days') ? 'selected' : ''; ?>>5 Days</option>
                                    <option value="7 Days" <?= (($storedDurations[$index] ?? '') == '7 Days') ? 'selected' : ''; ?>>7 Days</option>
                                    <option value="10 Days" <?= (($storedDurations[$index] ?? '') == '10 Days') ? 'selected' : ''; ?>>10 Days</option>
                                    <option value="14 Days" <?= (($storedDurations[$index] ?? '') == '14 Days') ? 'selected' : ''; ?>>14 Days</option>
                                    <option value="30 Days" <?= (($storedDurations[$index] ?? '') == '30 Days') ? 'selected' : ''; ?>>30 Days</option>
                                    <option value="Ongoing" <?= (($storedDurations[$index] ?? '') == 'Ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="Other" <?= (($storedDurations[$index] ?? '') == 'Other') ? 'selected' : ''; ?>>Other (Specify)</option>
                                </select>
                                <input type="text" name="custom-durations[]" placeholder="Specify duration" class="custom-duration"
                                    value="<?= htmlspecialchars($storedCustomDurations[$index] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= (($storedDurations[$index] ?? '') == 'Other') ? 'block' : 'none'; ?>;">
                                </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-buttons">
                    <button type="button" id="add-medication" style="margin-bottom: 10px;">Add Medication</button>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Prescription</button>
                </div>
        </form>





        <form class="staff-form" action="../src/process_followup.php" method="POST">
        <h2>Follow-Up Schedule</h2>
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

            <div id="followup-container">
                    <?php foreach ($follow_up_dates as $index => $date): ?>
                        <div class="form-row followup-item">
                            <div class="input-container">
                                <label for="follow-up-date-<?= $index + 1 ?>"><b>Follow-Up Date:</b></label>
                                <input type="date" id="follow-up-date-<?= $index + 1 ?>" 
                                    name="follow_up_dates[]" 
                                    value="<?= htmlspecialchars($date, ENT_QUOTES); ?>" 
                                    >
                            </div>
                            <div class="input-container">
                                <label for="follow-up-notes-<?= $index + 1 ?>"><b>Notes:</b></label>
                                <textarea id="follow-up-notes-<?= $index + 1 ?>" 
                                        name="follow_up_notes[]" 
                                        rows="3"
                                        placeholder="Enter follow-up notes"><?= htmlspecialchars($follow_up_notes[$index], ENT_QUOTES); ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Add Follow-Up</button>
                </div> -->
                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Follow-Up</button>
                </div>
            
        </form>

            <form class="staff-form" action="../src/finish_consultation.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? '') ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? '') ?>">
                    <div class="form-buttons">
                        <button type="submit" class="confirm-btn" style="font-size: 18px;" onclick="confirmFinishConsultation(event)">Finish Consultation</button>
                    </div>
            </form>
<script>
    document.querySelector("form").addEventListener("submit", function (event) {
    console.log("Form submitted!");
});
</script>
        <script>
                function toggleFileUpload(checkbox, fileInputId, customInputId = null) {
                    document.getElementById(fileInputId).style.display = checkbox.checked ? 'block' : 'none';
                    
                    // Show custom input field if 'Other Imaging' is selected
                    if (customInputId) {
                        document.getElementById(customInputId).style.display = checkbox.checked ? 'block' : 'none';
                    }
                }
                document.addEventListener("DOMContentLoaded", function () {
                    // Transfer PHP session messages to sessionStorage before they are cleared
                    <?php if (isset($_SESSION['success_medical_history'])): ?>
                        sessionStorage.setItem("success_medical_history", "<?= $_SESSION['success_medical_history']; ?>");
                        <?php unset($_SESSION['success_medical_history']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success_chief_complaint'])): ?>
                        sessionStorage.setItem("success_chief_complaint", "<?= $_SESSION['success_chief_complaint']; ?>");
                        <?php unset($_SESSION['success_chief_complaint']); ?>
                    <?php endif; ?>
                });
                </script>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const medicationsContainer = document.getElementById('medications-container');
                const addMedicationButton = document.getElementById('add-medication');

                addMedicationButton?.addEventListener('click', function () {
                    const medicationCount = medicationsContainer.children.length + 1;

                    const medicationItem = document.createElement('div');
                    medicationItem.classList.add('form-row', 'medication-item');
                    medicationItem.innerHTML = `
                        <div class="input-container">
                            <label for="medication-${medicationCount}"><b>Medication:</b></label>
                            <select id="medication-${medicationCount}" name="medications[]" onchange="toggleCustomInput(this, 'custom-medication-${medicationCount}')">
                                <option value="">Select Medication</option>
                                <optgroup label="Antibiotics">
                                    <option value="Amoxicillin">Amoxicillin</option>
                                    <option value="Cefalexin">Cefalexin</option>
                                    <option value="Doxycycline">Doxycycline</option>
                                    <option value="Metronidazole">Metronidazole</option>
                                </optgroup>
                                <optgroup label="Anti-Inflammatories">
                                    <option value="Carprofen">Carprofen</option>
                                    <option value="Meloxicam">Meloxicam</option>
                                    <option value="Prednisone">Prednisone</option>
                                </optgroup>
                                <optgroup label="Vitamins & Supplements">
                                    <option value="Vitamin B12">Vitamin B12</option>
                                    <option value="Omega-3">Omega-3 Fatty Acids</option>
                                    <option value="Calcium Supplements">Calcium Supplements</option>
                                </optgroup>
                                <optgroup label="Supportive Care">
                                    <option value="Fluid Therapy">Fluid Therapy</option>
                                    <option value="Oxygen Therapy">Oxygen Therapy</option>
                                </optgroup>
                                <option value="Other">Other (Specify)</option>
                            </select>
                            <input type="text" id="custom-medication-${medicationCount}" name="custom-medications[]" placeholder="Specify medication" style="display: none;">
                        </div>

                        <div class="input-container">
                            <label for="dosage-${medicationCount}"><b>Dosage:</b></label>
                            <select id="dosage-${medicationCount}" name="dosages[]" onchange="toggleCustomInput(this, 'custom-dosage-${medicationCount}')">
                                <option value="">Select Dosage</option>
                                <optgroup label="Liquid (mL)">
                                    <option value="0.5 mL">0.5 mL</option>
                                    <option value="1 mL">1 mL</option>
                                    <option value="2 mL">2 mL</option>
                                    <option value="5 mL">5 mL</option>
                                </optgroup>
                                <optgroup label="Tablet (mg)">
                                    <option value="50 mg">50 mg</option>
                                    <option value="100 mg">100 mg</option>
                                    <option value="250 mg">250 mg</option>
                                    <option value="500 mg">500 mg</option>
                                </optgroup>
                                <optgroup label="Injectable">
                                    <option value="0.1 mL/kg">0.1 mL/kg</option>
                                    <option value="0.2 mL/kg">0.2 mL/kg</option>
                                </optgroup>
                                <option value="Other">Other (Specify)</option>
                            </select>
                            <input type="text" id="custom-dosage-${medicationCount}" name="custom-dosages[]" placeholder="Specify dosage" style="display: none;">
                        </div>

                        <div class="input-container">
                            <label for="duration-${medicationCount}"><b>Duration:</b></label>
                            <select id="duration-${medicationCount}" name="durations[]" onchange="toggleCustomInput(this, 'custom-duration-${medicationCount}')">
                                <option value="">Select Duration</option>
                                <option value="1 Day">1 Day</option>
                                <option value="3 Days">3 Days</option>
                                <option value="5 Days">5 Days</option>
                                <option value="7 Days">7 Days</option>
                                <option value="10 Days">10 Days</option>
                                <option value="14 Days">14 Days</option>
                                <option value="Other">Other (Specify)</option>
                            </select>
                            <input type="text" id="custom-duration-${medicationCount}" name="custom-durations[]" placeholder="Specify duration" style="display: none;">
                        </div>

                        <button type="button" class="delete-button" onclick="removeMedication(this)">X</button>
                    `;
                    medicationsContainer.appendChild(medicationItem);
                });
            });

            // Function to remove medication fields
            function removeMedication(button) {
                button.parentElement.remove();
            }

            // Function to toggle visibility of custom input fields when "Other" is selected
            function toggleCustomInput(select, inputId) {
                const customInput = document.getElementById(inputId);
                customInput.style.display = select.value === "Other" ? "block" : "none";
            }
            </script>

            <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Function to show or hide "Other" medication input
                function toggleOtherMedication() {
                    const otherCheckbox = document.querySelector('input[name="medications[]"][value="Other"]');
                    const otherInput = document.getElementById("other_medication");
                    
                    if (otherCheckbox && otherInput) {
                        otherInput.style.display = otherCheckbox.checked ? "block" : "none";
                    }
                }

                // Attach event listener to "Other" medication checkbox
                document.querySelectorAll('input[name="medications[]"]').forEach(input => {
                    input.addEventListener("change", toggleOtherMedication);
                });

                // Call function on page load to apply correct visibility
                toggleOtherMedication();
            });

            document.addEventListener("DOMContentLoaded", function () {
                function toggleCustomInput(selectElement) {
                    const customInputId = selectElement.nextElementSibling; // The input field right after the select
                    if (customInputId && customInputId.tagName === "INPUT") {
                        customInputId.style.display = selectElement.value === "Other" ? "block" : "none";
                    }
                }

                // Find all dropdowns for Medication, Dosage, and Duration
                document.querySelectorAll(".medication-select, .dosage-select, .duration-select").forEach(select => {
                    select.addEventListener("change", function () {
                        toggleCustomInput(this);
                    });

                    // Ensure correct visibility on page load
                    toggleCustomInput(select);
                });
            });
            </script>

            


            <script src="../assets/js/patient_records.js?v=1.0.3"></script>
</body>
</html>