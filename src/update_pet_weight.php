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

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Weight updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or pet not found.']);
    }
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
