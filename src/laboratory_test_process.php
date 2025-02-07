<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require '../config/dbh.inc.php';

if (!isset($_SESSION['UserId'])) {
    $_SESSION['errors']['auth'] = "Unauthorized access.";
    header("Location: staff_login.php");
    exit();
}

$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$lab_tests = $_POST['lab-tests'] ?? [];

$blood_test_detail = $_POST['blood-test-detail'] ?? null;
$imaging_other_detail = $_POST['imaging-other-detail'] ?? null;
$microbiology_other_detail = $_POST['microbiology-other-detail'] ?? null;
$etc_detail = $_POST['etc-detail'] ?? null;

$_SESSION['lab_exam_data'] = $_POST;

if (!$pet_id || !$appointment_id) {
    $_SESSION['errors']['missing_data'] = "Missing required information.";
    header("Location: ../public/patient_records.php?error=missing_data&appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

try {
    $pdo->beginTransaction();

    // Fetch RecordId
    $stmt = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
    $stmt->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("No record found for the given Appointment ID and Pet ID.");
    }

    $record_id = $record['RecordId'];

    // Delete previous lab tests for this record
    $delete_stmt = $pdo->prepare("DELETE FROM LaboratoryTests WHERE RecordId = :record_id");
    $delete_stmt->execute([':record_id' => $record_id]);

    if (empty($lab_tests)) {
        throw new Exception("No laboratory tests were selected.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO LaboratoryTests (RecordId, TestType, TestName, TestDetail, FilePath) 
        VALUES (:record_id, :test_type, :test_name, :test_detail, :file_path)
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare the insert statement.");
    }

    $upload_dir = '../uploads/lab_tests/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    foreach ($lab_tests as $index => $test) {
        $test_type = null;
        $test_name = $test;
        $test_detail = null;
        $file_path = null;
    
        if (in_array($test, ['CBC', 'Blood Chemistry', 'Blood Smear', 'Other Blood Test'])) {
            $test_type = 'Blood';
            $test_detail = ($test === 'Other Blood Test') ? $blood_test_detail : null;
        } elseif (in_array($test, ['X-ray', 'Ultrasound', 'CT Scan', 'Other Imaging'])) {
            $test_type = 'Imaging';
            $test_detail = ($test === 'Other Imaging') ? $imaging_other_detail : null;
        } elseif (in_array($test, ['Ear Swab', 'Skin Scrape', 'Fungal Test', 'Other Microbiology'])) {
            $test_type = 'Microbiology';
            $test_detail = ($test === 'Other Microbiology') ? $microbiology_other_detail : null;
        } elseif ($test === 'ETC') {
            $test_type = 'Other';
            $test_name = 'Other Test';
            $test_detail = $etc_detail;
        } else {
            $test_type = 'Other';
        }

        if (!empty($_FILES['lab-results']['name'][$index])) {
            $original_file_name = $_FILES['lab-results']['name'][$index];
            $file_tmp = $_FILES['lab-results']['tmp_name'][$index];
            $file_error = $_FILES['lab-results']['error'][$index];
    
             /* 🔍 File Upload Debugging
            echo "Processing File: $original_file_name <br>";
            echo "Temp Path: $file_tmp <br>";
            echo "File Error Code: $file_error <br>";
            echo "File Size: $file_size bytes <br>";
            flush();*/
            
            if ($file_error === UPLOAD_ERR_OK) {
                $file_ext = pathinfo($original_file_name, PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
                if (in_array(strtolower($file_ext), $allowed_extensions)) {
                    $new_file_name = uniqid("lab_") . "." . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $file_path = str_replace('../', '', $file_path); // ✅ Store relative path
                    }
                }
            }
        }

        $stmt->execute([
            ':record_id' => $record_id,
            ':test_type' => $test_type,
            ':test_name' => $test_name,
            ':test_detail' => $test_detail,
            ':file_path' => $file_path
        ]);
    }

    $pdo->commit();

    $_SESSION['success_lab_exam'] = "Laboratory Examination saved successfully.";
    header("Location: ../public/patient_records.php?success=lab_saved&appointment_id=$appointment_id&pet_id=$pet_id");
    exit();

} catch (Exception $e) {
    try {
        $pdo->rollBack();
    } catch (Exception $rollbackError) {
        $_SESSION['errors']['rollback'] = "Rollback Error: " . $rollbackError->getMessage();
    }

    $_SESSION['errors']['db'] = "Database Error: " . $e->getMessage();
    header("Location: ../public/patient_records.php?error=db_error&appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}
?>