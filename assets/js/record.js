document.addEventListener("DOMContentLoaded", function () {
    applyDropdownAndLinksFunctionality(); // Initialize dropdown and links
    attachSearchAndFilterListeners(); // Attach search and filter listeners
    attachGeneralEventListeners(); // General listeners for confirmation dialogs, etc.
});

// ===============================
// 📂 Toggle Dropdown Menu (Unified)
// ===============================
function applyDropdownAndLinksFunctionality() {
    // Apply dropdown toggle functionality
    document.querySelectorAll('.three-dot-btn, .three-dot-btns').forEach(button => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();

            const dropdownMenu = button.nextElementSibling;

            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu, .dropdown-menus').forEach(menu => {
                if (menu !== dropdownMenu) {
                    menu.style.display = 'none';
                }
            });

            // Toggle current dropdown
            dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (event) => {
        if (!event.target.matches('.three-dot-btn, .three-dot-btns')) {
            document.querySelectorAll('.dropdown-menu, .dropdown-menus').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });

    // Toggle pet details when clicking on a row
    document.querySelectorAll('.pet-row').forEach(row => {
        row.addEventListener('click', () => togglePetDetails(row));
    });
}

// 📄 Expand/Collapse Pet Details
function togglePetDetails(row) {
    const detailsRow = row.nextElementSibling;
    detailsRow.style.display = (detailsRow.style.display === 'none' || !detailsRow.style.display) ? 'table-row' : 'none';
}

// ===============================
// 🔎 Search and Filter Functionality
// ===============================
function attachSearchAndFilterListeners() {
    const searchInput = document.getElementById("searchInput");
    const speciesFilter = document.getElementById("speciesFilter");
    const serviceFilter = document.getElementById("serviceFilter");
    const staffList = document.getElementById("staffList");

    async function fetchResults() {
        const query = new URLSearchParams({
            search: searchInput.value.trim(),
            species: speciesFilter.value,
            service: serviceFilter.value,
            ajax: "1"
        });

        try {
            const response = await fetch(`record.php?${query.toString()}`);
            const newContent = await response.text();
            staffList.innerHTML = newContent;

            applyDropdownAndLinksFunctionality(); // Reapply dropdown listeners for dynamically loaded content
        } catch (err) {
            console.error("Error fetching results:", err);
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    searchInput.addEventListener("input", debounce(fetchResults, 300));
    speciesFilter.addEventListener("change", fetchResults);
    serviceFilter.addEventListener("change", fetchResults);
}

// ===============================
// 🐾 Confirmation Dialogs
// ===============================
function attachGeneralEventListeners() {
    // Confirmation dialog for pet confinement
    window.confirmConfine = function (petId) {
        Swal.fire({
            title: 'Confirm Confinement',
            text: 'Are you sure you want to confine this pet?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Confine',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("../src/confine_pet.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `petId=${petId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire("Confined!", "The pet has been successfully confined.", "success");

                        // ✅ Remove the pet's row from the table (without refreshing)
                        const row = document.getElementById(`pet-row-${petId}`);
                        if (row) row.remove();

                        // ✅ Check if the table is empty and show a message
                        const tableBody = document.querySelector("tbody");
                        if (tableBody.children.length === 0) {
                            tableBody.innerHTML = `<tr><td colspan="6">No pets found.</td></tr>`;
                        }
                    } else {
                        Swal.fire("Error!", data.error || "Failed to confine pet.", "error");
                    }
                })
                .catch(error => {
                    console.error("Error confining pet:", error);
                    Swal.fire("Error!", "Something went wrong. Please try again.", "error");
                });
            }
        });
    };

    // Confirmation dialog for deleting a pet
    window.confirmDelete = function (petId) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action will permanently delete this pet record.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete it!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `../src/delete_pet.php?PetId=${petId}`;
            }
        });
    };
}

// ===============================
// 📄 Handle Filter & Search
// ===============================
function updateTableContent(url) {
    const staffList = document.getElementById("staffList");

    staffList.innerHTML = `
        <tr>
            <td colspan="6" style="text-align:center;">
                <div class="spinner"></div>
            </td>
        </tr>
    `;

    fetch(url)
        .then(response => response.text())
        .then(html => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newTableBody = tempDiv.querySelector("#staffList");
            staffList.innerHTML = newTableBody.innerHTML;
            applyDropdownAndLinksFunctionality(); // Reapply dropdown listeners
        })
        .catch(error => console.error("Error fetching data:", error));
}

// ===============================
// 📄 Search Input Listener
// ===============================
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");

    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            const query = searchInput.value.trim();

            if (query.length > 0) {
                updateTableContent(`record.php?search=${encodeURIComponent(query)}`);
            } else {
                updateTableContent(`record.php`);
            }
        }, 300);
    });
});

function archivePet(petId) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This action will archive the pet record. You can restore it later from the archive.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Archive it!',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`../src/archive_pet.php?pet_id=${encodeURIComponent(petId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Archived!',
                            'The pet has been successfully archived.',
                            'success'
                        ).then(() => {
                            location.reload(); // Reload page to reflect changes
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            'An error occurred while archiving the pet. Please try again.',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'Error!',
                        'An error occurred while archiving the pet. Please try again.',
                        'error'
                    );
                });
        }
    });
}