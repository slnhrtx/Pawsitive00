document.addEventListener('DOMContentLoaded', function () {
    const birthdayField = document.getElementById('Birthday');
    const calculatedAgeField = document.getElementById('CalculatedAge');

    // Attach event listeners
    birthdayField.addEventListener('change', calculateAge);
    birthdayField.addEventListener('input', calculateAge);

    function calculateAge() {
        const input = birthdayField.value.trim(); // Get the date from the input field
        const today = new Date(); // Current date
        let age = null;

        console.log("Raw Birthday Input:", input); // Debug: Log raw input

        // Convert MM/DD/YYYY to a Date object
        const birthDate = parseDate(input);
        console.log("Parsed Birth Date:", birthDate); // Debug: Log the parsed date

        if (birthDate && !isNaN(birthDate.getTime())) {
            // Calculate the age
            age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            // Adjust if today's date is before the birthday in the current year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            console.log("Calculated Age:", age); // Debug: Log the calculated age
            calculatedAgeField.value = age >= 0 ? `${age} year(s)` : 'Invalid date';
        } else {
            console.error("Invalid Date Format or Object");
            calculatedAgeField.value = 'Invalid date';
        }
    }

    function parseDate(input) {
        // Check for MM/DD/YYYY format and convert to Date object
        const mmddyyyy = /^(\d{2})\/(\d{2})\/(\d{4})$/; // Regex for MM/DD/YYYY
        const yyyymmdd = /^\d{4}-\d{2}-\d{2}$/; // Regex for YYYY-MM-DD

        if (mmddyyyy.test(input)) {
            const [_, month, day, year] = input.match(mmddyyyy);
            return new Date(`${year}-${month}-${day}`);
        } else if (yyyymmdd.test(input)) {
            return new Date(input); // Already in YYYY-MM-DD
        } else {
            return null; // Invalid format
        }
    }
});