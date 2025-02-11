let currentView = 'month';
let appointmentsChart = null;
let petsChart = null;

// ========================
// 📥 Export PDF
// ========================
async function exportAllDataPDF() {
    console.log("Exporting PDF...");

    Swal.fire({
        title: "Exporting PDF...",
        html: `<div class="swal2-loading-container"><div class="swal2-spinner"></div><p>Generating report...</p></div>`,
        allowOutsideClick: false,
        showConfirmButton: false
    });

    try {
        const url = '../src/export_dashboard_pdf.php';
        const response = await fetch(url, { method: 'POST' });

        if (!response.ok) throw new Error(`Server error: ${response.status}`);

        const blob = await response.blob();
        const downloadUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `dashboard_report_${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(downloadUrl);

        Swal.fire("Success", "PDF report exported successfully!", "success");
    } catch (error) {
        console.error("Export error:", error);
        Swal.fire("Error", "Failed to export PDF. Please try again.", "error");
    }
}

// Ensure export function is globally accessible
window.exportAllDataPDF = exportAllDataPDF;

function promptVitalsUpdate(appointmentId, petId) {
    // ✅ Check sessionStorage before prompting the user
    if (sessionStorage.getItem(`vitals_${appointmentId}_${petId}`) === 'recorded') {
        console.log('Skipping vitals prompt: already recorded.');

        Swal.fire({
            title: 'Weight Already Recorded!',
            text: 'Redirecting to patient records...',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`;
        });

        return;
    }

    // ✅ Fetch from the server if vitals exist
    fetch(`../src/check_vitals_status.php?appointment_id=${appointmentId}&pet_id=${petId}`)
        .then(response => response.json())
        .then(data => {
            if (data.alreadyRecorded) {
                // ✅ If already recorded, store in sessionStorage and skip the prompt
                sessionStorage.setItem(`vitals_${appointmentId}_${petId}`, 'recorded');
                console.log('Skipping vitals prompt from database check.');
                window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`;
            } else {
                // ✅ Show the prompt only if vitals are NOT recorded
                showVitalsPrompt(appointmentId, petId);
            }
        })
        .catch(error => {
            console.error('Error checking vitals:', error);
            showVitalsPrompt(appointmentId, petId);
        });
}

function showVitalsPrompt(appointmentId, petId) {
    Swal.fire({
        title: 'Update Pet Vitals',
        html: `
            <label for="weight">Weight (kg):</label>
            <input type="number" id="weight" class="swal2-input" placeholder="Enter weight in kg" min="0.1" step="0.1">
            <br>
            <br>
            <label for="temperature">Temperature (°C):</label>
            <input type="number" id="temperature" class="swal2-input" placeholder="Enter temperature in °C" min="30" max="45" step="0.1">
        `,
        confirmButtonText: 'Update & Start',
        preConfirm: () => {
            const weight = document.getElementById('weight').value;
            const temperature = document.getElementById('temperature').value;
            if (!weight || !temperature || weight <= 0) {
                Swal.showValidationMessage('All fields are required and must be valid.');
                return false;
            }
            return { weight, temperature };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateVitalsAndStartConsultation(appointmentId, petId, result.value.weight, result.value.temperature);
        }
    });
}

function updateVitalsAndStartConsultation(appointmentId, petId, weight, temperature) {
    fetch('../src/update_pet_vitals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId, weight, temperature })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server Response:', data);

        if (data.success) {
            sessionStorage.setItem(`vitals_${appointmentId}_${petId}`, 'recorded');
            handleVitalsSuccess(appointmentId, petId);
        } else {
            Swal.fire('Error', data.message || 'Update failed.', 'error');
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

function handleVitalsSuccess(appointmentId, petId) {
    fetch('../src/get_user_role.php')
        .then(response => response.json())
        .then(data => {
            const role = data.role;

            if (role === 'Super Admin' || role === 'Admin' || role === 'Veterinarian') {
                Swal.fire({
                    title: 'Vitals Updated!',
                    text: 'Redirecting to patient record...',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = `patient_record.php?appointment_id=${appointmentId}&pet_id=${petId}`;
                });
            } else {
                Swal.fire({
                    title: 'Vitals Updated!',
                    text: 'Pet vitals recorded successfully.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => {
            console.error('Error fetching user role:', error);
        });
}

function promptVitalsVaccine(appointmentId, petId) {
    // ✅ Check sessionStorage before prompting the user
    if (sessionStorage.getItem(`vaccine_vitals_${appointmentId}_${petId}`) === 'recorded') {
        console.log('Skipping weight prompt: already recorded.');
        Swal.fire({
            title: "Pet's weight is already updated!",
            text: "Redirecting to vaccination records...",
            icon: "info",
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
        });
        return;
    }

    // ✅ Fetch from the server if weight was already recorded
    fetch(`../src/check_vaccine_status.php?appointment_id=${appointmentId}&pet_id=${petId}`)
        .then(response => response.json())
        .then(data => {
            if (data.alreadyRecorded) {
                // ✅ If already recorded, store in sessionStorage and skip the prompt
                sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');
                console.log('Skipping weight prompt from database check.');

                Swal.fire({
                    title: "Pet's weight is already updated!",
                    text: "Redirecting to vaccination records...",
                    icon: "info",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
                });
            } else {
                // ✅ Show the prompt only if weight is NOT recorded
                showVaccinePrompt(appointmentId, petId);
            }
        })
        .catch(error => {
            console.error('Error checking weight:', error);
            showVaccinePrompt(appointmentId, petId);
        });
}

function showVaccinePrompt(appointmentId, petId) {
    Swal.fire({
        title: 'Update Pet Weight',
        html: `
            <label for="weight">Weight (kg):</label>
            <input type="number" id="weight" class="swal2-input" placeholder="Enter weight in kg" min="0.1" step="0.1">
        `,
        confirmButtonText: 'Update & Start',
        preConfirm: () => {
            const weight = document.getElementById('weight').value.trim();
            if (!weight || isNaN(weight) || weight <= 0 || !/^\d*\.?\d+$/.test(weight)) {
                Swal.showValidationMessage('Weight must be a positive number without letters or symbols.');
                return false;
            }
            
            return { weight: parseFloat(weight) }; 
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateWeightAndStartVaccination(appointmentId, petId, result.value.weight);
        }
    });
}

window.promptVitalsVaccine = promptVitalsVaccine;

function updateWeightAndStartVaccination(appointmentId, petId, weight) {
    fetch('../src/update_pet_weight.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId, weight })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server Response:', data);

        if (data.success) {
            // ✅ Store that weight is already recorded
            sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');

            Swal.fire({
                title: "Weight updated!",
                text: "Redirecting to vaccination records...",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
            });
        } else {
            if (data.alreadyRecorded) {
                sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');
                Swal.fire({
                    title: "Pet's weight is already updated!",
                    text: "Redirecting to vaccination records...",
                    icon: "info",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
                });
            } else {
                Swal.fire('Error', data.message || 'Update failed.', 'error');
            }
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}