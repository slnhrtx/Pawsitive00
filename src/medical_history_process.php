<?php
session_start();
require '../config/dbh.inc.php'; // Ensure database connection

// Check if user is logged in
if (!isset($_SESSION['UserId'])) {
    $_SESSION['errors']['auth'] = "Unauthorized access.";
    header("Location: staff_login.php");
    exit();
}

// Retrieve form data with validation
$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$last_vaccination_date = !empty($_POST['last_vaccination']) ? $_POST['last_vaccination'] : null;
$vaccines_given = !empty($_POST['Vaccine']) ? $_POST['Vaccine'] : null;
$custom_vaccine = !empty($_POST['VaccineInput']) ? $_POST['VaccineInput'] : null;
$last_deworming_date = !empty($_POST['last_deworming']) ? $_POST['last_deworming'] : null;
$dewormer_used = !empty($_POST['Dewormer']) ? $_POST['Dewormer'] : null;
$custom_dewormer = !empty($_POST['DewormerInput']) ? $_POST['DewormerInput'] : null;
$spayed_neutered = isset($_POST['SpayedNeutered']) ? (int)$_POST['SpayedNeutered'] : null;
$last_heat_cycle = !empty($_POST['LastHeatCycle']) ? $_POST['LastHeatCycle'] : null;

// Additional Fields
$flea_tick_prevention = $_POST['FleaTickPrevention'] ?? null;
$heartworm_prevention = $_POST['HeartwormPrevention'] ?? null;
$genetic_conditions = $_POST['GeneticConditions'] ?? null;
$food_allergies = $_POST['FoodAllergies'] ?? null;
$medication_allergies = $_POST['MedicationAllergies'] ?? null;
$past_illnesses = $_POST['PastIllnesses'] ?? null;
$past_surgeries = $_POST['PastSurgeries'] ?? null;
$hospitalizations = $_POST['Hospitalizations'] ?? null;
$current_medications = $_POST['CurrentMedications'] ?? null;
$behavioral_issues = $_POST['BehavioralIssues'] ?? null;

// Ensure required fields exist
if (!$pet_id || !$appointment_id) {
    $_SESSION['errors']['missing_data'] = "Missing required information.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

// Use custom input if 'Other' was selected
if ($vaccines_given === 'Other') {
    $vaccines_given = $custom_vaccine;
}
if ($dewormer_used === 'Other') {
    $dewormer_used = $custom_dewormer;
}

try {
    $pdo->beginTransaction();

    // Check if medical history exists for this PetId and AppointmentId
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM MedicalHistory WHERE PetId = :pet_id AND AppointmentId = :appointment_id
    ");
    $stmtCheck->execute([
        ':pet_id' => $pet_id,
        ':appointment_id' => $appointment_id
    ]);
    $exists = $stmtCheck->fetchColumn();

    if ($exists > 0) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE MedicalHistory
            SET LastVaccinationDate = :last_vaccination_date,
                VaccinesGiven = :vaccines_given,
                LastDewormingDate = :last_deworming_date,
                DewormerUsed = :dewormer_used,
                SpayedNeutered = :spayed_neutered,
                LastHeatCycle = :last_heat_cycle,
                FleaTickPrevention = :flea_tick_prevention,
                HeartwormPrevention = :heartworm_prevention,
                GeneticConditions = :genetic_conditions,
                FoodAllergies = :food_allergies,
                MedicationAllergies = :medication_allergies,
                PastIllnesses = :past_illnesses,
                PastSurgeries = :past_surgeries,
                Hospitalizations = :hospitalizations,
                CurrentMedications = :current_medications,
                BehavioralIssues = :behavioral_issues
            WHERE PetId = :pet_id AND AppointmentId = :appointment_id
        ");
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO MedicalHistory (
                AppointmentId, PetId, LastVaccinationDate, VaccinesGiven, LastDewormingDate,
                DewormerUsed, SpayedNeutered, LastHeatCycle, FleaTickPrevention, HeartwormPrevention,
                GeneticConditions, FoodAllergies, MedicationAllergies, PastIllnesses, PastSurgeries,
                Hospitalizations, CurrentMedications,  BehavioralIssues
            ) VALUES (
                :appointment_id, :pet_id, :last_vaccination_date, :vaccines_given, :last_deworming_date,
                :dewormer_used, :spayed_neutered, :last_heat_cycle, :flea_tick_prevention, :heartworm_prevention,
                :genetic_conditions, :food_allergies, :medication_allergies, :past_illnesses, :past_surgeries,
                :hospitalizations, :current_medications, :behavioral_issues
            )
        ");
    }

    // Execute the query
    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':pet_id' => $pet_id,
        ':last_vaccination_date' => $last_vaccination_date,
        ':vaccines_given' => $vaccines_given,
        ':last_deworming_date' => $last_deworming_date,
        ':dewormer_used' => $dewormer_used,
        ':spayed_neutered' => $spayed_neutered,
        ':last_heat_cycle' => $last_heat_cycle,
        ':flea_tick_prevention' => $flea_tick_prevention,
        ':heartworm_prevention' => $heartworm_prevention,
        ':genetic_conditions' => $genetic_conditions,
        ':food_allergies' => $food_allergies,
        ':medication_allergies' => $medication_allergies,
        ':past_illnesses' => $past_illnesses,
        ':past_surgeries' => $past_surgeries,
        ':hospitalizations' => $hospitalizations,
        ':current_medications' => $current_medications,
        ':behavioral_issues' => $behavioral_issues
    ]);

    $pdo->commit();

    // Store form data in session to retain input after submission
    $_SESSION['medical_history_data'] = $_POST;
    unset($_SESSION['errors']);
    $_SESSION['success_medical_history'] = "Medical History saved successfully.";

    header("Location: ../public/patient_records.php?appointment_id=" . urlencode($appointment_id) . "&pet_id=" . urlencode($pet_id));
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['errors']['db'] = "Database Error: " . $e->getMessage();
    header("Location: ../public/patient_records.php?appointment_id=" . urlencode($appointment_id) . "&pet_id=" . urlencode($pet_id));
    exit();
}
?>
