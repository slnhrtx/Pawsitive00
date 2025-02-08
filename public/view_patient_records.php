<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);
session_start();

require __DIR__ . '/../config/dbh.inc.php';

if (!isset($_GET['AppointmentId']) || !isset($_GET['PetId'])) {
    die("Invalid request. AppointmentId and PetId are required.");
}

$appointment_id = (int)$_GET['AppointmentId'];
$pet_id = (int)$_GET['PetId'];

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role'; 

try {
    $stmt = $pdo->prepare("
        SELECT
            pr.RecordId, pr.ChiefComplaint, pr.OnsetDate, pr.DurationDays, pr.Appetite, pr.Diet, 
            pr.UrineFrequency, pr.UrineColor, pr.WaterIntake, pr.PainLevel, pr.FecalScore, pr.Environment, pr.MedicationPriorCheckup,
            p.Name AS PetName, p.Weight, p.Temperature, p.Gender, p.Breed, p.Birthday,
            a.AppointmentDate, a.AppointmentTime, s.ServiceName,
            m.MedicationName, m.Dosage, m.Duration,
            pe.Pulse, pe.HeartRate, pe.RespiratoryRate, pe.HeartSound, pe.LungSound, pe.MucousMembrane, pe.CapillaryRefillTime
        FROM PatientRecords pr
        INNER JOIN Pets p ON pr.PetId = p.PetId
        INNER JOIN Appointments a ON pr.AppointmentId = a.AppointmentId
        LEFT JOIN Services s ON a.ServiceId = s.ServiceId
        LEFT JOIN PrescribeMedications m ON pr.RecordId = m.RecordId
        LEFT JOIN PhysicalExams pe ON pr.RecordId = pe.RecordId
        WHERE pr.AppointmentId = :appointment_id AND pr.PetId = :pet_id
        ORDER BY pr.RecordId DESC LIMIT 1
    ");
    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':pet_id' => $pet_id
    ]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die("No records found for this appointment.");
    }

    $record_id = $record['RecordId'];

    $stmt = $pdo->prepare("
        SELECT 
            LastVaccinationDate, 
            VaccinesGiven, 
            LastDewormingDate, 
            DewormerUsed,
            FleaTickPrevention,
            HeartwormPrevention,
            GeneticConditions,
            FoodAllergies,
            MedicationAllergies,
            PastIllnesses,
            PastSurgeries,
            Hospitalizations,
            CurrentMedications,
            BehavioralIssues,
            LastHeatCycle,
            SpayedNeutered
        FROM MedicalHistory 
        WHERE PetId = :PetId
        ORDER BY HistoryId DESC 
    ");
    $stmt->execute([':PetId' => $pet_id]);
    $medical_history = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Medications Given
    $stmt = $pdo->prepare("
        SELECT MedicationName, Dosage, Duration FROM PrescribeMedications 
        WHERE RecordId = :record_id
    ");
    $stmt->execute([':record_id' => $record_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Diagnoses and Treatment
    $stmt = $pdo->prepare("
        SELECT DiagnosisType, Diagnosis, Treatment, Prognosis, Status FROM Diagnoses 
        WHERE RecordId = :record_id
    ");
    $stmt->execute([':record_id' => $record_id]);
    $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Follow-Ups
    $stmt = $pdo->prepare("
        SELECT FollowUpDate FROM FollowUps 
        WHERE RecordId = :record_id
    ");
    $stmt->execute([':record_id' => $record_id]);
    $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Laboratory Tests
    $stmt = $pdo->prepare("
        SELECT TestName, TestDetail, FilePath FROM LaboratoryTests 
        WHERE RecordId = :record_id
    ");
    $stmt->execute([':record_id' => $record_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
    SELECT * FROM PhysicalExams 
    WHERE RecordId = :record_id
    ");
    $stmt->execute([':record_id' => $record_id]);
    $physical_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="../assets/css/consultation_record.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <button class="btn" onclick="downloadPDF()">📄 Download as PDF</button>
        <form class="staff-form">            
            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($_GET['appointment_id'] ?? ''); ?>">
            <input type="hidden" name="pet_id" value="<?= htmlspecialchars($_GET['pet_id'] ?? ''); ?>">

            <h2>General Details</h2>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Pet Name:</b></label>
                    <p><?= !empty($record['PetName']) ? htmlspecialchars($record['PetName'], ENT_QUOTES, 'UTF-8') : 'Not Provided' ?></p>
                </div>

                <div class="input-container">
                    <label><b>Weight (kg):</b></label>
                    <p><?= !empty($record['Weight']) ? htmlspecialchars($record['Weight'], ENT_QUOTES, 'UTF-8') . ' kg' : 'Not Provided' ?></p>
                </div>

                <div class="input-container">
                    <label><b>Temperature (°C):</b></label>
                    <p><?= !empty($record['Temperature']) ? htmlspecialchars($record['Temperature'], ENT_QUOTES, 'UTF-8') . ' °C' : 'Not Provided' ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Appointment Date:</b></label>
                    <p><?= !empty($record['AppointmentDate']) ? htmlspecialchars($record['AppointmentDate'], ENT_QUOTES, 'UTF-8') : 'Not Provided' ?></p>
                </div>

                <div class="input-container">
                    <label><b>Service:</b></label>
                    <p><?= !empty($record['ServiceName']) ? htmlspecialchars($record['ServiceName'], ENT_QUOTES, 'UTF-8') : 'Not Provided' ?></p>
                </div>
            </div>
            
            <br>
            <br>
            <hr>
            <br>

            <h2>General Details</h2>
            <div class="form-row">
                <div class="input-container">
                    <label><b>Primary Concern:</b></label>
                    <p><?= !empty($record['ChiefComplaint']) ? nl2br(htmlspecialchars($record['ChiefComplaint'], ENT_QUOTES)) : 'Not Provided' ?></p>

                    <?php if (!empty($_SESSION['errors']['chief_complaint'])): ?>
                        <span class="error-message" style="color: red;"><?= htmlspecialchars($_SESSION['errors']['chief_complaint']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Onset of Symptoms:</b></label>
                    <p><?= !empty($record['OnsetDate']) ? htmlspecialchars($record['OnsetDate'], ENT_QUOTES) : 'Not Provided' ?></p>
                </div>

                <div class="input-container">
                    <label><b>Duration (Days):</b></label>
                    <p>
                        <?php 
                        $durationMapping = [
                            '1' => '1 Day', '2' => '2 Days', '3' => '3 Days', '5' => '5 Days', 
                            '7' => '1 Week', '14' => '2 Weeks', '30' => '1 Month'
                        ];
                        echo isset($record['DurationDays']) ? ($durationMapping[$record['DurationDays']] ?? 'Not Provided') : 'Not Provided';
                        ?>
                    </p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Observed Symptoms:</b></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 46.9px;">
                        <?php
                        $symptomsList = ['Vomiting', 'Diarrhea', 'Lethargy', 'Coughing', 'Sneezing', 'Other'];
                        foreach ($symptomsList as $symptom):
                            $symptomKey = strtolower(str_replace(' ', '_', $symptom)); // Convert to lowercase for input naming
                            $isChecked = isset($form_data['symptoms']) && in_array($symptom, $form_data['symptoms']);
                            $details = htmlspecialchars($form_data[$symptomKey . '-detail'] ?? '', ENT_QUOTES);
                        ?>
                            <label>
                                <input type="checkbox" name="symptoms[]" value="<?= $symptom ?>" 
                                    <?= $isChecked ? 'checked' : ''; ?>
                                    onclick="toggleSymptomDetail(this, '<?= $symptomKey ?>')">
                                <?= $symptom ?>
                            </label>
                            <input type="text" id="<?= $symptomKey ?>-detail" name="<?= $symptomKey ?>-detail" 
                                placeholder="Specify details for <?= $symptom ?>"
                                value="<?= $details ?>"
                                style="display: <?= !empty($details) ? 'block' : 'none'; ?>;">
                        <?php endforeach; ?>
                    </div>
            
                <div class="form-row">
                    <div class="input-container">
                        <label><b>Appetite:</b></label>
                        <p><?= htmlspecialchars($record['Appetite'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Diet:</b></label>
                        <p><?= htmlspecialchars($record['Diet'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Urine Frequency:</b></label>
                        <p><?= htmlspecialchars($record['UrineFrequency'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Urine Color:</b></label>
                        <p><?= htmlspecialchars($record['UrineColor'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-container">
                        <label><b>Water Intake:</b></label>
                        <p><?= htmlspecialchars($record['WaterIntake'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Environment:</b></label>
                        <p><?= htmlspecialchars($record['Environment'] ?? 'Not Available', ENT_QUOTES); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Fecal Score -->
                    <div class="input-container" style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px; width: 100%;">
                        <label><b>Fecal Score (Bristol):</b></label>
                        <p><?= !empty($record['FecalScore']) ? htmlspecialchars($record['FecalScore'], ENT_QUOTES) : 'Not Provided'; ?></p>
                    </div>

                    <!-- Pain Level -->
                    <div class="input-container">
                        <label><b>Pain Level (1-10):</b></label>
                        <p><?= htmlspecialchars($record['PainLevel'] ?? 'Not Provided', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Medication given prior to check-up:</b></label>
                        <p><?= !empty($record['MedicationPriorCheckup']) 
                                ? htmlspecialchars($record['MedicationPriorCheckup'], ENT_QUOTES, 'UTF-8') 
                                : '<span style="color: gray;">No medication recorded</span>'; ?>
                        </p>
                    </div>
                </div>

            <br>
            <br>
            <hr>
            <br>

            <h2>Medical History</h2>
            <div class="form-row">
                <div class="input-container">
                    <label><b>Last Vaccination Date:</b></label>
                    <p><?= !empty($medical_history['LastVaccinationDate']) ? htmlspecialchars($medical_history['LastVaccinationDate'], ENT_QUOTES) : 'Not Available' ?></p>
                </div>

                <div class="input-container">
                    <label><b>Vaccine Given:</b></label>
                    <p><?= !empty($medical_history['VaccinesGiven']) ? htmlspecialchars($medical_history['VaccinesGiven'], ENT_QUOTES) : 'Not Available' ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Last Deworming Date:</b></label>
                    <p><?= htmlspecialchars($medical_history['LastDewormingDate'] ?? 'Not Available', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Dewormer Used:</b></label>
                    <p><?= htmlspecialchars($medical_history['DewormerUsed'] ?? 'Not Available', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Flea & Tick Prevention:</b></label>
                    <p><?= htmlspecialchars($medical_history['FleaTickPrevention'] ?? 'Not Available', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Heartworm Prevention:</b></label>
                    <p><?= htmlspecialchars($medical_history['HeartwormPrevention'] ?? 'Not Available', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Genetic Conditions:</b></label>
                    <p><?= htmlspecialchars($medical_history['GeneticConditions'] ?? 'None', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Food Allergies:</b></label>
                    <p><?= htmlspecialchars($medical_history['FoodAllergies'] ?? 'None', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Medication Allergies:</b></label>
                    <p><?= htmlspecialchars($medical_history['MedicationAllergies'] ?? 'None', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Past Illnesses:</b></label>
                    <p><?= htmlspecialchars($medical_history['PastIllnesses'] ?? 'None', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Past Surgeries:</b></label>
                    <p><?= htmlspecialchars($medical_history['PastSurgeries'] ?? 'None', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Hospitalizations:</b></label>
                    <p><?= htmlspecialchars($medical_history['Hospitalizations'] ?? 'None', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Current Medications:</b></label>
                    <p><?= htmlspecialchars($medical_history['CurrentMedications'] ?? 'None', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Behavioral Issues:</b></label>
                    <p><?= htmlspecialchars($medical_history['BehavioralIssues'] ?? 'None', ENT_QUOTES); ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Last Heat Cycle:</b></label>
                    <p><?= htmlspecialchars($medical_history['LastHeatCycle'] ?? 'Not Available', ENT_QUOTES); ?></p>
                </div>

                <div class="input-container">
                    <label><b>Spayed/Neutered:</b></label>
                    <p><?= isset($medical_history['SpayedNeutered']) ? ($medical_history['SpayedNeutered'] ? 'Yes' : 'No') : 'Not Available'; ?></p>
                </div>
            </div>

            <br>
            <br>
            <hr>
            <br>

            <h2>Physical Examination</h2>
            <?php if (!empty($physical_exams)): ?>
                <?php foreach ($physical_exams as $exam): ?>
                    <h3 style="color: #156f77;">Vital Signs</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Pulse (bpm):</b></label>
                            <p><?= htmlspecialchars($exam['Pulse'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Heart Rate (bpm):</b></label>
                            <p><?= htmlspecialchars($exam['HeartRate'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Respiratory Rate (brpm):</b></label>
                            <p><?= htmlspecialchars($exam['RespiratoryRate'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <h3 style="color: #156f77;">Heart & Lung Sounds</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Heart Sound:</b></label>
                            <p><?= htmlspecialchars($exam['HeartSound'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Lung Sound:</b></label>
                            <p><?= htmlspecialchars($exam['LungSound'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <h3 style="color: #156f77;">Mucous Membrane & CRT</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Mucous Membrane:</b></label>
                            <p><?= htmlspecialchars($exam['MucousMembrane'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Capillary Refill Time (sec):</b></label>
                            <p><?= htmlspecialchars($exam['CapillaryRefillTime'] ?? 'Not Available', ENT_QUOTES) ?> sec</p>
                        </div>
                    </div>

                    <h3 style="color: #156f77;">Head & Sensory Functions</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Eyes:</b></label>
                            <p><?= htmlspecialchars($exam['Eyes'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Ears:</b></label>
                            <p><?= htmlspecialchars($exam['Ears'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <h3 style="color: #156f77;">Tracheal & Abdominal Palpation</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Tracheal Pinch:</b></label>
                            <p><?= htmlspecialchars($exam['TrachealPinch'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Abdominal Palpation:</b></label>
                            <p><?= htmlspecialchars($exam['AbdominalPalpation'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <h3 style="color: #156f77;">Lymph Nodes & BCS</h3>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Lymph Nodes (LN):</b></label>
                            <p><?= htmlspecialchars($exam['LN'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Body Condition Score (BCS):</b></label>
                            <p><?= htmlspecialchars($exam['BCS'] ?? 'Not Available', ENT_QUOTES) ?>/9</p>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <p>No physical exam records found.</p>
            <?php endif; ?>

            <br>
            <br>
            <hr>
            <br>
                
            <h2>Laboratory Tests</h2>
            <?php if (!empty($lab_tests)): ?>
                <ul>
                    <?php foreach ($lab_tests as $lab): ?>
                        <li>
                            <b><?= htmlspecialchars($lab['TestName'], ENT_QUOTES, 'UTF-8') ?>:</b>

                            <?php if (!empty($lab['FilePath'])): ?>
                                <br><a href="../<?= htmlspecialchars($lab['FilePath'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">📂 View Uploaded File</a>
                                <?php else: ?>
                                    <br><span style="color: gray;">No uploaded file</span>
                                <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No lab tests recorded.</p>
            <?php endif; ?>

            <br>
            <hr>
            <br>

            <h2>Diagnosis</h2>

            <?php if (!empty($diagnoses)): ?>
                <?php foreach ($diagnoses as $diagnosis): ?>
                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Diagnosis Type:</b></label>
                            <p><?= htmlspecialchars($diagnosis['DiagnosisType'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Prognosis:</b></label>
                            <p><?= htmlspecialchars($diagnosis['Prognosis'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Diagnosis:</b></label>
                            <p><?= htmlspecialchars($diagnosis['Diagnosis'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Treatment:</b></label>
                            <p><?= htmlspecialchars($diagnosis['Treatment'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <label><b>Status:</b></label>
                            <p><?= htmlspecialchars($diagnosis['Status'] ?? 'Not Available', ENT_QUOTES) ?></p>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <p>No diagnoses recorded.</p>
            <?php endif; ?>

            <br>
            <br>
            <hr>
            <br>

            <h2>Medications Given</h2>

            <div id="medications-container">
                <?php if (!empty($medications)): ?>
                    <div class="form-row">
                        <?php foreach ($medications as $medication): ?>
                            <div class="input-container">
                                <label><b>Medication:</b></label>
                                <p><?= htmlspecialchars($medication['MedicationName'] ?? 'Not Available', ENT_QUOTES); ?></p>
                            </div>

                            <div class="input-container">
                                <label><b>Dosage:</b></label>
                                <p><?= htmlspecialchars($medication['Dosage'] ?? 'Not Available', ENT_QUOTES); ?></p>
                            </div>

                            <div class="input-container">
                                <label><b>Duration:</b></label>
                                <p><?= htmlspecialchars($medication['Duration'] ?? 'Not Available', ENT_QUOTES); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No medications recorded.</p>
                <?php endif; ?>
            </div>

            <br>
            <br>
            <hr>
            <br>
            <h2>Follow-Up Appointments</h2>

            <?php if (!empty($followups)): ?>
                <div class="form-row">
                    <?php foreach ($followups as $index => $followup): ?>
                        <div class="input-container">
                            <label><b>Follow-Up Date:</b></label>
                            <p><?= htmlspecialchars($followup['FollowUpDate'] ?? 'Not Available', ENT_QUOTES); ?></p>
                        </div>

                        <div class="input-container">
                            <label><b>Notes:</b></label>
                            <p><?= htmlspecialchars($followup['FollowUpNotes'] ?? 'Not Available', ENT_QUOTES); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No follow-up appointments recorded.</p>
            <?php endif; ?>
        </form>
        </form>
    <script>
    function goBack() {
        // Get the URL parameters from the current page
        const urlParams = new URLSearchParams(window.location.search);
        const petId = urlParams.get("PetId"); // Extract PetId from current URL

        if (petId) {
            // Redirect to the pet profile with the correct pet_id
            window.location.href = `pet_profile.php?pet_id=${petId}`;
        } else {
            // If PetId is not found, go back to a default location
            window.history.back();
        }
    }
    </script>
    <script>
    async function downloadPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        // Select the entire main-content section
        const content = document.querySelector(".main-content");

        // Convert content to canvas with higher scale for better quality
        const canvas = await html2canvas(content, {
            scale: 3, // Higher scale improves quality
            scrollX: 0,
            scrollY: 0,
            windowWidth: document.documentElement.scrollWidth,
            windowHeight: document.documentElement.scrollHeight
        });

        const imgData = canvas.toDataURL("image/png");

        // Calculate height to fit all content properly
        const imgWidth = 190; // A4 width in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width; // Maintain aspect ratio

        // Adjust height dynamically
        let position = 10; // Start position on the PDF
        doc.addImage(imgData, "PNG", 10, position, imgWidth, imgHeight);

        // Save the PDF
        doc.save("Medical_Record.pdf");
    }
    </script>
   <script>
async function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');  // Create a new PDF document

    // Select the entire .main-content section
    const content = document.querySelector(".main-content");

    // Get Patient Name & Appointment Date from PHP variables
    const patientName = "<?= isset($record['PetName']) ? htmlspecialchars($record['PetName'], ENT_QUOTES) : 'Unknown_Patient'; ?>";
    const appointmentDate = "<?= isset($record['AppointmentDate']) ? htmlspecialchars($record['AppointmentDate'], ENT_QUOTES) : 'Unknown_Date'; ?>";

    // Convert the appointment date to a filename-friendly format (YYYY-MM-DD)
    const formattedDate = appointmentDate.replace(/[^0-9\-]/g, '');  // Remove unwanted characters

    // Convert the content to a canvas image
    const canvas = await html2canvas(content, {
        scale: 3,  // Higher scale improves the image quality
        scrollX: 0,
        scrollY: 0,
        windowWidth: document.documentElement.scrollWidth,
        windowHeight: document.documentElement.scrollHeight
    });

    const imgData = canvas.toDataURL("image/png");

    // A4 page dimensions (210mm x 297mm)
    const imgWidth = 190;  // A4 width in mm
    const imgHeight = (canvas.height * imgWidth) / canvas.width;  // Maintain aspect ratio

    // Start at the top of the first page
    let position = 10;

    // Add the first page image
    doc.addImage(imgData, "PNG", 10, position, imgWidth, imgHeight);

    // Check if content is too long for one page
    let totalPages = Math.ceil(imgHeight / 297); // 297mm is the height of an A4 page

    // If there are more pages, add them
    for (let i = 1; i < totalPages; i++) {
        doc.addPage();  // Add a new page to the PDF
        position = -(i * 297) + 10;  // Adjust position for new pages
        doc.addImage(imgData, "PNG", 10, position, imgWidth, imgHeight);  // Add the image for the new page
    }

    // Set the filename dynamically
    const fileName = `${patientName.replace(/\s+/g, '_')}_${formattedDate}.pdf`;

    // Save the PDF
    doc.save(fileName);
}
</script>
</body>
</html>