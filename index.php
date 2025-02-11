<!DOCTYPE html>
<html lang="en">

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pawsitive</title>
  <link rel="icon" type="image/x-icon" href="assets/images/logo/LOGO.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>

<body class="bg-white overflow-x-hidden">
<header class="bg-[#156f77] py-5 px-6 md:px-20 lg:px-36 fixed w-full top-0 z-50 rounded-b-2xl">
    <nav class="flex justify-between items-center">
      <!-- Responsive Logo -->
      <div>
        <img src="assets/images/logo/LOGO 2 WHITE.png" 
             alt="Pawsitive Logo" 
             class="w-32 md:w-48 lg:w-64 xl:w-72">
      </div>

      <!-- Responsive Login Button -->
      <ul class="flex gap-2 md:gap-4 lg:gap-6 items-center">
        <li>
          <a href="#" onclick="openLoginModal()" 
             class="bg-white text-[#156f77] font-bold text-sm md:text-base lg:text-lg px-3 md:px-5 lg:px-6 py-1 md:py-2 rounded-full border-2 border-[#156f77] hover:bg-[#156f77] hover:text-white">
            Login
          </a>
        </li>
      </ul>
    </nav>
  </header>

  <main class="pt-32 md:pt-40 px-8 md:px-20">
    <section class="flex flex-col md:flex-row justify-center items-center text-left gap-6 md:gap-10">
      <div class="text-center md:text-left">
        <h1 class="text-4xl md:text-6xl font-bold text-black">Your Pet's Care</h1>
        <h2 class="text-4xl md:text-6xl font-bold text-[#156f77]">Our Priority</h2>
        <p class="text-base md:text-lg text-black mt-4 max-w-[42ch] leading-tight">
          We're dedicated to enhancing the lives of pets through expert care and heartfelt commitment.</p>
        <br>
      </div>
      <div class="relative translate-x-0 md:translate-x-0">
      <img src="assets/images/Icons/For Index.png" alt="Dog and Cat" class="w-80 md:w-[500px] max-w-full">
      </div>
    </section>

    <section class="bg-gray-100 py-10 md:py-20 mt-10 md:mt-20 px-8 md:px-20 rounded-lg">
      <div class="max-w-5xl mx-auto text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-5">About Us</h2>
        <p class="text-base md:text-lg">At PAWSITIVE, we believe that every pet deserves the best care and attention...</p>
        <div class="flex flex-col md:flex-row gap-6 md:gap-10 mt-6 md:mt-10">
          <div class="bg-teal-800 p-6 rounded-lg shadow-lg text-white flex-1 text-center">
            <h3 class="text-xl md:text-2xl font-bold">Mission</h3>
            <p>To empower pet owners and veterinary professionals through innovative technology...</p>
          </div>
          <div class="bg-teal-800 p-6 rounded-lg shadow-lg text-white flex-1 text-center">
            <h3 class="text-xl md:text-2xl font-bold">Vision</h3>
            <p>Our vision is to be the leading platform for seamless pet care management...</p>
          </div>
        </div>
      </div>
    </section>
  </main>
  <br>
  <div class="bg-white px-8 md:px-20">
    <img src="assets/images/Icons/Pet pic 4.png" alt="Group of Pets" class="w-full md:w-3/5 mx-auto">
  </div>

  <footer class="bg-black text-white py-10 px-8 md:px-20">
    <div class="flex flex-col md:flex-row justify-between items-start gap-10">
      <div>
        <img src="assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo" class="w-32 md:w-48">
        <ul class="mt-4 space-y-2">
          <li class="flex items-center"><img src="assets/images/Icons/Facebook.png" class="w-6 mr-2"> petadventure14</li>
          <li class="flex items-center"><img src="assets/images/Icons/Email.png" class="w-6 mr-2"> petadventure20@gmail.com</li>
          <li class="flex items-center"><img src="assets/images/Icons/Contact Number.png" class="w-6 mr-2"> 0923 528 3253</li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Company</h4>
        <ul class="mt-2 space-y-2">
          <li><a href="#" class="hover:underline">Home</a></li>
          <li><a href="#" class="hover:underline">Appointment</a></li>
          <li><a href="#" class="hover:underline">Contacts</a></li>
          <li><a href="#" class="hover:underline">About</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Help Center</h4>
        <ul class="mt-2 space-y-2">
          <li><a href="#" class="hover:underline">Call Center</a></li>
          <li><a href="#" class="hover:underline">FAQs</a></li>
          <li><a href="#" class="hover:underline">Support Docs</a></li>
          <li><a href="#" class="hover:underline">Careers</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Address</h4>
        <p>Stall 1, Atilano Bldg, National Road, Cay Pomba, Sta. Maria, Bulacan, 3022</p>
      </div>
    </div>
    <p class="text-center mt-6 md:mt-10 text-sm">&copy; 2025 Pet Adventure Veterinary Services and Supplies, All Rights Reserved.</p>
  </footer>

<!-- JavaScript for Modal -->
<script>
  function openLoginModal() {
    document.getElementById("loginModal").classList.remove("hidden");
  }
  function closeLoginModal() {
    document.getElementById("loginModal").classList.add("hidden");
  }
</script>

<script>
function openLoginModal() {
  Swal.fire({
    title: "<span class='custom-title' style='color: black;'>Select Login Type</span>",  // Title in black
    showCancelButton: false,
    showCloseButton: true,
    allowOutsideClick: true,
    showConfirmButton: false,  // Removes the OK button
    html: `
      <div class="flex flex-col space-y-4 font-poppins">
        <button onclick="window.location.href='public/owner_login.php'"
          class="w-full bg-[#156f77] text-white text-lg font-semibold py-3 rounded-lg hover:bg-[#0f5a5e] transition duration-200">
          Pet Owner Login
        </button>
        <button onclick="window.location.href='public/staff_login.php'"
          class="w-full bg-[#156f77] text-white text-lg font-semibold py-3 rounded-lg hover:bg-[#0f5a5e] transition duration-200">
          Clinic Staff Login
        </button>
      </div>
    `,
    customClass: {
      popup: "rounded-lg p-6",
      closeButton: "custom-close-button"
    },
    didOpen: () => {
      // Apply Poppins font to all elements
      document.querySelector(".swal2-popup").style.fontFamily = "Poppins, sans-serif";
      
      // Close button hover effect
      document.querySelector(".custom-close-button").style.color = "black";
      document.querySelector(".custom-close-button").addEventListener("mouseover", function() {
        this.style.color = "#156f77";
      });
      document.querySelector(".custom-close-button").addEventListener("mouseout", function() {
        this.style.color = "black";
      });
    }
  });
}
</script>

</body>
</html>
