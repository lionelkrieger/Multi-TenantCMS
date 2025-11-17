// availability-checker.js - JavaScript for checking room availability (e.g., on booking page)

document.addEventListener('DOMContentLoaded', function() {
    console.log('Availability Checker Loaded');

    // Initialize availability checker on the booking page
    initializeAvailabilityChecker();
});

function initializeAvailabilityChecker() {
    // Get DOM elements - adjust selectors based on your actual form structure
    const checkInInput = document.querySelector('#check-in-date'); // Example ID
    const checkOutInput = document.querySelector('#check-out-date'); // Example ID
    const roomTypeSelect = document.querySelector('#room-type'); // Example ID
    const availabilityResultDiv = document.querySelector('#availability-result'); // Div to show results
    const searchButton = document.querySelector('#check-availability-btn'); // Example ID for search button

    if (!checkInInput || !checkOutInput || !roomTypeSelect || !availabilityResultDiv || !searchButton) {
        console.warn('Availability checker elements not found on this page.');
        return; // Exit if required elements are not present
    }

    // Attach event listener to the search button
    searchButton.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default form submission if it's part of a form
        checkAvailability();
    });

    // Alternatively, you could attach to date/room type change events for real-time checking
    // checkInInput.addEventListener('change', checkAvailability);
    // checkOutInput.addEventListener('change', checkAvailability);
    // roomTypeSelect.addEventListener('change', checkAvailability);

    function checkAvailability() {
        const checkInDate = checkInInput.value;
        const checkOutDate = checkOutInput.value;
        const roomTypeId = roomTypeSelect.value;

        if (!checkInDate || !checkOutDate) {
            availabilityResultDiv.innerHTML = '<p class="error-message">Please select both check-in and check-out dates.</p>';
            return;
        }

        if (!roomTypeId) {
            availabilityResultDiv.innerHTML = '<p class="error-message">Please select a room type.</p>';
            return;
        }

        // Basic date validation (ensure check-out is after check-in)
        const startDate = new Date(checkInDate);
        const endDate = new Date(checkOutDate);
        if (endDate <= startDate) {
            availabilityResultDiv.innerHTML = '<p class="error-message">Check-out date must be after check-in date.</p>';
            return;
        }

        // Show loading state
        availabilityResultDiv.innerHTML = '<p>Checking availability...</p>';

        // Prepare data for API call
        const queryParams = new URLSearchParams({
            room_type_id: roomTypeId,
            check_in_date: checkInDate,
            check_out_date: checkOutDate
        }).toString();

        // Call the API endpoint (adjust URL as needed)
        apiCall(`/api/hotel/availability?${queryParams}`, 'GET')
            .then(data => {
                if (data.available_count !== undefined) {
                    if (data.available_count > 0) {
                        availabilityResultDiv.innerHTML = `
                            <p class="success-message">
                                Great! There are <strong>${data.available_count}</strong> ${data.available_count === 1 ? 'room' : 'rooms'} available for your dates.
                            </p>
                            <!-- Optionally, you could enable the "Book Now" button here -->
                        `;
                        // Example: document.querySelector('#book-now-btn').disabled = false;
                    } else {
                        availabilityResultDiv.innerHTML = `
                            <p class="error-message">
                                Sorry, there are no available rooms of this type for your selected dates.
                            </p>
                        `;
                        // Example: document.querySelector('#book-now-btn').disabled = true;
                    }
                } else {
                    console.error('Unexpected API response format:', data);
                    availabilityResultDiv.innerHTML = '<p class="error-message">An error occurred while checking availability. Please try again.</p>';
                }
            })
            .catch(error => {
                console.error('Error checking availability:', error);
                // Error message already shown by apiCall
                availabilityResultDiv.innerHTML = '<p class="error-message">An error occurred while checking availability. Please try again.</p>';
            });
    }

    // Assume apiCall is available from hotel-manager.js or defined here
    // If not defined elsewhere, include the apiCall function from the previous example.
    // For brevity, I'll assume it's available.
}

// If apiCall is not defined elsewhere, uncomment the following:
/*
function apiCall(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            // Include CSRF token if needed by your system
            // 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        options.body = JSON.stringify(data);
    }

    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('API call failed:', error);
            showNotification('An error occurred while communicating with the server.', 'error');
            throw error; // Re-throw if the calling code needs to handle it
        });
}
*/