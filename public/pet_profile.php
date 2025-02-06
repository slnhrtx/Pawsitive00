<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

if (!isset($_GET['pet_id']) || !is_numeric($_GET['pet_id'])) {
    die("Invalid PetId.");
}
$pet_id = (int) $_GET['pet_id'];

try {
    $sql = "
        SELECT 
            VaccinationName AS Vaccine, 
            VaccinationDate AS Date, 
            Weight,
            Manufacturer, 
            LotNumber, 
            Notes 
        FROM PetVaccinations 
        WHERE PetId = :PetId
        ORDER BY Date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':PetId' => $pet_id]);
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT AllergyName, Notes
            FROM PetAllergies
            WHERE PetId = :PetId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':PetId' => $pet_id]);
    $allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT 
        m.MedicationName, 
        m.Dosage, 
        m.Duration, 
        m.DateCreated as Date
    FROM Medications m
    INNER JOIN PatientRecords pr ON m.RecordId = pr.RecordId
    WHERE pr.PetId = :PetId
    ORDER BY m.DateCreated DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':PetId' => $pet_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtPet = $pdo->prepare("
        SELECT 
            p.Name AS PetName, 
            p.CalculatedAge as Age, 
            p.ProfilePicture,
            sp.SpeciesName AS PetType, 
            p.Gender,
            p.Weight,
            CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName,
            o.Phone
        FROM Pets p
        INNER JOIN Species sp ON p.SpeciesId = sp.Id
        INNER JOIN Owners o ON p.OwnerId = o.OwnerId
        WHERE p.PetId = :PetId
    ");
    $stmtPet->execute([':PetId' => $pet_id]);
    $pet = $stmtPet->fetch(PDO::FETCH_ASSOC);

    try {
        $stmtConfinements = $pdo->prepare("
            SELECT 
                ConfinementDate, Weight, Diagnosis, IVFluidDripRate, Diet, Notes, CreatedAt
            FROM ConfinementRecords
            WHERE PetId = :PetId
            ORDER BY CreatedAt DESC
        ");
        $stmtConfinements->execute([':PetId' => $pet_id]);
        $confinements = $stmtConfinements->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $confinements = [];
    }

    try {
        $stmtFollowUps = $pdo->prepare("
            SELECT 
                FollowUpDate, FollowUpNotes
            FROM FollowUps
            WHERE PetId = :PetId
            ORDER BY CreatedAt DESC
        ");
        $stmtFollowUps->execute([':PetId' => $pet_id]);
        $followUps = $stmtFollowUps->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $followUps = [];
    }

    $stmtFollowUps = $pdo->prepare("
        SELECT 
            f.FollowUpDate, 
            f.FollowUpNotes
        FROM FollowUps f
        INNER JOIN PatientRecords pr ON f.RecordId = pr.RecordId
        WHERE pr.PetId = :PetId
        ORDER BY f.FollowUpDate DESC
    ");
    $stmtFollowUps->execute([':PetId' => $pet_id]);
    $followUps = $stmtFollowUps->fetchAll(PDO::FETCH_ASSOC);

    $stmtPreviousAppointments = $pdo->prepare("
    SELECT 
        a.AppointmentId,
        s.ServiceName, 
        a.AppointmentDate, 
        a.AppointmentTime
    FROM Appointments a
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE a.PetId = :PetId AND a.Status IN ('Done', 'Paid')
    AND s.ServiceName NOT IN ('Pet Vaccination & Deworming') 
    ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC
    ");
    $stmtPreviousAppointments->execute([':PetId' => $pet_id]);
    $previous_appointments = $stmtPreviousAppointments->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT 
            Diagnosis, 
            Status, 
            Treatment
        FROM Diagnoses d
        INNER JOIN PatientRecords pr ON d.RecordId = pr.RecordId
        WHERE pr.PetId = :PetId
        ORDER BY CreatedAt DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':PetId' => $pet_id]);
    $medical_conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("A database error occurred: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/pet_profile.css">
    <style>
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .success-toast {
            background-color: #198754;
            /* Success green */
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fade-in 0.5s ease, fade-out 0.5s ease 3s;
            opacity: 1;
        }

        .success-toast i {
            font-size: 16px;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fade-out {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
            <ul class="nav-links">
                <li><a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                <li class="active"><a href="record.php">
                        <img src="../assets/images/Icons/Record 3.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>
    <div class="main-content">
        <button class="btn" onclick="goToRecord()">Back</button>
        <h1>Pet Profile</h1>
        <div class="profile">
            <div class="profile-image">
                <img src="<?= htmlspecialchars(!empty($pet['ProfilePicture']) ? $pet['ProfilePicture'] : '../assets/images/Icons/Profile User.png'); ?>"
                    alt="<?= htmlspecialchars($pet['PetName']); ?>'s Profile Picture">
            </div>
            <div class="profile-details-horizontal">
                <h2><?= htmlspecialchars($pet['PetName']); ?></h2>
                <p>
                    <span><strong>Age:</strong> <?= htmlspecialchars($pet['Age'] ?? 'No information found'); ?></span>
                    <span><strong>Type:</strong>
                        <?= htmlspecialchars($pet['PetType'] ?? 'No information found'); ?></span>
                    <span><strong>Gender:</strong>
                        <?= htmlspecialchars($pet['Gender'] ?? 'No information found'); ?></span>
                    <span><strong>Weight:</strong>
                        <?= htmlspecialchars($pet['Weight'] ?? 'No information found'); ?></span>
                    <br>
                    <span><strong>Owner's Name:</strong>
                        <?= htmlspecialchars($pet['OwnerName'] ?? 'No information found'); ?></span>
                    <span><strong>Owner's Contact No.:</strong>
                        <?= htmlspecialchars($pet['Phone'] ?? 'No information found'); ?></span>
                </p>
            </div>
        </div>
        <section class="previous-appointments">
            <div class="section-header">
                <h2>Previous Appointments:</h2>
                <!--<div class="action-buttons">
                    <form method="GET" action="your_page.php" class="filter-form">
                        <label for="appointment_date">Filter by Appointment Date:</label>
                        <input type="date" id="appointment_date" name="appointment_date" value="<?= htmlspecialchars($_GET['appointment_date'] ?? '') ?>">
                        <button type="submit" class="btn">Filter</button>
                        <div class="see-all-button">
                            <button class="btn" onclick="location.href='add_staff.php'">See All</button>
                        </div>
                    </form>
                </div>-->
            </div>
            <div class="prescription">
                <table>
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Appointment Date</th>
                            <th>Appointment Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($previous_appointments)): ?>
                            <?php foreach ($previous_appointments as $appointment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appointment['ServiceName']) ?></td>
                                    <td><?= htmlspecialchars($appointment['AppointmentDate']) ?></td>
                                    <td><?= htmlspecialchars($appointment['AppointmentTime']) ?></td>
                                    <td>
                                        <a href="consultation_record.php?AppointmentId=<?= urlencode($appointment['AppointmentId']) ?>&PetId=<?= urlencode($pet_id) ?>"
                                            class="btn">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No previous appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if (!empty($confinements)): ?>
            <section class="consultation">
                <h2>Confinement Records</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Weight</th>
                            <th>Diagnosis</th>
                            <th>IV Fluid Drip Rate</th>
                            <th>Diet</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confinements as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['ConfinementDate']) ?></td>
                                <td><?= htmlspecialchars($record['Weight']) ?></td>
                                <td><?= htmlspecialchars($record['Diagnosis']) ?></td>
                                <td><?= htmlspecialchars($record['IVFluidDripRate']) ?></td>
                                <td><?= htmlspecialchars($record['Diet']) ?></td>
                                <td><?= htmlspecialchars($record['Notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
        <section class="consultation">
            <h2>Follow-Up</h2>
            <div class="prescription">
                <table>
                    <thead>
                        <tr>
                            <th>FollowUp Date</th>
                            <th>FollowUp Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($followUps)): ?>
                            <?php foreach ($followUps as $followUp): ?>
                                <tr>
                                    <td><?= htmlspecialchars(!empty($followUp['FollowUpDate']) ? $followUp['FollowUpDate'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($followUp['FollowUpNotes']) ? $followUp['FollowUpNotes'] : 'No information found') ?>
                                    </td>
                                    <td>
                                        <button class="remind-btn"
                                            onclick="sendReminder('<?= $pet['Phone'] ?>', '<?= $pet['PetName'] ?>', '<?= $pet['OwnerName'] ?>', '<?= $followUp['FollowUpDate'] ?>', '<?= addslashes($followUp['FollowUpNotes']) ?>')">
                                            Remind Me
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No follow up found for this pet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="consultation">
            <h2>Vaccines</h2>
            <button class="btn add-vaccine-btn" onclick="redirectToAddVaccine()">Add Vaccine</button>
            <div class="prescription">
                <table>
                    <thead>
                        <tr>
                            <th>Weight</th>
                            <th>Vaccination Name</th>
                            <th>Date</th>
                            <th>Manufacturer</th>
                            <th>Lot Number</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vaccinations)): ?>
                            <?php foreach ($vaccinations as $vaccination): ?>
                                <tr>
                                    <td><?= htmlspecialchars(!empty($vaccination['Weight']) ? $vaccination['Weight'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($vaccination['Vaccine']) ? $vaccination['Vaccine'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($vaccination['Date']) ? $vaccination['Date'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($vaccination['Manufacturer']) ? $vaccination['Manufacturer'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($vaccination['LotNumber']) ? $vaccination['LotNumber'] : 'No information found') ?>
                                    </td>
                                    <td><?= htmlspecialchars(!empty($vaccination['Notes']) ? $vaccination['Notes'] : 'No information found') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No vaccination records found for this pet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <br>
        <h2>Pet Weight Over Time</h2>
        <canvas id="weightChart" width="800" height="400"></canvas>
        <!--<section class="consultation">
            <h2>Allergies</h2>
            <div class="prescription">
                <table>
                    <thead>
                            <th>Allergy Name</th>
                            <th>Notes</th>
                    </thead>
                    <tbody>
                        <?php if (!empty($allergies)): ?>
                            <?php foreach ($allergies as $allergy): ?>
                                <tr>
                                    <td><?= htmlspecialchars($allergy['AllergyName']) ?></td>
                                    <td><?= htmlspecialchars($allergy['Notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">The pet has no allergies.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>-->
    </div>
    <div id="toastContainer" class="toast-container">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="success-toast" id="successToast">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('weightChart').getContext('2d');
            const petId = new URLSearchParams(window.location.search).get('pet_id');

            fetch(`../src/fetch_weight.php?pet_id=${petId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        const labels = data.data.map(item => item.RecordedAt);
                        const weights = data.data.map(item => parseFloat(item.Weight));

                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Weight (kg)',
                                    data: weights,
                                    borderColor: 'rgb(75, 192, 192)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    fill: true,
                                    tension: 0.1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function (tooltipItem) {
                                                return `Weight: ${tooltipItem.raw} kg`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        title: { display: true, text: 'Date' }
                                    },
                                    y: {
                                        title: { display: true, text: 'Weight (kg)' },
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    } else {
                        ctx.font = "16px Poppins";
                        ctx.fillStyle = "gray";
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);  // Clear canvas if previously rendered
                        ctx.fillText("No weight record found.", ctx.canvas.width / 2, ctx.canvas.height / 2);
                    }
                })
                .catch(error => console.error('Error fetching weight data:', error));
        });
    </script>
    <script>
        function sendReminder(phone, petName, ownerName, followUpDate, notes) {
            const message = `Hello ${ownerName}, this is a reminder from Pawsitive. 
            ${petName} has a follow-up on ${followUpDate}. Notes: ${notes}.`;

            fetch('../src/send_sms.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `phone=${encodeURIComponent(phone)}&message=${encodeURIComponent(message)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Success!', data.message, 'success');
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error!', 'Failed to send SMS.', 'error');
                    console.error('Error:', error);
                });
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const toast = document.getElementById("successToast");
            if (toast) {
                setTimeout(() => {
                    toast.remove();
                }, 4000);
            }
        });
    </script>
    <script>
        function openConfinementDialog() {
            Swal.fire({
                title: 'Confirm Confinement',
                text: 'Do you want to confine the pet?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6', // Blue color for "Yes" button
                cancelButtonColor: '#d33', // Red color for "Cancel" button
                confirmButtonText: 'Yes, Confine Pet',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Call the confinement function if confirmed
                    confirmConfinement();
                } else {
                    // Optional: Do something if user cancels (e.g., log an action or display a message)
                    closeConfinementDialog();
                }
            });
        }

        function confirmConfinement() {
            const petId = <?= json_encode($pet_id); ?>;

            // Show a success alert
            Swal.fire({
                title: 'Confined!',
                text: 'The pet has been confined successfully.',
                icon: 'success',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK',
            }).then(() => {
                window.location.href = `confine_pet.php?PetId=${petId}`;
            });
        }

        function closeConfinementDialog() {
            console.log('Confinement canceled.');
        }
    </script>
    <script>
        function redirectToAddVaccine() {
            window.location.href = "add_vaccine.php?pet_id=<?= htmlspecialchars($pet_id) ?>";
        }
    </script>
    <script>
        function goToRecord() {
            // Get pet_id from current URL
            const urlParams = new URLSearchParams(window.location.search);
            const petId = urlParams.get("pet_id");

            if (petId) {
                // Redirect to record.php with pet_id
                window.location.href = `record.php?pet_id=${petId}`;
            } else {
                // Redirect to record.php without pet_id (fallback)
                window.location.href = `record.php`;
            }
        }
    </script>
</body>

</html>