<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['appointment_id'], $data['pet_id'], $data['weight'], $data['temperature'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];
$petId = (int)$data['pet_id'];
$weight = (float)$data['weight'];
$temperature = (float)$data['temperature'];

if ($weight <= 0 || $temperature < 30 || $temperature > 45) {
    echo json_encode(['success' => false, 'message' => 'Invalid weight or temperature.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ✅ Check if the pet's vitals were already recorded today
    $stmtCheck = $pdo->prepare("SELECT 1 FROM PetWeights WHERE PetId = :pet_id AND DATE(RecordedAt) = CURDATE() LIMIT 1");
    $stmtCheck->execute([':pet_id' => $petId]);
    $alreadyRecorded = $stmtCheck->fetchColumn();

    if ($alreadyRecorded) {
        echo json_encode(['success' => false, 'alreadyRecorded' => true, 'message' => 'Vitals already recorded for today.']);
        $pdo->rollBack();
        exit;
    }

    // ✅ Record new weight entry
    $stmtWeight = $pdo->prepare("INSERT INTO PetWeights (PetId, Weight, RecordedAt) VALUES (:pet_id, :weight, NOW())");
    $stmtWeight->execute([':pet_id' => $petId, ':weight' => $weight]);

    // ✅ Update Pets table with latest weight and temperature
    $stmtPet = $pdo->prepare("UPDATE Pets SET Weight = :weight, Temperature = :temperature WHERE PetId = :pet_id");
    $stmtPet->execute([':weight' => $weight, ':temperature' => $temperature, ':pet_id' => $petId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Vitals updated successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>