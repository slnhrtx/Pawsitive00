function updateAppointmentStatus(appointmentId, status, petId) {
  if (["Confirmed", "Declined", "Done"].includes(status)) {
    const actionText =
      status === "Confirmed"
        ? "confirm this appointment"
        : status === "Declined"
        ? "cancel this appointment"
        : "mark this appointment as done";

    Swal.fire({
      title: "Are you sure?",
      text: `Are you sure you want to ${actionText}?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor:
        status === "Done"
          ? "#28a745"
          : status === "Declined"
          ? "#d33"
          : "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes",
      cancelButtonText: "No",
    }).then((result) => {
      if (result.isConfirmed) {
        proceedWithStatusUpdate(appointmentId, status, petId);
      }
    });
  } else {
    proceedWithStatusUpdate(appointmentId, status, petId);
  }
}

function proceedWithStatusUpdate(appointmentId, status, petId) {
    const payload = {
      appointment_id: appointmentId,
      status: status,
      pet_id: petId,
    };
  
    console.log("Sending data:", payload);
  
    fetch("../public/update_appointment.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Response from PHP:", data);
  
        if (data.success) {
          const appointmentElement = document.getElementById(
            `appointment-${appointmentId}-${petId}`
          );
  
          if (appointmentElement) {
            const statusElement = appointmentElement.querySelector(".status");
            if (statusElement) {
              statusElement.textContent = status;
            }
  
            const buttonsContainer =
              appointmentElement.querySelector(".buttons-container");
            if (buttonsContainer) {
              buttonsContainer.innerHTML = "";
  
              switch (status) {
                case "Pending":
                  createButton(buttonsContainer, "Confirm", "confirm-btn", () =>
                    updateAppointmentStatus(appointmentId, "Confirmed", petId)
                  );
                  break;
  
                case "Confirmed":
                  createButton(buttonsContainer, "Start Consultation", "status-button", () =>
                    promptVitalsUpdate(appointmentId, petId)
                  );
                  createButton(buttonsContainer, "Mark as Done", "status-button", () =>
                    updateAppointmentStatus(appointmentId, "Done", petId)
                  );
                  createButton(buttonsContainer, "Decline", "decline-btn", () =>
                    updateAppointmentStatus(appointmentId, "Declined", petId)
                  );
                  break;
  
                case "Declined":
                  buttonsContainer.innerHTML = "<p>This appointment has been declined.</p>";
                  break;
  
                case "Done":
                  createButton(buttonsContainer, "Invoice and Billing", "status-button", () => {
                    window.location.href = `invoice_billing_form.php?appointment_id=${appointmentId}`;
                  });
                  break;
  
                default:
                  console.warn(`Unknown status: ${status}`);
              }
            }
          } else {
            console.error(
              `Appointment element not found for appointmentId: ${appointmentId}, petId: ${petId}`
            );
          }
  
          Swal.fire({
            title: "Success!",
            text: `Appointment status updated to ${status}.`,
            icon: "success",
            confirmButtonText: "OK",
          });
        } else {
          Swal.fire({
            title: "Error",
            text: data.message,
            icon: "error",
            confirmButtonText: "OK",
          });
        }
      })
      .catch((error) => {
        console.error("Fetch error:", error);
  
        Swal.fire({
          title: "Error",
          text: `An error occurred: ${error.message}`,
          icon: "error",
          confirmButtonText: "OK",
        });
      });
  }

  function createButton(container, text, className, onClick) {
    const button = document.createElement("button");
    button.textContent = text;
    button.classList.add(className);
    button.onclick = onClick;
    container.appendChild(button);
  }

function openRescheduleModal(event, appointmentId) {
  event.preventDefault();

  Swal.fire({
    title: "Reschedule Appointment",
    html: `
            <form id="rescheduleForm">
                <label for="newDate">New Date:</label>
                <input type="date" id="newDate" class="swal2-input" required>
                <label for="newTime">New Time:</label>
                <input type="time" id="newTime" class="swal2-input" required>
            </form>
        `,
    showCancelButton: true,
    confirmButtonText: "Reschedule",
    cancelButtonText: "Cancel",
    preConfirm: () => {
      const newDate = document.getElementById("newDate").value;
      const newTime = document.getElementById("newTime").value;

      if (!newDate || !newTime) {
        Swal.showValidationMessage("Please provide both date and time");
        return false;
      }

      return { newDate, newTime };
    },
  }).then((result) => {
    if (result.isConfirmed) {
      const { newDate, newTime } = result.value;

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

  fetch("../public/reschedule_appointment.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Response from PHP:", data);

      if (data.success) {
        Swal.fire({
          title: "Success!",
          text: "Appointment successfully rescheduled.",
          icon: "success",
          confirmButtonText: "OK",
        });

        // Update UI dynamically
        const appointmentElement = document.getElementById(
          `appointment-${appointmentId}`
        );
        if (appointmentElement) {
          const dateElement =
            appointmentElement.querySelector(".appointment-date");
          const timeElement =
            appointmentElement.querySelector(".appointment-time");
          if (dateElement) dateElement.textContent = newDate;
          if (timeElement) timeElement.textContent = newTime;
        }
      } else {
        Swal.fire({
          title: "Error",
          text: data.message,
          icon: "error",
          confirmButtonText: "OK",
        });
      }
    })
    .catch((error) => {
      console.error("Fetch error:", error);

      Swal.fire({
        title: "Error",
        text: `An error occurred: ${error.message}`,
        icon: "error",
        confirmButtonText: "OK",
      });
    });
}
