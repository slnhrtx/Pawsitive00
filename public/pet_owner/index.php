<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../config/dbh.inc.php';
session_start();

if (!isset($_SESSION['LoggedIn'])) {
    echo "User not logged in.";
    exit;
}

// Define owner ID from session
$owner_id = $_SESSION['OwnerId'] ?? null;
$userName = $_SESSION['OwnerName'];

if (!$owner_id) {
    echo "Owner ID not found.";
    exit;
}

try {
    // Fetch pets linked to the owner
    $query = "
        SELECT 
            p.PetId, 
            p.Name AS PetName, 
            s.SpeciesName AS PetType, 
            p.Gender, 
            p.CalculatedAge AS Age, 
            b.BreedName AS Breed
        FROM Pets p
        INNER JOIN Species s ON p.SpeciesId = s.Id
        INNER JOIN Breeds b ON p.Breed = b.BreedId
        WHERE p.OwnerId = :owner_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Appointments for the Owner
    $appointmentQuery = "
        SELECT 
            a.AppointmentId, 
            p.Name AS PetName, 
            s.ServiceName AS Service, 
            a.AppointmentTime AS Time, 
            a.Status
        FROM Appointments a
        INNER JOIN Pets p ON a.PetId = p.PetId
        INNER JOIN Services s ON a.ServiceId = s.ServiceId
        WHERE p.OwnerId = :owner_id
        ORDER BY a.AppointmentTime DESC
    ";

    $appointmentStmt = $pdo->prepare($appointmentQuery);
    $appointmentStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $appointmentStmt->execute();
    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="pet_owner.css">
    
    <style>
        .appointments-section {
            width: 100%;
            max-width: 800px; /* Adjust based on your preference */
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            background-color: #f5f5f5;
        }
        
        .appointments-container {
            max-height: 400px;
            overflow-y: auto; /* Scrollable table */
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table th, .appointments-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #afadad;
        }

        .appointments-table th {
            background-color: var(--color-2);
            color: #333;
        }

        .status {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .status.pending { background-color: #FFB200; color: #fff; } 
        .status.done { background-color: #00A86B; color: #fff; } 
        .status.confirmed { background-color: #4C5FD5; color: #fff; } 
        .status.cancelled { background-color: #D72638; color: #fff; } 
    </style>
</head>

<body>
<header>
    <nav>
        <div class="logo">
            <img src="../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <ul class="nav-links">
            <li><a href="#home" class="active">Home</a></li>
            <li><a href="appointment/book_appointment.php">Appointment</a></li>
            <li><a href="pet/pet_add.php">Pets</a></li>
            <li><a href="./record/pet_record.php">Record</a></li>
            <li><a href="invoice/invoice.php">Billing</a></li>
        </ul>
    </nav>
</header>

<main>
    <section class="hero" id="home">
        <div class="hero-text">
            <h1>Welcome!</h1>
              <h2><?= htmlspecialchars($userName); ?></h2>
            <p id="current-date"></p>
        </div>
        <img src="../../assets/images/Icons/Pet pic 1.png" alt="Dog and Cat">
    </section>

    <!-- Pets Section -->
    <h2 class="section-headline">Your Pets</h2>
    <section id="pets-section" class="pets-section">
        <div class="pets-container">
            <?php if (!empty($pets)): ?>
                <?php foreach ($pets as $pet): ?>
                    <div class="pet-card">
                      <div class="pet-avatar">
                          <label for="upload-<?= $pet['PetId'] ?>">
                              <img id="preview-<?= $pet['PetId'] ?>" src="../../assets/images/Icons/Profile User.png" alt="Pet Avatar">
                          </label>
                          <input type="file" id="upload-<?= $pet['PetId'] ?>" class="file-input" data-pet-id="<?= $pet['PetId'] ?>" accept="image/*" hidden>
                      </div>
                        <div class="pet-details">
                            <div class="detail"><span class="label">Name</span><span class="value"><?= htmlspecialchars($pet['PetName']); ?></span></div>
                            <div class="detail"><span class="label">Pet Type</span><span class="value"><?= htmlspecialchars($pet['PetType']); ?></span></div>
                            <div class="detail"><span class="label">Gender</span><span class="value"><?= htmlspecialchars($pet['Gender']); ?></span></div>
                            <div class="detail"><span class="label">Age</span><span class="value"><?= htmlspecialchars($pet['Age']); ?></span></div>
                            <div class="detail"><span class="label">Breed</span><span class="value"><?= htmlspecialchars($pet['Breed']); ?></span></div>
                        </div>
                        <div class="three-dot-menu">
                            <button class="menu-button">...</button>
                            <div class="menu-dropdown">
                            <ul>
                                <li><a href="#">Edit</a></li>
                                <li><a href="#">View Record</a></li>
                                <li><a href="#" style="color: red;">Delete</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-pets-text">No pets found.</p>
            <?php endif; ?>
        </div>
    </section>

    <br>
    <!-- New Appointments Section -->
    <h2 class="section-headline">Your Appointments</h2>
    <section id="appointments-section" class="appointments-section">
        <div class="appointments-container">
            <?php if (!empty($appointments)): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Pet Name</th>
                            <th>Service</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['PetName']); ?></td>
                                <td><?= htmlspecialchars($appointment['Service']); ?></td>
                                <td><?= htmlspecialchars($appointment['Time']); ?></td>
                                <td>
                                    <span class="status <?= strtolower($appointment['Status']); ?>">
                                        <?= htmlspecialchars($appointment['Status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-appointments-text">No appointments found.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<br>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("current-date").textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    });
</script>

<script>
    document.querySelectorAll('.file-input').forEach(input => {
    input.addEventListener('change', function () {
        const petId = this.dataset.petId;
        const file = this.files[0];

        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('preview-' + petId).src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Create FormData to send to backend
            const formData = new FormData();
            formData.append('petId', petId);
            formData.append('profilePicture', file);

            // Send image to backend via AJAX
            fetch('../../src/upload_pet_avatar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile picture updated successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => console.error('Error:', error));
        }
    });
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
      const profileIcon = document.querySelector('.profile-icon');
      const dropdownContent = document.querySelector('.dropdown-content');

      profileIcon.addEventListener('click', function() {
        dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
      });

      window.addEventListener('click', function(event) {
        if (!event.target.matches('.profile-icon')) {
          if (dropdownContent.style.display === 'block') {
            dropdownContent.style.display = 'none';
          }
        }
      });
    });
  </script>

</body>
</html>