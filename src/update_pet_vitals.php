<?php
require '../config/dbh.inc.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$appointment_id = $data['appointment_id'] ?? null;
$pet_id = $data['pet_id'] ?? null;
$weight = $data['weight'] ?? null;
$temperature = $data['temperature'] ?? null;

$response = ['success' => false];

if ($appointment_id && $pet_id && $weight && $temperature) {
    try {
        $stmt = $pdo->prepare("UPDATE Pets SET Weight = ?, Temperature = ? WHERE PetId = ?");
        $stmt->execute([$weight, $temperature, $pet_id]);

        $response['success'] = true;
    } catch (PDOException $e) {
        error_log("Error updating vitals: " . $e->getMessage());
        $response['error'] = 'Database error';
    }
} else {
    $response['error'] = 'Invalid data provided';
}

echo json_encode($response);