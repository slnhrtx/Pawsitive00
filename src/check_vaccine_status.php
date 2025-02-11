<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
header('Content-Type: application/json');

// Validate input parameters
if (!isset($_GET['appointment_id'], $_GET['pet_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$appointmentId = (int) $_GET['appointment_id'];
$petId = (int) $_GET['pet_id'];

try {
    // ✅ Check if the pet's weight has already been recorded
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS recordCount 
        FROM PetWeights 
        WHERE PetId = :pet_id 
          AND RecordedAt >= (SELECT AppointmentDate FROM Appointments WHERE AppointmentId = :appointment_id)
    ");
    $stmt->execute([
        ':pet_id' => $petId,
        ':appointment_id' => $appointmentId
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['recordCount'] > 0) {
        echo json_encode(['success' => true, 'alreadyRecorded' => true, 'message' => 'Weight already recorded.']);
    } else {
        echo json_encode(['success' => true, 'alreadyRecorded' => false, 'message' => 'Weight not recorded yet.']);
    }
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>