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
        const url = '../public/export_dashboard_pdf.php';
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
            if (!weight || !temperature) {
                Swal.showValidationMessage('All fields are required.');
                return false;
            }
            if (weight <= 0) {
                Swal.showValidationMessage('Weight must be a positive number.');
                return false;
            }
            if (temperature < 30 || temperature > 45) {
                Swal.showValidationMessage('Temperature should be between 30°C and 45°C.');
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
        if (data.success) {
            Swal.fire('Success', 'Vitals updated. Redirecting...', 'success');
            setTimeout(() => window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`, 2000);
        } else {
            Swal.fire('Error', 'Update failed.', 'error');
        }
    });
}

function promptVitalsVaccine(appointmentId, petId) {
    Swal.fire({
        title: 'Update Pet Weight',
        html: `
            <label for="weight">Weight (kg):</label>
            <input type="number" id="weight" class="swal2-input" placeholder="Enter weight in kg" min="0.1" step="0.1">
        `,
        confirmButtonText: 'Update & Start',
        preConfirm: () => {
            const weight = document.getElementById('weight').value;
            if (!weight || weight <= 0) {
                Swal.showValidationMessage('Weight must be a positive number.');
                return false;
            }
            return { weight };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateWeightAndStartConsultation(appointmentId, petId, result.value.weight);
        }
    });
}

window.promptVitalsVaccine = promptVitalsVaccine;

function updateWeightAndStartConsultation(appointmentId, petId, weight) {
    fetch('../src/update_pet_weight.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId, weight })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success', 'Weight updated. Redirecting...', 'success');
            setTimeout(() => window.location.href = `add_vaccination.php?appointment_id=${appointmentId}&pet_id=${petId}`, 2000);
        } else {
            Swal.fire('Error', 'Update failed.', 'error');
        }
    });
}