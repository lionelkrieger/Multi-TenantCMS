// hotel-manager.js - Main JavaScript for the Hotel Manager extension

document.addEventListener('DOMContentLoaded', function() {
    console.log('Hotel Manager Extension Loaded');

    // Example: Initialize any common dashboard functionality here
    initializeDashboardCharts();
    setupGlobalEventListeners();
});

function initializeDashboardCharts() {
    // Placeholder for future chart initialization (e.g., using Chart.js)
    // This would fetch data from the server and render occupancy, revenue, etc.
    console.log('Initializing dashboard charts...');
    // Example pseudo-code:
    // fetch('/admin/hotel-manager/api/dashboard-data')
    //     .then(response => response.json())
    //     .then(data => {
    //         renderOccupancyChart(data.occupancy);
    //         renderRevenueChart(data.revenue);
    //     });
}

function setupGlobalEventListeners() {
    // Example: Add a listener for a common action across the hotel manager
    // e.g., a "Refresh Data" button
    document.querySelectorAll('.js-refresh-data').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Refreshing data...');
            // Implement refresh logic here
            location.reload(); // Simple example
        });
    });
}

// Utility function to show a simple notification (can be replaced with a library like Toastr)
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // Style the notification (or use CSS classes defined in CSS)
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '10px 20px',
        borderRadius: '4px',
        color: 'white',
        zIndex: 10000,
        fontSize: '14px'
    });

    // Set background color based on type
    if (type === 'error') {
        notification.style.backgroundColor = '#d9534f';
    } else if (type === 'success') {
        notification.style.backgroundColor = '#5cb85c';
    } else {
        notification.style.backgroundColor = '#5bc0de'; // info
    }

    // Add to page
    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Utility function for making API calls (using fetch)
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

// Example function that might be used in a reservation list view to confirm a reservation
function confirmReservation(reservationId) {
    if (!confirm('Are you sure you want to confirm this reservation?')) {
        return;
    }

    apiCall(`/admin/hotel-manager/reservations/${reservationId}/confirm`, 'POST')
        .then(result => {
            if (result.success) {
                showNotification('Reservation confirmed successfully!', 'success');
                // Optionally, reload the list or update the UI
                location.reload();
            } else {
                showNotification('Failed to confirm reservation: ' + (result.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error confirming reservation:', error);
        });
}