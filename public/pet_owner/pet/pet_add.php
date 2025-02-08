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
    // Fetch pets linked to the owner with actual species and breed names
    $query = "SELECT 
                p.PetId, 
                p.Name AS PetName, 
                s.SpeciesName AS PetType, 
                p.Gender, 
                p.CalculatedAge, 
                b.BreedName AS Breed
              FROM pets p
              LEFT JOIN Species s ON p.SpeciesId = s.Id
              LEFT JOIN Breeds b ON p.Breed = b.BreedId
              WHERE p.OwnerId = :OwnerId";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch pet types (species) for dropdown
    $speciesStmt = $pdo->query("SELECT Id, SpeciesName FROM Species");
    $petTypes = $speciesStmt->fetchAll(PDO::FETCH_ASSOC);
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
  <link rel="icon" type="image/x-icon" href="/../assets/images/logo/LOGO.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Link to CSS -->
  <link rel="stylesheet" href="pet_add.css">
</head>

<body>
    <header>
        <nav>
             <div class="logo">
                 <img src="../../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
             </div>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../appointment/book_appointment.php">Appointment</a></li>
                    <li><a href="pet_add.html" class="active">Pet</a></li>
                    <li><a href="../record/pet_record.html">Record</a></li>
                    <li><a href="../record/record.php">Billing</a></li>
                </ul>
            <div class="profile-dropdown">
                <img src="../../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                    <div class="dropdown-content">
                      <a href="profile/index.php"><img src="../../../assets/images/Icons/Profile.png"alt="Profile Icon">Profile</a>
                      <a href=""><img src="../../../assets/images/Icons/Change Password.png"alt="Change Password Icon">Change Password</a>
                      <a href=""><img src="../../../assets/images/Icons/Settings 2.png"alt="Settings">Settings</a>
                      <a href=""><img src="../../../assets/images/Icons/Sign out.png"alt="Sign Out">Sign Out</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
      <section class="hero" id="home">
        <div class="hero-text">
      </section>

      <div class="main-content">
        <div class="container">
          <div class="left-section">
            <h2>Your Pets</h2>
            <?php if (!empty($pets)): ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Pet ID</th>
                    <th>Pet Name</th>
                    <th>Species</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Breed</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pets as $pet): ?>
                  <tr>
                    <td><?= htmlspecialchars($pet['PetId']) ?></td>
                    <td><?= htmlspecialchars($pet['PetName']) ?></td>
                    <td><?= htmlspecialchars($pet['PetType']) ?></td>
                    <td><?= htmlspecialchars($pet['Gender']) ?></td>
                    <td><?= htmlspecialchars($pet['CalculatedAge'] ?? 'No Information Found') ?></td>
                    <td><?= htmlspecialchars($pet['Breed']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <p>You have no pets registered.</p>
            <?php endif; ?>
          </div>
      
        <!-- Right Section: Add Pet Form -->
        <div class="right-section">
            <h2>Add a New Pet</h2>
            <form class="staff-form" action="add_pet.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-row">
                    <div class="input-container">
                        <label for="Name">Pet Name:</label>
                        <input type="text" id="Name" name="Name" required minlength="3" aria-invalid="false" placeholder="Enter pet name">
                    </div>
                    <div class="input-container">
                        <label for="Gender">Gender:</label>
                        <select id="Gender" name="Gender" required>
                            <option value="">Select gender</option>
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="PetType">Pet Type:</label>
                        <select id="PetType" name="PetType" required>
                            <option value="">Select pet type</option>
                            <?php foreach ($petTypes as $petType): ?>
                                <option value="<?php echo $petType['Id']; ?>">
                                    <?php echo $petType['SpeciesName']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-container">
                        <label for="Breed">Breed:</label>
                        <select id="Breed" name="Breed" required>
                            <option value="">Select breed</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="Birthday">Birthday or Birth Year:</label>
                        <input type="text" id="Birthday" name="Birthday" placeholder="YYYY-MM-DD or YYYY" required>
                    </div>
                    <div class="input-container">
                        <label for="CalculatedAge">Age:</label>
                        <input type="text" id="CalculatedAge" name="CalculatedAge" required placeholder="Enter pet's age">
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="ProfilePicture">Upload Profile Picture:</label>
                        <input type="file" id="ProfilePicture" name="ProfilePicture" accept="image/*" optional>
                    </div>
                    <div class="input-container">
                        <label for="Weight">Weight:</label>
                        <input type="number" step="0.01" id="Weight" name="Weight" required placeholder="Enter pet's weight">
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="window.location.href='../index.html'">Cancel</button>
                    <button type="submit" class="regowner-btn">Add Pet</button>
                </div>
            </form>
        </div>

</main>

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

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const dateElement = document.getElementById("current-date");
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const today = new Date().toLocaleDateString('en-US', options);
      dateElement.textContent = today;
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const menuButtons = document.querySelectorAll('.menu-button');
      menuButtons.forEach(button => {
        button.addEventListener('click', function (event) {
          const dropdown = this.nextElementSibling;
          dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

          document.addEventListener('click', function closeMenu(e) {
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
              dropdown.style.display = 'none';
              document.removeEventListener('click', closeMenu);
            }
          });
        });
      });
    });
  </script>
  
  <script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
  </script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const petTypeSelect = document.getElementById('PetType');
        const breedSelect = document.getElementById('Breed');

        petTypeSelect.addEventListener('change', function () {
            const speciesId = this.value;
            breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset breeds dropdown

            if (speciesId) {
                fetch(`add_pet.php?SpeciesId=${speciesId}`)
                    .then(response => response.json())
                    .then(breeds => {
                        breeds.forEach(breed => {
                            const option = document.createElement('option');
                            option.value = breed.id;
                            option.textContent = breed.breed_name;
                            breedSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching breeds:', error));
            }
        });
    });
</script>

<script src="add_pet.js"></script>
</body>
</html>