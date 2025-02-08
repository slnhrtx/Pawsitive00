<?php
require '../config/dbh.inc.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePicture']) && isset($_POST['petId'])) {
    $petId = $_POST['petId'];
    $file = $_FILES['profilePicture'];

    // Define the upload directory (Ensure absolute path)
    $uploadDir = realpath("../uploads/pet_avatars/") . "/";

    // Create directory if it doesn't exist
    if (!$uploadDir) {
        mkdir("../uploads/pet_avatars/", 0777, true);
        chmod("../uploads/pet_avatars/", 0777);
        $uploadDir = realpath("../uploads/pet_avatars/") . "/";
    }

    // Check if directory exists
    if (!is_dir($uploadDir)) {
        error_log("🚨 Upload directory does not exist: " . $uploadDir);
        echo json_encode(['success' => false, 'error' => 'Upload directory does not exist.']);
        exit;
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        error_log("🚨 Upload directory is not writable: " . $uploadDir);
        chmod($uploadDir, 0777);
        echo json_encode(['success' => false, 'error' => 'Upload directory is not writable.']);
        exit;
    }

    error_log("✅ Upload directory is valid and writable: " . $uploadDir);

    // Debugging: Check temp file path
    error_log("Temp file: " . $file['tmp_name']);
    error_log("Target directory: " . $uploadDir);

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("🚨 File upload error: Code " . $file['error']);
        echo json_encode(['success' => false, 'error' => 'File upload error. Code: ' . $file['error']]);
        exit;
    }

    // Validate file type (Only allow images)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("🚨 Invalid file type: " . $file['type']);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
        exit;
    }

    // Validate file size (Limit to 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxFileSize) {
        error_log("🚨 File too large: " . $file['size'] . " bytes");
        echo json_encode(['success' => false, 'error' => 'File too large. Max size is 5MB.']);
        exit;
    }

    // Create a unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'pet_' . $petId . '_' . time() . '.' . $fileExtension;
    $uploadFilePath = $uploadDir . $fileName;

    // Attempt file move
    if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
        chmod($uploadFilePath, 0644); // Secure uploaded file

        // Store image path in database
        $stmt = $pdo->prepare("UPDATE Pets SET ProfilePicture = :filePath WHERE PetId = :petId");
        $stmt->bindParam(':filePath', $fileName);
        $stmt->bindParam(':petId', $petId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'imagePath' => $fileName]);
        } else {
            error_log("🚨 Database update failed.");
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
    } else {
        error_log("❌ File move failed: " . $file['tmp_name'] . " to " . $uploadFilePath);
        echo json_encode(['success' => false, 'error' => 'File move failed.']);
    }
} else {
    error_log("🚨 Invalid request.");
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?>