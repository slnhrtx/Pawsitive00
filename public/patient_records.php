<?php
// Enable error reporting
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

$appointment_id = $_POST['appointment_id'] ?? $_GET['appointment_id'] ?? null;

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

if ($recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM PhysicalExams
            WHERE RecordId = :record_id
        ");
        $stmt->execute([':record_id' => $recordId]);
        $physicalExam = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
    }
}

$labTests = [];
$recordId = $_GET['record_id'] ?? null;

if ($recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM LaboratoryTests
            WHERE RecordId = :record_id
        ");
        $stmt->execute([':record_id' => $recordId]);
        $labTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
    }
}

$diagnosisData = [];
$recordId = $_GET['record_id'] ?? null;  // Fetch RecordId from URL

if ($recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT AppointmentId, PetId
            FROM PatientRecords
            WHERE RecordId = :record_id
        ");
        $stmt->execute([':record_id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $appointment_id = $record['AppointmentId'];
            $pet_id = $record['PetId'];

            // Step 2: Fetch Diagnosis Data using AppointmentId and PetId
            $stmt = $pdo->prepare("
                SELECT * FROM Diagnoses
                WHERE AppointmentId = :appointment_id AND PetId = :pet_id
            ");
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':pet_id' => $pet_id
            ]);
            $diagnosisData = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

$follow_up_dates = $_SESSION['form_data']['follow_up_dates'] ?? [''];
$follow_up_notes = $_SESSION['form_data']['follow_up_notes'] ?? [''];
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
        <h1>Patient Record</h1>
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
            
            <br>
            <br>
            <hr>
            <br>

            <h2>Chief Complaint</h2>
            <form action="../src/process_chief_complaint.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">
                <div class="form-row">
                    <div class="input-container">
                        <label for="chief_complaint"><b>Primary Concern:<span class="required">*</span></b></label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="3" maxlength="300"
                            placeholder="Describe the main reason for today's visit..."
                            required><?= htmlspecialchars($patientRecord['ChiefComplaint'] ?? '', ENT_QUOTES); ?></textarea>
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
                            value="<?= htmlspecialchars($patientRecord['onset'] ?? '', ENT_QUOTES); ?>">
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
                            <option value="Other" <?= ($patientRecord['DurationDays'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other (Specify)</option>
                        </select>
                    
                        <input type="number" id="custom-duration" name="custom-duration" min="1"
                            placeholder="Specify duration (days)" 
                            value="<?= htmlspecialchars($patientRecord['CustomDuration'] ?? '', ENT_QUOTES); ?>"
                            style="display: <?= ($patientRecord['DurationDays'] ?? '') == 'Other' ? 'block' : 'none'; ?>;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Observed Symptoms:</b></label>
                        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                            <?php
                            // List of predefined symptoms
                            $symptoms = ['Vomiting', 'Diarrhea', 'Lethargy', 'Coughing', 'Sneezing'];
                
                            // Retrieve symptoms from patient record (assumed comma-separated)
                            $storedSymptoms = isset($patientRecord['Symptoms']) ? explode(',', $patientRecord['Symptoms']) : [];
                
                            foreach ($symptoms as $symptom) :
                                $checked = in_array($symptom, $storedSymptoms);
                            ?>
                                <label>
                                    <input type="checkbox" name="symptoms[]" value="<?= $symptom ?>" <?= $checked ? 'checked' : ''; ?>>
                                    <?= $symptom ?>
                                </label>
                            <?php endforeach; ?>
                
                            <!-- Other Symptom -->
                            <label>
                                <input type="checkbox" name="symptoms[]" value="Other"
                                    <?= in_array('Other', $storedSymptoms) ? 'checked' : ''; ?>
                                    onclick="toggleOtherSymptom()">
                                Other
                            </label>
                            <input type="text" id="other_symptom" name="other_symptom" placeholder="Specify other symptoms"
                                value="<?= htmlspecialchars($patientRecord['OtherSymptom'] ?? '', ENT_QUOTES) ?>"
                                style="display: <?= (!empty($patientRecord['OtherSymptom'])) ? 'block' : 'none'; ?>; width: 113%;">
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
                                    "Grain-Free Diet", "Specialized Diets", "Treats and Snacks", "Other"
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
                                $urineFrequencies = ['normal' => 'Normal', 'increased' => 'Increased', 'decreased' => 'Decreased', 'none' => 'No Urination', 'Other' => 'Other'];
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
                                    'Other' => 'Other'
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
                        <!-- Medication Given -->
                        <div class="input-container">
                            <label for="medication"><b>Medication given prior to check-up:</b></label>
                            <textarea id="medication" name="medication" rows="2" maxlength="300">
                                <?= htmlspecialchars($patientRecord['MedicationPriorCheckup'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </textarea>
                        
                            <?php if (!empty($errors['medication'])): ?>
                                <span class="error-message" style="color: red;"><?= htmlspecialchars($errors['medication']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="confirm-btn">Save Chief Complaint</button>
                    </div>
                </form>
                
            <br>
            <br>
            <hr>
            <br>
            
            <h2>Medical History</h2>
            <form action="../src/process_medical_history.php" method="POST">
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
                            $vaccines = ['Rabies', 'Parvovirus', 'Distemper', 'Hepatitis', 'Bordetella', 'Leptospirosis', 'Other'];
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
                        <?php
                            // Fetch data from MedicalHistory
                            $selectedDewormer = $medicalHistory['DewormerUsed'] ?? '';
                        ?>
                        <select id="Dewormer" name="Dewormer" onchange="toggleDewormerInput(this)">
                            <option value="" <?= empty($selectedDewormer) ? 'selected' : ''; ?>>Select a dewormer</option>
                            <option value="Drontal" <?= $selectedDewormer === 'Drontal' ? 'selected' : ''; ?>>Drontal</option>
                            <option value="Panacur" <?= $selectedDewormer === 'Panacur' ? 'selected' : ''; ?>>Panacur</option>
                            <option value="Strongid" <?= $selectedDewormer === 'Strongid' ? 'selected' : ''; ?>>Strongid</option>
                            <option value="Cestex" <?= $selectedDewormer === 'Cestex' ? 'selected' : ''; ?>>Cestex</option>
                            <option value="Milbemax" <?= $selectedDewormer === 'Milbemax' ? 'selected' : ''; ?>>Milbemax</option>
                            <option value="Other" <?= $selectedDewormer === 'Other' ? 'selected' : ''; ?>>Other (Specify)</option>
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
                        <textarea id="MedicationAllergies" name="MedicationAllergies" rows="2" placeholder="e.g., Penicillin">
                            <?= htmlspecialchars($medicalHistory['MedicationAllergies'] ?? '', ENT_QUOTES); ?>
                        </textarea>
                    </div>
                    
                    <div class="input-container">
                        <label for="PastIllnesses"><b>Past Illnesses:</b></label>
                        <textarea id="PastIllnesses" name="PastIllnesses" rows="2" placeholder="e.g., Parvovirus in 2023">
                            <?= htmlspecialchars($medicalHistory['PastIllnesses'] ?? '', ENT_QUOTES); ?>
                        </textarea>
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
                        <textarea id="BehavioralIssues" name="BehavioralIssues" rows="2" placeholder="e.g., Aggression towards other pets"><?= htmlspecialchars($medicalHistory['BehaviouralIssues'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label for="LastHeatCycle"><b>Last Heat Cycle (for females):</b></label>
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
            
            <script>
                function toggleVaccineInput(select) {
                    document.getElementById("VaccineInput").style.display = select.value === "Other" ? "block" : "none";
                }
            </script>

            <br>
            <br>
            <hr>
            <br>

            <h2>Physical Examination</h2>
            <form action="../src/process_physical_exam.php" method="POST">
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
                        // Fetch stored value from database
                        $storedRespiratoryRate = $physicalExam['RespiratoryRate'] ?? ''; 
                        $respiratoryOptions = [10, 15, 20, 25, 30];
                
                        // Check if stored value is custom (not in predefined options)
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

            <div class="form-buttons">
                <button type="submit" class="confirm-btn">Save Physical Exam</button>
            </div>
            </form>

            <br>
            <br>
            <hr>
            <br>
                
            <h2>Laboratory Examination</h2>
            <form action="../src/process_lab_exam.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

                <?php $storedTests = $_SESSION['lab_exam_data']['lab-tests'] ?? []; ?>

                <!-- Blood Tests -->
                <h3 style="color: #156f77;">Blood Tests</h3>
                <div class="form-row">
                    <div class="form-group">
                    <label>
                        <input type="checkbox" name="lab-tests[]" value="CBC" <?= in_array('CBC', $storedTests) ? 'checked' : ''; ?>>
                        Complete Blood Count (CBC)
                    </label>
            
                    <label>
                        <input type="checkbox" name="lab-tests[]" value="Blood Chemistry" <?= in_array('Blood Chemistry', $storedTests) ? 'checked' : ''; ?>>
                        Blood Chemistry
                    </label>
            
                    <label>
                        <input type="checkbox" name="lab-tests[]" value="Blood Smear" <?= in_array('Blood Smear', $storedTests) ? 'checked' : ''; ?>>
                        Blood Smear
                    </label>
            
                    <label>
                        <input type="checkbox" name="lab-tests[]" value="Other Blood Test"
                            onchange="toggleCustomInput(this, 'blood-test-detail')"
                            <?= in_array('Other Blood Test', $storedTests) ? 'checked' : ''; ?>>
                        Other Blood Test
                    </label>
            
                    <!-- Custom Input for 'Other' -->
                    <input type="text" id="blood-test-detail" name="blood-test-detail"
                        placeholder="Specify other blood test"
                        value="<?= htmlspecialchars($otherBloodTestDetail, ENT_QUOTES); ?>"
                        style="display: <?= in_array('Other Blood Test', $storedTests) ? 'block' : 'none'; ?>;">
                </div>
                </div>

                <!-- Imaging -->
                <h3 style="color: #156f77;">Imaging</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="lab-tests[]" value="X-ray" <?= in_array('X-ray', $storedTests) ? 'checked' : ''; ?>> X-ray</label>
                        <label><input type="checkbox" name="lab-tests[]" value="Ultrasound" <?= in_array('Ultrasound', $storedTests) ? 'checked' : ''; ?>> Ultrasound</label>
                        <label><input type="checkbox" name="lab-tests[]" value="CT Scan" <?= in_array('CT Scan', $storedTests) ? 'checked' : ''; ?>> CT Scan</label>
                        <label>
                            <input type="checkbox" name="lab-tests[]" value="Other Imaging" onchange="toggleCustomInput(this, 'imaging-other-detail')" <?= in_array('Other Imaging', $storedTests) ? 'checked' : ''; ?>> Other Imaging
                        </label>
                        <input type="text" id="imaging-other-detail" name="imaging-other-detail"
                            placeholder="Specify other imaging"
                            value="<?= htmlspecialchars($_SESSION['lab_exam_data']['imaging-other-detail'] ?? '', ENT_QUOTES); ?>"
                            style="display: <?= in_array('Other Imaging', $storedTests) ? 'block' : 'none'; ?>;">
                    </div>
                </div>

                <!-- Microbiology -->
                <h3 style="color: #156f77;">Microbiology</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="lab-tests[]" value="Ear Swab" <?= in_array('Ear Swab', $storedTests) ? 'checked' : ''; ?>> Ear Swab</label>
                        <label><input type="checkbox" name="lab-tests[]" value="Skin Scrape" <?= in_array('Skin Scrape', $storedTests) ? 'checked' : ''; ?>> Skin Scrape</label>
                        <label><input type="checkbox" name="lab-tests[]" value="Fungal Test" <?= in_array('Fungal Test', $storedTests) ? 'checked' : ''; ?>> Fungal Test</label>
                        <label>
                            <input type="checkbox" name="lab-tests[]" value="Other Microbiology" onchange="toggleCustomInput(this, 'microbiology-other-detail')" <?= in_array('Other Microbiology', $storedTests) ? 'checked' : ''; ?>> Other Microbiology
                        </label>
                        <input type="text" id="microbiology-other-detail" name="microbiology-other-detail"
                            placeholder="Specify other microbiology"
                            value="<?= htmlspecialchars($_SESSION['lab_exam_data']['microbiology-other-detail'] ?? '', ENT_QUOTES); ?>"
                            style="display: <?= in_array('Other Microbiology', $storedTests) ? 'block' : 'none'; ?>;">
                    </div>
                </div>

                <!-- Other Tests -->
                <h3 style="color: #156f77;">Other Tests</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="lab-tests[]" value="Otoscope" <?= in_array('Otoscope', $storedTests) ? 'checked' : ''; ?>> Otoscope Examination</label>
                        <label><input type="checkbox" name="lab-tests[]" value="Vaginal Smear" <?= in_array('Vaginal Smear', $storedTests) ? 'checked' : ''; ?>> Vaginal Smear</label>
                        <label>
                            <input type="checkbox" name="lab-tests[]" value="ETC" onchange="toggleCustomInput(this, 'etc-detail')" <?= in_array('ETC', $storedTests) ? 'checked' : ''; ?>> Other (Specify)
                        </label>
                        <input type="text" id="etc-detail" name="etc-detail"
                            placeholder="Specify other tests"
                            value="<?= htmlspecialchars($_SESSION['lab_exam_data']['etc-detail'] ?? '', ENT_QUOTES); ?>"
                            style="display: <?= in_array('ETC', $storedTests) ? 'block' : 'none'; ?>;">
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="confirm-btn">Save Laboratory Exam</button>
                </div>
            </form>

            <br>
            <hr>
            <br>

            <h2>Diagnosis</h2>
            <form action="../src/process_diagnosis.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? '') ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? '') ?>">

                <?php
                $storedData = $_SESSION['diagnosis_data'] ?? [];
                ?>

                <div class="form-row">
                    <div class="input-container">
                        <label for="diagnosis-type"><b>Is this a Final or Tentative Diagnosis?</b></label>
                        <select id="diagnosis-type" name="diagnosis-type" required>
                            <option value="final" <?= ($diagnosisData['DiagnosisType'] ?? '') === 'final' ? 'selected' : ''; ?>>Final Diagnosis</option>
                            <option value="tentative" <?= ($diagnosisData['DiagnosisType'] ?? '') === 'tentative' ? 'selected' : ''; ?>>Tentative Diagnosis</option>
                        </select>
                    </div>
            
                    <div class="input-container">
                        <label for="prognosis"><b>Prognosis:</b></label>
                        <select id="prognosis" name="prognosis" required>
                            <option value="">Select Prognosis</option>
                            <?php
                            $prognosisOptions = ['Excellent', 'Good', 'Fair', 'Poor', 'Questionable', 'Grave'];
                            foreach ($prognosisOptions as $option) {
                                $selected = ($diagnosisData['Prognosis'] ?? '') === $option ? 'selected' : '';
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

            <br>
            <br>
            <hr>
            <br>

            <h2>Medications Given</h2>
            <form action="../src/process_medication.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

                <div id="medications-container">
                    <?php
                    // Load stored medications (if any) to retain form values
                    $storedMedications = $_SESSION['medication_data']['medications'] ?? [];
                    $storedDosages = $_SESSION['medication_data']['dosages'] ?? [];
                    $storedDurations = $_SESSION['medication_data']['durations'] ?? [];
                    $storedCustomMedications = $_SESSION['medication_data']['custom-medications'] ?? [];
                    $storedCustomDosages = $_SESSION['medication_data']['custom-dosages'] ?? [];
                    $storedCustomDurations = $_SESSION['medication_data']['custom-durations'] ?? [];

                    // Ensure at least one empty input is available
                    if (empty($storedMedications)) {
                        $storedMedications[] = "";
                        $storedDosages[] = "";
                        $storedDurations[] = "";
                    }

                    // Loop through stored medications
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
                                        <option value="Metronidazole" <?= ($medication == 'Metronidazole') ? 'selected' : ''; ?>>Metronidazole</option>
                                    </optgroup>
                                    <optgroup label="Anti-Inflammatories">
                                        <option value="Carprofen" <?= ($medication == 'Carprofen') ? 'selected' : ''; ?>>Carprofen</option>
                                        <option value="Meloxicam" <?= ($medication == 'Meloxicam') ? 'selected' : ''; ?>>Meloxicam</option>
                                        <option value="Prednisone" <?= ($medication == 'Prednisone') ? 'selected' : ''; ?>>Prednisone</option>
                                    </optgroup>
                                    <optgroup label="Vitamins & Supplements">
                                        <option value="Vitamin B12" <?= ($medication == 'Vitamin B12') ? 'selected' : ''; ?>>Vitamin B12</option>
                                        <option value="Omega-3" <?= ($medication == 'Omega-3') ? 'selected' : ''; ?>>Omega-3 Fatty Acids</option>
                                        <option value="Calcium Supplements" <?= ($medication == 'Calcium Supplements') ? 'selected' : ''; ?>>Calcium Supplements</option>
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
                                        <option value="0.5 mL" <?= ($storedDosages[$index] == '0.5 mL') ? 'selected' : ''; ?>>0.5 mL</option>
                                        <option value="1 mL" <?= ($storedDosages[$index] == '1 mL') ? 'selected' : ''; ?>>1 mL</option>
                                    </optgroup>
                                    <optgroup label="Tablet (mg)">
                                        <option value="50 mg" <?= ($storedDosages[$index] == '50 mg') ? 'selected' : ''; ?>>50 mg</option>
                                        <option value="100 mg" <?= ($storedDosages[$index] == '100 mg') ? 'selected' : ''; ?>>100 mg</option>
                                    </optgroup>
                                    <option value="Other" <?= ($storedDosages[$index] == 'Other') ? 'selected' : ''; ?>>Other (Specify)</option>
                                </select>
                                <input type="text" name="custom-dosages[]" placeholder="Specify dosage" class="custom-dosage"
                                    value="<?= htmlspecialchars($storedCustomDosages[$index] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= ($storedDosages[$index] == 'Other') ? 'block' : 'none'; ?>;">
                            </div>

                            <div class="input-container">
                                <label><b>Duration:</b></label>
                                <select name="durations[]" class="duration-select" onchange="toggleCustomDuration(this)">
                                    <option value="">Select Duration</option>
                                    <option value="1 Day" <?= ($storedDurations[$index] == '1 Day') ? 'selected' : ''; ?>>1 Day</option>
                                    <option value="3 Days" <?= ($storedDurations[$index] == '3 Days') ? 'selected' : ''; ?>>3 Days</option>
                                    <option value="7 Days" <?= ($storedDurations[$index] == '7 Days') ? 'selected' : ''; ?>>7 Days</option>
                                    <option value="Other" <?= ($storedDurations[$index] == 'Other') ? 'selected' : ''; ?>>Other (Specify)</option>
                                </select>
                                <input type="text" name="custom-durations[]" placeholder="Specify duration" class="custom-duration"
                                    value="<?= htmlspecialchars($storedCustomDurations[$index] ?? '', ENT_QUOTES); ?>"
                                    style="display: <?= ($storedDurations[$index] == 'Other') ? 'block' : 'none'; ?>;">
                            </div>

                            <button type="button" class="delete-button" onclick="removeMedication(this)">X</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-buttons">
                    <button type="button" id="add-medication">Add Medication</button>
                    <button type="submit" class="confirm-btn">Save Medications</button>
                </div>
            </form>
            <br>
            <br>
            <hr>
            <br>
            <h2>Follow-Up Schedule</h2>
            <form action="../src/process_followup.php" method="POST">
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
                                    required>
                            </div>
                            <div class="input-container">
                                <label for="follow-up-notes-<?= $index + 1 ?>"><b>Notes:</b></label>
                                <textarea id="follow-up-notes-<?= $index + 1 ?>" 
                                        name="follow_up_notes[]" 
                                        rows="3"
                                        placeholder="Enter follow-up notes"><?= htmlspecialchars($follow_up_notes[$index] ?? '', ENT_QUOTES); ?></textarea>
                            </div>
                            <button type="button" class="delete-button" onclick="removeFollowUp(this)" <?= $index > 0 ? '' : 'style="display: none;"' ?>>X</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-buttons">
                    <button type="button" id="add-followup">Add Follow-Up</button>
                    <button type="submit" class="confirm-btn">Save Follow-Up</button>
                </div>
            </form>
            <form action="../src/process_finish_consultation.php" method="POST">
                <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? '') ?>">
                <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? '') ?>">
                <button type="submit" onclick="confirmFinishConsultation(event)">Finish Consultation</button>
            </form>
            <?php if (!empty($errors)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Occurred',
                            html: '<?= implode("<br>", array_map("htmlspecialchars", $errors)) ?>',
                            confirmButtonColor: '#d33'
                        });
                    });
                </script>
                <?php exit(); ?>
            <?php endif; ?>
            <script>
                function confirmFinishConsultation(event) {
                    event.preventDefault();
                    Swal.fire({
                        title: "Finish Consultation?",
                        text: "All data will be saved, and you can't modify this appointment afterward.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, finish it!",
                        cancelButtonText: "Cancel",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            event.target.form.submit();
                        }
                    });
                }
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    <?php if (!empty($_SESSION['success_chief_complaint'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_chief_complaint'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_chief_complaint']); ?>
                    <?php endif; ?>
                
                    <?php if (!empty($_SESSION['success_medical_history'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_medical_history'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_medical_history']); ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($_SESSION['success_physical_exam'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_physical_exam'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_physical_exam']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['success_lab_exam'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_lab_exam'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_lab_exam']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['success_diagnosis'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_diagnosis'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_diagnosis']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['success_lab_exam'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?= htmlspecialchars($_SESSION['success_lab_exam'], ENT_QUOTES); ?>',
                            confirmButtonColor: '#156f77'
                        });
                        <?php unset($_SESSION['success_lab_exam']); ?>
                    <?php endif; ?>
                });
            </script>
            <script>
                document.getElementById("add-medication").addEventListener("click", function () {
                    let container = document.getElementById("medications-container");
                    let newMed = container.firstElementChild.cloneNode(true);
                    
                    // Reset values in the cloned medication row
                    newMed.querySelector(".medication-select").selectedIndex = 0;
                    newMed.querySelector(".custom-medication").style.display = "none";
                    newMed.querySelector(".custom-medication").value = "";
                
                    newMed.querySelector(".dosage-select").selectedIndex = 0;
                    newMed.querySelector(".custom-dosage").style.display = "none";
                    newMed.querySelector(".custom-dosage").value = "";
                
                    newMed.querySelector(".duration-select").selectedIndex = 0;
                    newMed.querySelector(".custom-duration").style.display = "none";
                    newMed.querySelector(".custom-duration").value = "";
                
                    // Show delete button
                    newMed.querySelector(".delete-button").style.display = "inline-block";
                
                    container.appendChild(newMed);
                });
                
                function removeMedication(button) {
                    let container = document.getElementById("medications-container");
                    if (container.children.length > 1) {
                        button.parentElement.remove();
                    }
                }
            </script>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    <?php if (isset($_SESSION['success_message'])): ?>
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved!',
                            text: '<?= addslashes($_SESSION['success_message']); ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                });
            </script>
            <script>
                function toggleUrineFrequencyInput() {
                    let frequencySelect = document.getElementById("frequency");
                    let customFrequencyInput = document.getElementById("custom-frequency");
                    customFrequencyInput.style.display = frequencySelect.value === "Other" ? "block" : "none";
                }

                function toggleUrineColorInput() {
                    let colorSelect = document.getElementById("color");
                    let customColorInput = document.getElementById("custom-color");
                    customColorInput.style.display = colorSelect.value === "Other" ? "block" : "none";
                }
                function toggleDietInput() {
                    let dietSelect = document.getElementById("diet");
                    let customDietInput = document.getElementById("custom-diet");
                    customDietInput.style.display = dietSelect.value === "Other" ? "block" : "none";
                }
                function toggleOtherSymptom() {
                    const otherCheckbox = document.querySelector('input[name="symptoms[]"][value="Other"]');
                    const otherInput = document.getElementById("other_symptom");
                    otherInput.style.display = otherCheckbox.checked ? "block" : "none";
                }
            </script>
            <script>
                document.getElementById("add-medication").addEventListener("click", function () {
                    let container = document.getElementById("medications-container");
                    let newMed = container.firstElementChild.cloneNode(true);
                    
                    // Reset fields in new medication row
                    newMed.querySelector(".medication-select").selectedIndex = 0;
                    newMed.querySelector(".custom-medication").style.display = "none";
                    newMed.querySelector(".custom-medication").value = "";
                    
                    newMed.querySelector(".dosage-select").selectedIndex = 0;
                    newMed.querySelector(".custom-dosage").style.display = "none";
                    newMed.querySelector(".custom-dosage").value = "";

                    newMed.querySelector(".duration-select").selectedIndex = 0;
                    newMed.querySelector(".custom-duration").style.display = "none";
                    newMed.querySelector(".custom-duration").value = "";
                    
                    // Ensure the delete button is visible and aligned at the bottom
                    let deleteButton = newMed.querySelector(".delete-button");
                    deleteButton.style.display = "inline-flex"; 
                    deleteButton.style.alignItems = "flex-end";  // Align to the bottom
                    deleteButton.style.justifyContent = "center"; 
                    deleteButton.style.width = "42px"; 
                    deleteButton.style.height = "42px"; 
                    deleteButton.style.marginTop = "34px"; // Push it to the bottom
                    deleteButton.addEventListener("click", function () {
                        newMed.remove(); // Remove medication row when delete is clicked
                    });

                    container.appendChild(newMed);
                });

                function toggleCustomMedication(selectElement) {
                    let customInput = selectElement.parentElement.querySelector(".custom-medication");
                    customInput.style.display = selectElement.value === "Other" ? "block" : "none";
                }

                function toggleCustomDosage(selectElement) {
                    let customInput = selectElement.parentElement.querySelector(".custom-dosage");
                    customInput.style.display = selectElement.value === "Other" ? "block" : "none";
                }

                function toggleCustomDuration(selectElement) {
                    let customInput = selectElement.parentElement.querySelector(".custom-duration");
                    customInput.style.display = selectElement.value === "Other" ? "block" : "none";
                }

                function removeMedication(button) {
                    let container = document.getElementById("medications-container");
                    if (container.children.length > 1) {
                        button.parentElement.remove();
                    }
                }
                </script>
                <script>
                    function toggleCustomInput(checkboxId, inputId) {
                        document.getElementById(inputId).style.display = document.getElementById(checkboxId).checked ? "block" : "none";
                    }
                </script>
                <script>
                function toggleInput(selectId, inputId) {
                    let select = document.getElementById(selectId);
                    let input = document.getElementById(inputId);
                    input.style.display = select.value === "Other" ? "block" : "none";
                }
                </script>
                <script>
                    document.getElementById("Vaccine").addEventListener("change", function() {
                        document.getElementById("VaccineInput").style.display = this.value === "Other" ? "block" : "none";
                    });
                    document.getElementById("Dewormer").addEventListener("change", function() {
                        document.getElementById("DewormerInput").style.display = this.value === "Other" ? "block" : "none";
                    });
                    document.getElementById("duration").addEventListener("change", function() {
                        document.getElementById("custom-duration").style.display = this.value === "Other" ? "block" : "none";
                    });
                </script>
                <script>
                    function updatePainValue(value) {
                        document.getElementById("pain_value_display").innerText = value;
                    }

                    function toggleSymptomInput(checkbox) {
                        let inputField = document.getElementById("other_symptom");
                        inputField.style.display = checkbox.checked ? "block" : "none";
                    }
                </script>
</body>
</html>