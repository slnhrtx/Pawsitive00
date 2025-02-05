<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (!hasPermission($pdo,'View Appointments')) {
    exit("You do not have permission to access this page.");
}

$events = [];
$booked_times_by_date = [];

try {
    $query = "
        SELECT 
            a.AppointmentId, 
            a.AppointmentDate, 
            a.AppointmentTime, 
            p.PetId, 
            p.Name AS PetName, 
            a.Status,
            s.ServiceName
        FROM Appointments AS a
        INNER JOIN Pets AS p ON a.PetId = p.PetId
        INNER JOIN Services AS s ON a.ServiceId = s.ServiceId
        WHERE a.Status IN ('Pending', 'Confirmed', 'Done')  -- Exclude 'Paid'
        ORDER BY a.AppointmentDate ASC
        LIMIT 10;
    "; 
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $appointments = [];
}

try {
    $events_stmt = $pdo->prepare("
        SELECT 
            Appointments.AppointmentId AS id,
            Services.ServiceName AS title,
            Appointments.AppointmentDate AS start,
            Appointments.AppointmentTime AS time,
            Appointments.Status AS status,
            CONCAT(Pets.Name, ' (Owner: ', Owners.FirstName, ' ', Owners.LastName, ')') AS description,
            Owners.Email AS owner_email, Owners.FirstName AS owner_name
        FROM Appointments
        INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
        INNER JOIN Pets ON Appointments.PetId = Pets.PetId
        INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
        ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC
    ");

    $events_stmt->execute();
    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $date = $event['start'] ?? null;
        $time = $event['time'] ?? null;

        if ($date && $time) {
            if (!isset($booked_times_by_date[$date])) {
                $booked_times_by_date[$date] = [];
            }
            $booked_times_by_date[$date][] = $time;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $events = [];
}

$events_json = json_encode($events);

try {
    $services_stmt = $pdo->prepare("SELECT ServiceId, ServiceName FROM Services");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

try {
    $pets_stmt = $pdo->prepare("
        SELECT 
            Pets.PetId, 
            Pets.Name AS pet_name, 
            Owners.FirstName AS owner_name
        FROM Pets
        INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
        WHERE Pets.IsArchived = 0
    ");
    $pets_stmt->execute();
    $pets = $pets_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pets: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $petId = filter_input(INPUT_POST, 'PetId', FILTER_VALIDATE_INT);
    $serviceId = filter_input(INPUT_POST, 'ServiceId', FILTER_VALIDATE_INT);
    $appointmentDate = filter_input(INPUT_POST, 'AppointmentDate', FILTER_SANITIZE_SPECIAL_CHARS);
    $appointmentTime = filter_input(INPUT_POST, 'AppointmentTime', FILTER_SANITIZE_SPECIAL_CHARS);

    $checkArchivedStmt = $pdo->prepare("SELECT IsArchived FROM Pets WHERE PetId = ?");
    $checkArchivedStmt->execute([$petId]);
    $pet = $checkArchivedStmt->fetch(PDO::FETCH_ASSOC);

    if ($pet && $pet['IsArchived']) {
        $_SESSION['message'] = "Archived pets cannot book appointments.";
        header("Location: appointment.php");
        exit();
    }

    if ($petId && $serviceId && $appointmentDate && $appointmentTime) {
        $pdo->beginTransaction();

        try {
            $query = "
                INSERT INTO Appointments (PetId, ServiceId, AppointmentDate, AppointmentTime, Status)
                VALUES (:PetId, :ServiceId, :AppointmentDate, :AppointmentTime, 'Pending')
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':PetId' => $petId,
                ':ServiceId' => $serviceId,
                ':AppointmentDate' => $appointmentDate,
                ':AppointmentTime' => $appointmentTime,
            ]);

            $pdo->commit();

            $ownerQuery = "
                SELECT Owners.Email, Owners.FirstName, Pets.Name AS PetName, Services.ServiceName
                FROM Appointments
                INNER JOIN Pets ON Appointments.PetId = Pets.PetId
                INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
                INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
                WHERE Appointments.PetId = ?
            ";
            $ownerStmt = $pdo->prepare($ownerQuery);
            $ownerStmt->execute([$petId]);
            $appointmentData = $ownerStmt->fetch(PDO::FETCH_ASSOC);

            if ($appointmentData) {
                sendEmailNotification(
                    $appointmentData['Email'],
                    $appointmentData['FirstName'],
                    $appointmentData['PetName'],
                    $appointmentData['ServiceName'],
                    $appointmentDate,
                    $appointmentTime
                );
            }

            $_SESSION['message'] = "Appointment successfully added!";
            header("Location: appointment.php");
            exit();
        } catch (PDOException $e) {
            error_log("Error saving appointment: " . $e->getMessage());
            $_SESSION['message'] = "Failed to save appointment. Please try again.";
        }
    } else {
        $_SESSION['message'] = "All fields are required. Please fill out the form.";
    }
}

function sendEmailNotification($email, $ownerName, $petName, $serviceName, $appointmentDate, $appointmentTime) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@vetpawsitive.com';
        $mail->Password = 'Pawsitive3.';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@vetpawsitive.com', 'Pawsitive Veterinary Clinic');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format: $email");
            return;
        }
        error_log("Sending email to: $email");

        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation for $petName";
        $mail->Body = "
            <p>Hello $ownerName,</p>
            <p>Your appointment for <b>$petName</b> for <b>$serviceName</b> is confirmed.</p>
            <p><strong>Appointment Details:</strong></p>
            <p>Date: <b>$appointmentDate</b></p>
            <p>Time: <b>$appointmentTime</b></p>
            <p>We look forward to seeing you!</p>
            <p>Best regards,</p>
            <p><b>Pawsitive Veterinary Clinic</b></p>
        ";

        $mail->SMTPDebug = 2; 
        $mail->Debugoutput = function($str, $level) {
            error_log("Mail Debug [$level]: $str");
        };

        $mail->send();
        error_log("Email successfully sent to $email");
    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../assets/css/schedule.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var calendarEvents = <?= $events_json ?>;
        var bookedTimesByDate = <?= json_encode($booked_times_by_date) ?>;
    </script>
    <style>
        /* Toast Styling */
        #successToast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 400px;
            background-color: #28a745; /* Success green */
            color: white;
            padding: 15px;
            border-radius: 8px;
            display: none; /* Hidden by default */
            font-family: "Poppins", sans-serif;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Subtle shadow */
            animation: fadeInOut 4s ease-in-out; /* Fade animation */
        }

        #successToast.show {
            display: block; /* Show when active */
        }

        #successToast .btn-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        /* Toast Fade Animation */
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(10px); }
            10%, 90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(10px); }
        }
    </style>
    <style>
    /* Modal Container */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5); /* Slightly darker background for focus */
    }

    /* Modal Content */
    .modal-content {
        position: relative;
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        width: 100%;
        max-width: 500px; /* Increased from 400px to 500px */
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Modal Header */
    .modal-header {
        font-size: 1.25rem;
        font-weight: bold;
        color: #333;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }

    .modal-header .btn-close {
        position: absolute;
        right: 10px;
        top: 10px;
        background: none;
        border: none;
        font-size: 1rem;
        cursor: pointer;
    }

    /* Modal Body */
    .modal-body {
        padding: 20px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        color: #555;
    }

    .modal-body p {
        margin-bottom: 10px;
    }

    /* Modal Footer */
    .modal-footer {
        display: flex;
        justify-content: space-between;
        padding: 15px;
        border-top: 1px solid #e0e0e0;
    }

    .modal-footer .btn {
        font-size: 0.9rem;
        font-weight: 500;
        padding: 10px 30px;
        border-radius: 6px;
        transition: background-color 0.2s, transform 0.2s ease-in-out;
    }

    .modal-footer .btn:hover {
        transform: scale(1.05);
    }

    /* Button Variants */
    .btn {
        cursor: pointer;
        outline: none;
        border: none;
    }

    .btn-light {
        background-color: #f8f9fa;
        color: #333;
    }

    .btn-light:hover {
        background-color: #e2e6ea;
    }

    .btn-outline-danger {
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-outline-danger:hover {
        background-color: #dc3545;
        color: white;
    }

    .btn-outline-success {
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-outline-success:hover {
        background-color: #28a745;
        color: white;
    }

    .btn-outline-primary {
        color: #007bff;
        border: 1px solid #007bff;
    }

    .btn-outline-primary:hover {
        background-color: #007bff;
        color: white;
    }

    /* Fade-in Animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Responsive Design */
    @media (max-width: 576px) {
        .modal-content {
            width: 90%;
        }

        .modal-footer {
            flex-direction: column;
            gap: 10px;
        }

        .modal-footer .btn {
            width: 100%; /* Full width for better accessibility */
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
                    <img src="../assets/images/Icons/Chart 1.png" alt="Chart Icon">Overview</a></li>
                <li><a href="record.php">
                    <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff.php">
                    <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li class="active"><a href="appointment.php">
                    <img src="../assets/images/Icons/Schedule 3.png" alt="Schedule Icon">Schedule</a></>
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
    <?php if (!empty($message)) : ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast("<?= htmlspecialchars($message); ?>");
            });
        </script>
    <?php endif; ?>
        <header>
            <h1>Schedule</h1>  
            <div class="filter-container">
                <label for="statusFilter"><strong>Filter by Status:</strong></label>
                <select id="statusFilter" class="form-select">
                    <option value="all" selected>All</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancel">Cancel</option>
                </select>
            </div>
        </header>
        <div id="calendar"></div>
        <div class="appointment-list">
        <?php foreach ($appointments as $appointment): ?>
            <div 
                class="appointment-item" 
                data-id="<?= htmlspecialchars($appointment['AppointmentId']); ?>" 
                data-pet-id="<?= htmlspecialchars($appointment['PetId']); ?>" 
                data-title="<?= htmlspecialchars($appointment['ServiceName']); ?>" 
                data-description="<?= htmlspecialchars($appointment['PetName']); ?>" 
                data-status="<?= htmlspecialchars($appointment['Status']); ?>" 
                data-date="<?= htmlspecialchars($appointment['AppointmentDate']); ?>" 
                data-time="<?= htmlspecialchars($appointment['AppointmentTime']); ?>">
            </div>
        <?php endforeach; ?>
    </div>
    </div>
    <form action="" method="POST" class="appointment-form" novalidate>
        <div class="right-section">
        <h2>Appointment Section</h2>
        <div class="appointment-form-buttons">
            <a href="appointment_list.php" class="btn btn-primary view-all-btn">View All Appointments</a>
        </div>
        <br>
        <form action="appointment.php" method="POST" class="appointment-form">
        <div class="form-group">
            <label for="PetSearch">Pet:</label>
            <input type="text" id="PetSearch" name="PetSearch" placeholder="Search for a pet..." autocomplete="off">
            <input type="hidden" id="PetId" name="PetId">
            <div id="PetSuggestions" class="suggestions-box"></div>
        </div>
        <br>
        <div class="form-group">
            <label for="ServiceId">Service:</label>
            <select name="ServiceId" id="ServiceId" required>
                <?php foreach ($services as $service): ?>
                    <option value="<?= $service['ServiceId'] ?>"><?= htmlspecialchars($service['ServiceName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
        <div class="form-group">
            <label for="AppointmentDate">Date:</label>
            <input type="date" name="AppointmentDate" id="AppointmentDate" required>
        </div>
        <br>
        <div class="form-group">
            <label for="AppointmentTime">Time:</label>
            <select name="AppointmentTime" id="AppointmentTime" required>
                <option value="">Select Time</option>
                <?php 
                    $start = strtotime("08:00:00");
                    $end = strtotime("17:00:00");
                    while ($start <= $end) {
                        $displayTime = date("g:i A", $start);
                        $valueTime = date("H:i:s", $start);
                        echo "<option value='$valueTime'>$displayTime</option>";
                        $start = strtotime("+30 minutes", $start);
                    }
                ?>
            </select>
        </div>
        <br>
        <button type="submit" name="add_appointment" class="btn btn-primary view-all-btn">Submit Appointment</button>
    </form>
</div>

<div id="successToast" class="toast">
    <div class="toast-body"></div>
    <button type="button" class="btn-close" onclick="document.getElementById('successToast').classList.remove('show');"></button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
<script src="../assets/js/appointment.js?v=<?= time() ?>"></script>
<script src="../assets/js/update_appointment.js?v=<?= time() ?>"></script>
<script>
    $(document).ready(function() {
        let pets = <?php echo json_encode($pets); ?>;  // Fetch pets data from PHP

        $('#PetSearch').on('input', function() {
            let query = $(this).val().toLowerCase();
            let suggestionsBox = $('#PetSuggestions');

            if (query.length === 0) {
                suggestionsBox.hide();
                return;
            }

            let filteredPets = pets.filter(pet => pet.pet_name.toLowerCase().includes(query) || pet.owner_name.toLowerCase().includes(query));

            suggestionsBox.empty();
            if (filteredPets.length > 0) {
                filteredPets.forEach(pet => {
                    let suggestionItem = $('<div>')
                        .addClass('suggestion-item')
                        .text(`${pet.pet_name} (Owner: ${pet.owner_name})`)
                        .data('pet-id', pet.PetId)
                        .click(function() {
                            $('#PetSearch').val(pet.pet_name);
                            $('#PetId').val(pet.PetId);
                            suggestionsBox.hide();
                        });
                    suggestionsBox.append(suggestionItem);
                });
            } else {
                suggestionsBox.append('<div class="no-result">No Pet Found</div>');
            }
            suggestionsBox.show();
        });
        // Hide suggestions when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('#PetSearch, #PetSuggestions').length) {
                $('#PetSuggestions').hide();
            }
        });
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const appointmentItems = document.querySelectorAll(".appointment-item");

        appointmentItems.forEach(item => {
            const status = item.getAttribute("data-status");

            if (status === "Done" || status === "Paid" || status === "Declined") {
                item.classList.add("disabled-appointment"); // Grey out the appointment
                item.style.pointerEvents = "none";          // Disable click
            } else {
                item.addEventListener("click", () => {
                    showAppointmentDetails(item.dataset);   // Only trigger if not disabled
                });
            }
        });
    });
</script>
<script>
// ===========================
// 📝 Appointment Actions
// ===========================

// Function to confirm an appointment
function confirmAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, "Confirmed");
}

// Function to cancel an appointment
function declineAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, "Declined");
}

// Function to edit an appointment
function editAppointmentDetails(id, pet, service, date, time) {
    let petNameOnly = pet.replace(/ *\([^)]*\) */g, ""); // Removes anything inside parentheses

    Swal.fire({
        title: 'Edit Appointment',
        html: `
        <div style="text-align: left;">
            <div class="swal2-row">
                <label>Pet:</label>
                <input type="text" id="editPetName" class="swal2-input" value="${petNameOnly}" readonly>
            </div>
            
            <div class="swal2-row">
                <label>Service:</label>
                <select id="editServiceId" class="swal2-select">
                    ${generateServiceOptions(service)}
                </select>
            </div>
            
            <div class="swal2-row">
                <label>Date:</label>
                <input type="date" id="editAppointmentDate" class="swal2-input" value="${date}">
            </div>
            
            <div class="swal2-row">
                <label>Time:</label>
                <select id="editAppointmentTime" class="swal2-select">
                    ${generateTimeOptions(time)}
                </select>
            </div>
        </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Save Changes",
        preConfirm: () => {
            return {
                id: id,
                service: document.getElementById('editServiceId').value,
                date: document.getElementById('editAppointmentDate').value,
                time: document.getElementById('editAppointmentTime').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveEditedAppointment(result.value);
        }
    });
}

function updateAppointmentStatus(appointmentId, newStatus) {
    if (newStatus === "Declined") {
        Swal.fire({
            title: "Decline Appointment",
            input: "textarea",
            inputLabel: "Provide a reason for declining",
            inputPlaceholder: "Enter reason here...",
            inputAttributes: { "aria-label": "Reason for declining" },
            showCancelButton: true,
            confirmButtonText: "Submit"
        }).then((result) => {
            if (result.isConfirmed) {
                let reason = result.value || "No reason provided"; // Default reason if empty
                sendStatusUpdate(appointmentId, newStatus, reason);
            }
        });
    } else {
        sendStatusUpdate(appointmentId, newStatus);
    }
}

function sendStatusUpdate(appointmentId, status, reason = null) {
    $.ajax({
        url: 'update_appointment1.php',
        type: 'POST',
        data: { appointmentId: appointmentId, status: status, reason: reason },
        success: function (response) {
            Swal.fire({
                title: "Success!",
                text: `Appointment has been ${status.toLowerCase()}.`,
                icon: "success"
            }).then(() => {
                location.reload(); // Refresh the page
            });
        },
        error: function () {
            Swal.fire("Error", "Failed to update appointment.", "error");
        }
    });
}

// Function to handle appointment actions
function showAppointmentDetails(event) {
    if (event.status === "Declined") {
        return;
    }
    
    Swal.fire({
            title: 'Appointment Details',
            html: `
                <button class="swal2-close-button" onclick="Swal.close()">×</button>
                <div style="text-align: left; margin-top: 10px;">
                    <p><strong>Appointment For:</strong> ${event.extendedProps.description || "No Description"}</p>
                    <p><strong>Service:</strong> ${event.title || "No Title"}</p>
                    <p><strong>Date:</strong> ${event.start.toISOString().split('T')[0]}</p>
                    <p><strong>Time:</strong> ${event.extendedProps.time ? formatTime(event.extendedProps.time) : "No Time"}</p>
                    <p><strong>Status:</strong> <span class="status-badge">${event.extendedProps.status || "Pending"}</span></p>
                </div>
            `,
            showCancelButton: true,
            showDenyButton: true,
            showConfirmButton: true,
            cancelButtonText: "Decline Appointment",
            denyButtonText: "Edit Appointment",
            confirmButtonText: "Confirm Appointment",
            icon: "info",
            customClass: {
                confirmButton: 'swal2-confirm-btn',
                denyButton: 'swal2-edit-btn',
                cancelButton: 'swal2-cancel-btn'
            },
            buttonsStyling: false
        }).then((result) => {
        if (result.isConfirmed) {
            confirmAppointment(event.id);
        } else if (result.isDenied) {
            editAppointmentDetails(event.id, event.extendedProps.description, event.title, event.start.toISOString().split('T')[0], event.extendedProps.time);
        } else if (result.dismiss === Swal.DismissReason.cancel) { 
            updateAppointmentStatus(event.id, "Declined");  // ✅ Corrected this line to trigger the prompt
        }
    });
}

// Function to save edited appointment
function saveEditedAppointment(data) {
    $.ajax({
        url: 'update_appointment.php',
        type: 'POST',
        data: {
            appointmentId: data.id,
            service: data.service,
            date: data.date,
            time: data.time
        },
        success: function (response) {
            Swal.fire({
                title: "Success!",
                text: "Appointment updated successfully!",
                icon: "success"
            }).then(() => {
                location.reload(); // Refresh page to see changes
            });
        },
        error: function () {
            Swal.fire("Error", "Failed to update appointment.", "error");
        }
    });
}

// Helper function to generate service options
function generateServiceOptions(selectedService) {
    let services = <?= json_encode($services); ?>; // Fetch services from PHP
    let options = '';

    services.forEach(service => {
        let isSelected = service.ServiceName === selectedService ? "selected" : "";
        options += `<option value="${service.ServiceId}" ${isSelected}>${service.ServiceName}</option>`;
    });

    return options;
}

// Helper function to generate time options
function generateTimeOptions(selectedTime) {
    const timeSlots = [
        "08:00:00", "08:30:00", "09:00:00", "09:30:00",
        "10:00:00", "10:30:00", "11:00:00", "11:30:00",
        "12:00:00", "12:30:00", "13:00:00", "13:30:00",
        "14:00:00", "14:30:00", "15:00:00", "15:30:00",
        "16:00:00", "16:30:00", "17:00:00"
    ];

    let options = '';
    timeSlots.forEach(time => {
        let isSelected = time === selectedTime ? "selected" : "";
        options += `<option value="${time}" ${isSelected}>${formatTime(time)}</option>`;
    });

    return options;
}
</script>
</body>
</html>