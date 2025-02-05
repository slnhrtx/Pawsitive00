// Optimized JavaScript for AJAX-based Pagination and UI Enhancements

document.addEventListener('DOMContentLoaded', () => {
    const paginationContainer = document.querySelector('.pagination');
    const recordsContainer = document.getElementById('records-container');
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

    function fetchRecords(page = 0, search = '', filter = '') {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', '1');
        url.searchParams.set('page', page);
        if (search) url.searchParams.set('search', search);
        if (filter) url.searchParams.set('filter', filter);

        recordsContainer.innerHTML = '<div class="spinner"></div>'; // Loading spinner

        fetch(url)
            .then(response => response.text())
            .then(html => {
                recordsContainer.innerHTML = html;
                attachPaginationListeners();
            })
            .catch(error => console.error('Error fetching records:', error));
    }

    function attachPaginationListeners() {
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page, 10);
                fetchRecords(page, searchInput.value, filterForm.filter.value);
            });
        });
    }

    searchInput.addEventListener('input', () => {
        fetchRecords(0, searchInput.value, filterForm.filter.value);
    });

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchRecords(0, searchInput.value, filterForm.filter.value);
    });

    // Initial Fetch
    fetchRecords();
});
