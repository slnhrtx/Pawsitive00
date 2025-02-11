<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
header('Content-Type: application/json');

// Read and decode JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['appointment_id'], $data['pet_id'], $data['weight'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];
$petId = (int)$data['pet_id'];
$weight = (float)$data['weight'];

// Validate weight
if ($weight <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid weight. Must be greater than zero.']);
    exit;
}

try {
    // Update pet's weight
    $stmt = $pdo->prepare("UPDATE Pets SET Weight = :weight WHERE PetId = :pet_id");
    $stmt->execute([':weight' => $weight, ':pet_id' => $petId]);

    $stmtWeight = $pdo->prepare("INSERT INTO PetWeights (PetId, Weight, RecordedAt) VALUES (:pet_id, :weight, NOW())");
    $stmtWeight->execute([':pet_id' => $petId, ':weight' => $weight]);

    echo json_encode(['success' => true, 'message' => 'Weight recorded successfully.']);
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
