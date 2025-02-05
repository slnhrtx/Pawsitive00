function updateAppointmentStatus(appointmentId, status, petId, reason = null) {
    // Confirmation logic for specific statuses
    if (['Confirmed', 'Declined', 'Done'].includes(status)) {
        const actionText =
            status === 'Confirmed'
                ? 'confirm this appointment'
                : status === 'Declined'
                ? 'cancel this appointment'
                : 'mark this appointment as done';

        Swal.fire({
            title: 'Are you sure?',
            text: `Are you sure you want to ${actionText}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor:
                status === 'Done' ? '#28a745' : status === 'Declined' ? '#d33' : '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
        }).then((result) => {
            if (result.isConfirmed) {
                proceedWithStatusUpdate(appointmentId, status, petId, reason);
            }
        });
    } else {
        proceedWithStatusUpdate(appointmentId, status, petId, reason);
    }
}

function proceedWithStatusUpdate(appointmentId, status, petId, reason = null) {
    const payload = {
        appointment_id: appointmentId,
        status: status,
        pet_id: petId,
    };

    if (status === 'Declined') {
        payload.reason = reason;
    }

    console.log("Sending data:", payload);

    fetch('../public/update_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Response from PHP:", data);

            if (data.success) {
                const appointmentElement = document.getElementById(`appointment-${appointmentId}-${petId}`);
                
                if (appointmentElement) {
                    const statusElement = appointmentElement.querySelector('.status');
                    if (statusElement) {
                        statusElement.textContent = status;
                    }

                    const buttonsContainer = appointmentElement.querySelector('.buttons-container');
                    if (buttonsContainer) {
                        buttonsContainer.innerHTML = '';

                        if (status === 'Confirmed') {
                            const consultationButton = document.createElement('button');
                            consultationButton.textContent = 'Start Consultation';
                            consultationButton.classList.add('status-button');
                            consultationButton.onclick = () => {
                                promptVitalsUpdate(appointmentId, petId);
                            };
                            buttonsContainer.appendChild(consultationButton);

                            const cancelButton = document.createElement('button');
                            cancelButton.textContent = 'Cancel';
                            cancelButton.classList.add('decline-btn');
                            cancelButton.onclick = () => {
                                updateAppointmentStatus(appointmentId, 'Declined', petId);
                            };
                            buttonsContainer.appendChild(cancelButton);
                        } else if (status === 'Declined') {
                            const declinedMessage = document.createElement('p');
                            declinedMessage.textContent = 'This appointment has been declined.';
                            buttonsContainer.appendChild(declinedMessage);
                        } else if (status === 'Done') {
                            const invoiceButton = document.createElement('button');
                            invoiceButton.textContent = 'Invoice and Billing';
                            invoiceButton.classList.add('status-button');
                            invoiceButton.onclick = () => {
                                window.location.href = `invoice_billing_form.php?appointment_id=${appointmentId}`;
                            };
                            buttonsContainer.appendChild(invoiceButton);
                        }
                    }
                } else {
                    console.error(`Appointment element not found for appointmentId: ${appointmentId}, petId: ${petId}`);
                }

                Swal.fire({
                    title: 'Success!',
                    text: `Appointment status updated to ${status}.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                });
            } else {
                // Show error message if data.success is false
                Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK',
                });
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);

            // Show error message for fetch error
            Swal.fire({
                title: 'Error',
                text: `An error occurred: ${error.message}`,
                icon: 'error',
                confirmButtonText: 'OK',
            });
        });
}

function openRescheduleModal(event, appointmentId) {
    event.preventDefault(); // Prevent default anchor behavior

    Swal.fire({
        title: 'Reschedule Appointment',
        html: `
            <form id="rescheduleForm">
                <label for="newDate">New Date:</label>
                <input type="date" id="newDate" class="swal2-input" required>
                <label for="newTime">New Time:</label>
                <input type="time" id="newTime" class="swal2-input" required>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Reschedule',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const newDate = document.getElementById('newDate').value;
            const newTime = document.getElementById('newTime').value;

            if (!newDate || !newTime) {
                Swal.showValidationMessage('Please provide both date and time');
                return false;
            }

            return { newDate, newTime };
        },
    }).then((result) => {
        if (result.isConfirmed) {
            const { newDate, newTime } = result.value;

            // Send the reschedule request to the server
            rescheduleAppointment(appointmentId, newDate, newTime);
        }
    });
}

function rescheduleAppointment(appointmentId, newDate, newTime) {
    const payload = {
        appointment_id: appointmentId,
        new_date: newDate,
        new_time: newTime,
    };

    console.log("Rescheduling with data:", payload);

    fetch('../public/reschedule_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    })
        .then(response => response.json())
        .then(data => {
            console.log("Response from PHP:", data);

            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Appointment successfully rescheduled.',
                    icon: 'success',
                    confirmButtonText: 'OK',
                });

                // Update UI dynamically
                const appointmentElement = document.getElementById(`appointment-${appointmentId}`);
                if (appointmentElement) {
                    const dateElement = appointmentElement.querySelector('.appointment-date');
                    const timeElement = appointmentElement.querySelector('.appointment-time');
                    if (dateElement) dateElement.textContent = newDate;
                    if (timeElement) timeElement.textContent = newTime;
                }
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK',
                });
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);

            Swal.fire({
                title: 'Error',
                text: `An error occurred: ${error.message}`,
                icon: 'error',
                confirmButtonText: 'OK',
            });
        });
}