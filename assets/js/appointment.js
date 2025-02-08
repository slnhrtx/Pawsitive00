// ===========================
// 📅 Calendar Initialization
// ===========================
document.addEventListener('DOMContentLoaded', function(){
    initializeCalendar();
    setupStatusFilter();
    highlightBookedDates();
    setupConfirmRejectButtons();
    setupEditButton();
    setupDateValidation();
    setupToastMessage();
});

let FullCalendarInstance = null;

function initializeCalendar(filteredStatus = "all") {
    const calendarEl = document.getElementById('calendar');

    if (FullCalendarInstance) {
        FullCalendarInstance.destroy();
    }

    console.log("Current Filter: ", filteredStatus);
    console.log("Original Events: ", calendarEvents);
    
    const filteredEvents = filteredStatus === "all"
    ? [...calendarEvents] // Clone the array to avoid modifying the original
    : calendarEvents.filter(event => event.status.toLowerCase() === filteredStatus.toLowerCase());

    filteredEvents.sort((a, b) => {
        let dateComparison = new Date(a.start) - new Date(b.start);
        if (dateComparison === 0) {
            return a.time.localeCompare(b.time);
        }
        return dateComparison;
    });

    console.log("Sorted Events: ", filteredEvents);
    console.log("Filtered Events: ", filteredEvents);
    
FullCalendarInstance = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    views: {
        dayGridMonth: { buttonText: 'Month' }, 
        timeGridWeek: { 
            buttonText: 'Week',
            slotMinTime: "08:00:00",
            slotMaxTime: "17:00:00",
            slotDuration: "00:30:00",
        },
        timeGridDay: { 
            buttonText: 'Day',
            slotMinTime: "08:00:00",
            slotMaxTime: "17:00:00",
            slotDuration: "00:30:00",
        }
    },
    events: filteredEvents,
    
        dateClick: function (info) {
            setDateAndLoadTimeSlots(info.dateStr);
        },
    
        eventContent: function (arg) {
            let statusClass = '';
    
            switch (arg.event.extendedProps.status.toLowerCase()) {
                case 'confirmed':
                    statusClass = 'status-confirmed';
                    break;
                case 'pending':
                    statusClass = 'status-pending';
                    break;
                case 'cancelled':
                    statusClass = 'status-cancelled';
                    break;
                default:
                    statusClass = 'status-default';
            }
    
            return {
                html: `
                    <div class="custom-event">
                        <div class="event-title">${arg.event.title}</div>
                        <div class="event-time">${formatTime(arg.event.extendedProps.time)}</div>
                        <div class="event-status ${statusClass}">${arg.event.extendedProps.status}</div>
                    </div>
                `
            };
        },
    
        eventClick: function (info) {
            const status = info.event.extendedProps.status;
        
            if (status === "Done" || status === "Paid" || status === "Declined") {
                Swal.fire({
                    icon: 'info',
                    title: 'Appointment Locked',
                    text: `This appointment is marked as "${status}" and cannot be edited.`,
                    confirmButtonColor: '#3085d6'
                });
                return; // 🚫 Prevent further interaction
            }
            showAppointmentDetails(info.event);
        }
    });
    

    FullCalendarInstance.render();
}

statusFilter.addEventListener('change', function () {
    const selectedStatus = this.value;
    console.log("Filter changed to: ", selectedStatus);
    
    initializeCalendar(selectedStatus);
    FullCalendarInstance.refetchEvents(); // Force refresh
});

// ===========================
// 📅 Date and Time Handling
// ===========================

// Validate selected date (no past dates)
function validateSelectedDate(selectedDateStr) {
    const selectedDate = new Date(selectedDateStr);
    const today = new Date();

    // Compare only the date, ignore time
    today.setHours(0, 0, 0, 0);
    selectedDate.setHours(0, 0, 0, 0);

    if (selectedDate < today) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Date',
            text: 'You cannot select a past date.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
        }).then(() => {
            document.getElementById("AppointmentDate").value = ''; // Clear invalid date
        });
    } else {
        loadAvailableTimeSlots(selectedDateStr); // Proceed if valid
    }
}

// Attach event listener to AppointmentDate field
function setupDateValidation() {
    document.getElementById("AppointmentDate").addEventListener("change", function () {
        validateSelectedDate(this.value);
    });
}

function setDateAndLoadTimeSlots(dateStr) {
    const dateInput = document.getElementById('AppointmentDate');
    dateInput.value = dateStr;

    validateSelectedDate(dateStr); // 🔥 Manually validate date before proceeding

    dateInput.dispatchEvent(new Event('change'));
}

function loadAvailableTimeSlots(selectedDate) {
    const today = new Date().toISOString().split('T')[0];
    const currentTime = new Date().toTimeString(). split(' ')[0];
    const timeSelect = document.getElementById("AppointmentTime");
    timeSelect.innerHTML = '<option value="">Select Time</option>';

    const bookedTimes = bookedTimesByDate[selectedDate] || [];
    const timeSlots = generateTimeSlots();

    let firstAvailableTime = null;

    timeSlots.forEach(time => {
        const option = document.createElement("option");
        option.value = time;
        option.textContent = formatTime(time);

        if ((selectedDate === today && time <= currentTime) || bookedTimes.includes(time)){
            option.disabled = true;
            option.textContent += bookedTimes.includes(time) ? " (Booked)" : " (Past)";
        } else if (!firstAvailableTime){
            firstAvailableTime = time;
        }

        timeSelect.appendChild(option);
    });

    if (firstAvailableTime){
        timeSelect.value = firstAvailableTime;
    }
}

function generateTimeSlots(){
    const slots = [];
    let start = new Date();
    start.setHours(8, 0, 0, 0);

    for (let i = 0; i <= 18; i++){
        slots.push(start.toTimeString().split(' ')[0]);
        start.setMinutes(start.getMinutes() + 30);
    }

    return slots;
}

function formatTime(time) {
    const [hour, minute] = time.split(':');
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const formattedHour = (hour % 12) || 12;
    return `${formattedHour}:${minute} ${ampm}`;
}

// ===========================
// Toast Message Display
// ===========================

function setupToastMessage() {
    if (typeof message !== 'undefined' && message) {
        showToast(message);
    }
}

function showToast(message) {
    const toast = document.getElementById("successToast");
    const toastBody = toast.querySelector(".toast-body");

    toastBody.textContent = message;

    toast.classList.add("show");

    setTimeout(() => {
        toast.classList.remove("show");
    }, 4000);
}

// ===========================
// 📝 Appointment Modal Handling
// ===========================

// Show appointment details in modal
function showAppointmentDetails(event) {
    document.getElementById('modalService').textContent = event.title || "No Title";
    document.getElementById('modalDate').textContent = event.start.toISOString().split('T')[0];
    document.getElementById('modalTime').textContent = event.extendedProps.time
        ? formatTime(event.extendedProps.time)
        : "No Time";
    document.getElementById('modalDescription').textContent = event.extendedProps.description || "No Description";

    const statusElement = document.getElementById('modalStatus');
    const status = event.extendedProps.status || "Pending";
    statusElement.textContent = status;

    statusElement.className = 'badge';
    switch (status.toLowerCase()) {
        case 'confirmed':
            statusElement.classList.add('bg-success');
            break;
        case 'cancel':
            statusElement.classList.add('bg-danger');
            break;
        case 'pending':
        default:
            statusElement.classList.add('bg-warning');
            break;
    }

    const disableActions = ['Paid', 'Done'].includes(status);
    document.getElementById('rejectButton').disabled = disableActions;
    document.getElementById('confirmButton').disabled = disableActions;
    document.getElementById('editButton').disabled = disableActions;

    console.log(event);

    const appointmentModal = new bootstrap.Modal(document.getElementById('appointmentModal'));
    appointmentModal.show();
}

// ===========================
// ✅ Confirm/Reject Handlers
// ===========================
function setupConfirmRejectButtons(){
    document.getElementById("confirmButton").addEventListener("click", () => handleAction("Confirmed"));
    document.getElementById("rejectButton").addEventListener("click", () => handleAction("Cancel"));
}

// ===========================
// ✏️ Edit Appointment Handler
// ===========================

function setupEditButton() {
    document.getElementById('editButton').addEventListener('click', function () {
        const appointmentId = document.getElementById('modalService').getAttribute('data-id');
        const service = document.getElementById('modalService').textContent;
        const date = document.getElementById('modalDate').textContent;
        const time = document.getElementById('modalTime').textContent;

        document.getElementById('editAppointmentId').value = appointmentId;
        document.getElementById('editServiceId').value = getServiceIdByName(service);
        document.getElementById('editAppointmentDate').value = date;
        document.getElementById('editAppointmentTime').value = formatTimeForInput(time);

        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    });
}

// Helper: Get Service ID by Name
function getServiceIdByName(serviceName) {
    const serviceSelect = document.getElementById('editServiceId');
    for (const option of serviceSelect.options) {
        if (option.textContent.trim() === serviceName) {
            return option.value;
        }
    }
    return '';
}

function formatTimeForInput(time) {
    const [hours, minutes] = time.split(':');
    return `${hours}:${minutes}:00`;
}

function highlightBookedDates() {
    const bookedDates = Object.keys(bookedTimesByDate);

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        events: calendarEvents,

        dayCellDidMount: function (info){
            const dateStr = info.date.toISOString().split('T')[0];

            if (bookedDates.includes(dateStr)){
                info.el.classList.add('booked-date');
            }
        },

        dateClick: function (info){
            setDateAndLoadTimeSlots(info.dateStr);
        },

        eventClick: function (info){
            showAppointmentDetails(info.event);
        }
    });

    calendar.render();
}