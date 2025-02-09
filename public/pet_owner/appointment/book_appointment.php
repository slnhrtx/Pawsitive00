<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* For pets basic info */
/* For pets basic info */
/* For pets basic info */

session_start();

require '../../../config/dbh.inc.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId']; // Get the logged-in owner's ID
$user_name = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Owner';

try {
    // Query to fetch pets linked to the logged-in owner
    $query = "SELECT 
        pets.PetId, 
        pets.Name AS pet_name, 
        pets.SpeciesId, 
        pets.Gender, 
        pets.CalculatedAge, 
        pets.Breed
    FROM 
        pets 
    WHERE 
        pets.OwnerId = :OwnerId";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $appointmentQuery = "SELECT 
        a.AppointmentId, 
        p.Name AS PetName, 
        s.ServiceName AS Service, 
        a.AppointmentTime AS Time, 
        a.Status
    FROM Appointments a
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE p.OwnerId = :owner_id
    ORDER BY a.AppointmentTime DESC";

    $appointmentStmt = $pdo->prepare($appointmentQuery);
    $appointmentStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $appointmentStmt->execute();
    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedTimesQuery = "
        SELECT DATE(AppointmentTime) as booked_date, 
            TIME(AppointmentTime) as booked_time
        FROM Appointments";

    $bookedTimesStmt = $pdo->prepare($bookedTimesQuery);
    $bookedTimesStmt->execute();
    $bookedTimes = $bookedTimesStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedTimesByDate = [];
    foreach ($bookedTimes as $bt) {
        $bookedTimesByDate[$bt['booked_date']][] = $bt['booked_time'];
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
        
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="book_appointment.css">
    <script>
        const bookedTimesByDate = <?= json_encode($bookedTimesByDate); ?>;
    </script>
</head>

<body>
    <header>
        <nav>
            <div class="logo">
                <img src="../../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
            </div>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="book_appointment.php" class="active">Appointment</a></li>
                <li><a href="../pet/pet_add.php">Pets</a></li>
                <li><a href="../record/pet_record.php">Record</a></li>
                <li><a href="../record/record.php">Billing</a></li>
            </ul>
            <div class="profile-dropdown">
                <img src="../../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                <div class="dropdown-content">
                    <a href="profile/index.php"><img src="../../../assets/images/Icons/Profile.png"
                            alt="Profile Icon">Profile</a>
                    <a href=""><img src="../../../assets/images/Icons/Change Password.png"
                            alt="Change Password Icon">Change Password</a>
                    <a href=""><img src="../../../assets/images/Icons/Settings 2.png" alt="Settings">Settings</a>
                    <a href=""><img src="../../../assets/images/Icons/Sign out.png" alt="Sign Out">Sign Out</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
    <section class="hero">
            <div class="hero-text">
                <h1>Appointments</h1>
            </div>
        </section>

        <div class="main-content">
            <div class="container">

                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="add_pet.php" method="POST">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-row">
                            <div class="input-container">
                                <label for="PetId">Pet Name:</label>
                                <select id="PetId" name="PetId" required>
                                    <option value="">Select pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?= htmlspecialchars($pet['PetId']) ?>">
                                            <?= htmlspecialchars($pet['pet_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-container">
                                <label for="Service">Service:</label>
                                <select id="Service" name="Service" required>
                                    <option value="">Select Service</option>
                                    <option value="Pet Wellness & Consultation">Pet Wellness & Consultation</option>
                                    <option value="Pet Vaccination & Deworming">Pet Vaccination & Deworming</option>
                                    <option value="Diagnostics & Laboratories">Diagnostics & Laboratories</option>
                                    <option value="Ultrasound">Ultrasound</option>
                                    <option value="Dental Cleaning (Prophylaxis)">Dental Cleaning (Prophylaxis)</option>
                                    <option value="Spay & Neuter">Spay & Neuter</option>
                                    <option value="Surgery Services">Surgery Services</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Pet Grooming">Pet Grooming</option>
                                    <option value="Pet Accessories & Supplies">Pet Accessories & Supplies</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <label for="AppointmentDate">Date:</label>
                                <input type="date" id="AppointmentDate" name="AppointmentDate" min="" required>
                            </div>

                            <div class="input-container">
                                <label for="AppointmentTime">Time:</label>
                                <select id="AppointmentTime" name="AppointmentTime" required>
                                    <option value="">Select Time</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <!-- <button type="button" class="cancel-btn" onclick="window.location.href='../index.html'">Cancel</button>-->
                            <button type="submit" class="regowner-btn">Submit</button>
                        </div>
                    </form>
                </div>
    </main>

    <div class="calendar-container">
        <div class="calendar-header">
            <button id="prevMonth">&lt;</button>
            <h2 id="currentMonthYear"></h2>
            <button id="nextMonth">&gt;</button>
        </div>
        <div id="calendar"></div>
    </div>

    <main>
        <div class="main-content">
            <div class="container">
                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="add_pet.php" method="POST">
                        <section id="appointments-section" class="appointments-section">
                            <h2 class="section-headline">Your Booked Appointments</h2>
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
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const profileIcon = document.querySelector('.profile-icon');
            const dropdownContent = document.querySelector('.dropdown-content');

            profileIcon.addEventListener('click', function () {
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.addEventListener('click', function (event) {
                if (!event.target.matches('.profile-icon')) {
                    if (dropdownContent.style.display === 'block') {
                        dropdownContent.style.display = 'none';
                    }
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const timeSelect = document.getElementById('AppointmentTime');

            function generateTimeSlots(startTime, endTime, interval) {
                const start = new Date(`1970-01-01T${startTime}:00`);
                const end = new Date(`1970-01-01T${endTime}:00`);

                while (start <= end) {
                    const hours = start.getHours();
                    const minutes = start.getMinutes();
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    const formattedHours = hours % 12 === 0 ? 12 : hours % 12;
                    const formattedMinutes = minutes < 10 ? `0${minutes}` : minutes;

                    const timeString = `${formattedHours}:${formattedMinutes} ${ampm}`;

                    const option = document.createElement('option');
                    option.value = timeString;
                    option.textContent = timeString;
                    timeSelect.appendChild(option);

                    start.setMinutes(start.getMinutes() + interval);
                }
            }

            generateTimeSlots('08:00', '17:00', 30);
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarElement = document.getElementById('calendar');
        const currentMonthYearElement = document.getElementById('currentMonthYear');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const dateInput = document.getElementById('AppointmentDate');
        
        let today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();

        function generateCalendar(month, year) {
            calendarElement.innerHTML = '';

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            currentMonthYearElement.textContent = `${monthNames[month]} ${year}`;

            // Days of the week labels
            const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysOfWeek.forEach(day => {
                const dayLabel = document.createElement('div');
                dayLabel.textContent = day;
                dayLabel.style.fontWeight = 'bold';
                calendarElement.appendChild(dayLabel);
            });

            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                calendarElement.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day');
                dayElement.textContent = day;

                // Highlight today's date
                if (
                    day === today.getDate() &&
                    month === today.getMonth() &&
                    year === today.getFullYear()
                ) {
                    dayElement.classList.add('today');
                }

                // Set click event for selecting date
                dayElement.addEventListener('click', function () {
                    let selectedDate = new Date(year, month, day);
                    let formattedDate = selectedDate.toISOString().split('T')[0];

                    if (selectedDate < today) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'It is no longer possible to make an appointment with a past date.',
                        });
                        return;
                    }

                    dateInput.value = formattedDate; // Update the date input field
                });

                calendarElement.appendChild(dayElement);
            }
        }

        function goToNextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateCalendar(currentMonth, currentYear);
        }

        function goToPrevMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateCalendar(currentMonth, currentYear);
        }

        prevMonthBtn.addEventListener('click', goToPrevMonth);
        nextMonthBtn.addEventListener('click', goToNextMonth);

        generateCalendar(currentMonth, currentYear);
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dateInput = document.getElementById('AppointmentDate');

        if (dateInput) {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Ensure time is set to start of the day
            const formattedDate = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD

            dateInput.setAttribute('min', formattedDate); // Prevent selecting past dates

            // Prevent user from manually inputting past dates
            dateInput.addEventListener('change', function () {
                const selectedDate = new Date(this.value);
                selectedDate.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Date',
                        text: 'You cannot select a past date!',
                    });

                    this.value = ''; // Reset to empty if invalid
                }
            });
        }
    });

    </script>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

</body>

</html>