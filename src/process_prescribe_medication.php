<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

session_start(); // Ensure session is started

// Get appointment and pet IDs
$appointment_id = $_POST['appointment_id'] ?? null;
$pet_id = $_POST['pet_id'] ?? null;

// Ensure required fields are present
if (!$appointment_id || !$pet_id) {
    $_SESSION['error'] = "Invalid appointment or pet ID.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

// Check if medications are provided
if (!isset($_POST['medications']) || empty($_POST['medications'])) {
    $_SESSION['error'] = "Please select at least one medication.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

// Retrieve form data
$medications = $_POST['medications'];
$dosages = $_POST['dosages'] ?? [];
$durations = $_POST['durations'] ?? [];
$customMedications = $_POST['custom-medications'] ?? [];
$customDosages = $_POST['custom-dosages'] ?? [];
$customDurations = $_POST['custom-durations'] ?? [];

try {
    $pdo->beginTransaction();

    // Fetch `RecordId` from `PatientRecords` using `AppointmentId` and `PetId`
    $stmtRecord = $pdo->prepare("
        SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id LIMIT 1
    ");
    $stmtRecord->execute([
        ':appointment_id' => $appointment_id,
        ':pet_id' => $pet_id
    ]);
    $recordId = $stmtRecord->fetchColumn();

    if (!$recordId) {
        $_SESSION['error'] = "No record found for this appointment.";
        header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
        exit();
    }

    // Prepare statement to insert prescribed medications
    $stmt = $pdo->prepare("
        INSERT INTO PrescribeMedications (RecordId, MedicationName, Dosage, Duration)
        VALUES (:record_id, :medication_name, :dosage, :duration)
    ");

    // Clear previous session data
    $_SESSION['medication_data'] = [
        'medications' => [],
        'dosages' => [],
        'durations' => [],
        'custom-medications' => [],
        'custom-dosages' => [],
        'custom-durations' => []
    ];

    foreach ($medications as $index => $medication) {
        // Handle "Other" option inputs
        $medication_name = ($medication === "Other") ? ($customMedications[$index] ?? '') : $medication;
        $dosage = ($dosages[$index] === "Other") ? ($customDosages[$index] ?? '') : $dosages[$index];
        $duration = ($durations[$index] === "Other") ? ($customDurations[$index] ?? '') : $durations[$index];

        if (empty($medication_name)) {
            continue; // Skip empty inputs
        }

        // Insert into database
        $stmt->execute([
            ':record_id' => $recordId,
            ':medication_name' => $medication_name,
            ':dosage' => $dosage,
            ':duration' => $duration
        ]);

        // Store in session for persistence
        $_SESSION['medication_data']['medications'][] = $medication_name;
        $_SESSION['medication_data']['dosages'][] = $dosage;
        $_SESSION['medication_data']['durations'][] = $duration;
    }

    $pdo->commit();
    $_SESSION['success'] = "Prescribed medications saved successfully.";
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to the patient record page
header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
exit();
?>