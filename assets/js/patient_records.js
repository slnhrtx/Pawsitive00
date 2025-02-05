// =========================
// Character Counter for Chief Complaint
// =========================
function updateCharCount() {
    const textArea = document.getElementById('chief_complaint');
    const counter = document.getElementById('char-count');
    counter.textContent = `${textArea.value.length} / 300 characters`;
}

// =========================
// Toggle Custom Duration Input
// =========================
document.addEventListener('DOMContentLoaded', function () {
    const durationSelect = document.getElementById('duration');
    const customDurationInput = document.getElementById('custom-duration');

    durationSelect?.addEventListener('change', function () {
        if (this.value === 'Other') {
            customDurationInput.style.display = 'block';
            customDurationInput.required = true;
        } else {
            customDurationInput.style.display = 'none';
            customDurationInput.required = false;
            customDurationInput.value = '';
        }
    });
});

// =========================
// Toggle Custom Medication, Dosage, and Duration Inputs
// =========================
function toggleCustomMedication(select){
    const customInput = select.parentElement.querySelector('input[type="text"]');
    if (select.value === 'Other') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        inputField.style.display = "none";
        inputField.value = "";
    }
}

// Attach event listeners
function attachListeners() {
    document.querySelectorAll("select[name='medications[]']").forEach(select => {
        select.addEventListener("change", () => toggleCustomInput(select));
    });

    document.querySelectorAll("select[name='dosages[]']").forEach(select => {
        select.addEventListener("change", () => toggleCustomInput(select));
    });

    document.querySelectorAll("select[name='durations[]']").forEach(select => {
        select.addEventListener("change", () => toggleCustomInput(select));
    });
}

// Add listener to 'Add Medication' button
document.addEventListener("DOMContentLoaded", () => {
    attachListeners();

    document.getElementById("add-medication").addEventListener("click", () => {
        setTimeout(attachListeners, 100);  // Wait for new inputs to load
    });
});


// =========================
// Add and Remove Medication Fields
// =========================
document.addEventListener('DOMContentLoaded', function () {
    const medicationsContainer = document.getElementById('medications-container');
    const addMedicationButton = document.getElementById('add-medication');
    let medicationCount = 1;

    addMedicationButton?.addEventListener('click', function () {
        medicationCount++;
        const medicationItem = document.createElement('div');
        medicationItem.classList.add('form-row', 'medication-item');
        medicationItem.innerHTML = `
                    <div class="input-container">
                        <label for="medication-1"><b>Medication:</b></label>
                        <select id="medication-1" name="medications[]" onchange="toggleCustomMedication(this)">
                            <option value="">Select Medication</option>

                            <!-- Antibiotics -->
                            <optgroup label="Antibiotics">
                                <option value="Amoxicillin">Amoxicillin</option>
                                <option value="Cefalexin">Cefalexin</option>
                                <option value="Doxycycline">Doxycycline</option>
                                <option value="Metronidazole">Metronidazole</option>
                            </optgroup>

                            <!-- Anti-Inflammatories -->
                            <optgroup label="Anti-Inflammatories">
                                <option value="Carprofen">Carprofen</option>
                                <option value="Meloxicam">Meloxicam</option>
                                <option value="Prednisone">Prednisone</option>
                            </optgroup>

                            <!-- Vitamins and Supplements -->
                            <optgroup label="Vitamins & Supplements">
                                <option value="Vitamin B12">Vitamin B12</option>
                                <option value="Omega-3">Omega-3 Fatty Acids</option>
                                <option value="Calcium Supplements">Calcium Supplements</option>
                            </optgroup>

                            <!-- Anti-Emetics -->
                            <optgroup label="Anti-Emetics">
                                <option value="Maropitant (Cerenia)">Maropitant (Cerenia)</option>
                                <option value="Metoclopramide">Metoclopramide</option>
                            </optgroup>

                            <!-- Fluids and Oxygen -->
                            <optgroup label="Supportive Care">
                                <option value="Fluid Therapy">Fluid Therapy</option>
                                <option value="Oxygen Therapy">Oxygen Therapy</option>
                            </optgroup>

                            <!-- Other -->
                            <option value="Other">Other (Specify)</option>
                        </select>

                        <!-- Custom medication input -->
                        <input type="text" id="custom-medication-1" name="custom-medications[]" placeholder="Specify other medication" style="display: none;">
                    </div>

                    <div class="input-container">
                        <label for="dosage-1"><b>Dosage:</b></label>
                        <select id="dosage-1" name="dosages[]">
                            <option value="">Select Dosage</option>
                            <optgroup label="Liquid (mL)">
                                <option value="0.5 mL">0.5 mL</option>
                                <option value="1 mL">1 mL</option>
                                <option value="2 mL">2 mL</option>
                                <option value="5 mL">5 mL</option>
                            </optgroup>
                            <optgroup label="Tablet (mg)">
                                <option value="50 mg">50 mg</option>
                                <option value="100 mg">100 mg</option>
                                <option value="250 mg">250 mg</option>
                                <option value="500 mg">500 mg</option>
                            </optgroup>
                            <optgroup label="Injectable">
                                <option value="0.1 mL/kg">0.1 mL/kg</option>
                                <option value="0.2 mL/kg">0.2 mL/kg</option>
                            </optgroup>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-dosage-1" name="custom-dosages[]" placeholder="Specify other dosage" style="display: none;">
                    </div>

                    <div class="input-container">
                        <label for="duration-1"><b>Duration:</b></label>
                        <select id="duration-1" name="durations[]">
                            <option value="">Select Duration</option>
                            <option value="1 Day">1 Day</option>
                            <option value="3 Days">3 Days</option>
                            <option value="5 Days">5 Days</option>
                            <option value="7 Days">7 Days</option>
                            <option value="10 Days">10 Days</option>
                            <option value="14 Days">14 Days</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-duration-1" name="custom-durations[]" placeholder="Specify other duration" style="display: none;">
                        <!-- Add/Delete Buttons -->
                        <button type="button" class="delete-button">Remove</button>
                     </div>
                </div>
            </div>
        `;
        medicationsContainer.appendChild(medicationItem);

        medicationItem.querySelector('.delete-button').addEventListener('click', function () {
            medicationsContainer.removeChild(medicationItem);
        });
    });
});

// =========================
// Form Validation Before Submission (Fixed)
// =========================
/*document.querySelector('form')?.addEventListener('submit', function (event) {
    const diagnosis = document.getElementById('diagnosis')?.value.trim();
    const diagnosisType = document.getElementById('diagnosis-type')?.value;

    if (!diagnosis) {
        alert('Please provide a diagnosis before submitting.');
        event.preventDefault();  // Keep this to avoid incomplete form submission
        return;
    }

    if (!diagnosisType) {
        alert('Please select whether the diagnosis is final or tentative.');
        event.preventDefault();
        return;
    }
});*/

// =========================
// Toggle Symptom Detail Inputs
// =========================
function toggleSymptomInput(checkbox) {
    const inputField = document.getElementById('other_symptom');
    inputField.style.display = checkbox.checked ? 'block' : 'none';

    // Clear the input field when the checkbox is unchecked
    if (!checkbox.checked) {
        inputField.value = '';
    }
}

// =========================
// Go Back Button Function
// =========================
function goBack() {
    const petId = new URLSearchParams(window.location.search).get('pet_id');
    window.location.href = `../public/pet_profile.php?PetId=${petId}`;
}

// =========================
// General function to toggle visibility
// =========================
function toggleOtherSpecify(selectId, inputId) {
    const selectElement = document.getElementById(selectId);
    const inputElement = document.getElementById(inputId);

    if (selectElement.value === "custom" || selectElement.value === "Other" || selectElement.value === "Other (Specify)") {
        inputElement.style.display = "block";
    } else {
        inputElement.style.display = "none";
        inputElement.value = "";
    }
}

// =========================
// Laboratory Examination
// =========================
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('heart-sound-select').addEventListener('change', function () {
        toggleOtherSpecify('heart-sound-select', 'heart-sound');
    });

    document.getElementById('lung-sound-select').addEventListener('change', function () {
        toggleOtherSpecify('lung-sound-select', 'lung-sound');
    });

    document.getElementById('mucous-membrane').addEventListener('change', function () {
        toggleOtherSpecify('mucous-membrane', 'mucous-membrane-custom');
    });
});

// General function to toggle "Other (Specify)" input fields for checkboxes
function toggleOtherCheckbox(checkboxId, inputId, placeholderText) {
    const checkbox = document.getElementById(checkboxId);
    const inputField = document.getElementById(inputId);

    checkbox.addEventListener('change', function () {
        if (this.checked) {
            inputField.style.display = 'block';
            inputField.placeholder = placeholderText;
        } else {
            inputField.style.display = 'none';
            inputField.value = '';
        }
    });
}

// Apply the function to all relevant "Other" checkboxes
document.addEventListener('DOMContentLoaded', function () {
    toggleOtherCheckbox('blood-test-other', 'blood-test-detail', 'Specify other blood test');
    toggleOtherCheckbox('imaging-other', 'imaging-other-detail', 'Specify other imaging');
    toggleOtherCheckbox('microbiology-other', 'microbiology-other-detail', 'Specify other microbiology');
    toggleOtherCheckbox('etc', 'etc-detail', 'Specify other tests');
});
