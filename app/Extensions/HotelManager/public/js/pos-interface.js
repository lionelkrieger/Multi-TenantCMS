// pos-interface.js - JavaScript for the Point of Sale interface

document.addEventListener('DOMContentLoaded', function() {
    console.log('POS Interface Loaded');

    // Initialize POS specific functionality
    initializePosInterface();
});

function initializePosInterface() {
    // Get DOM elements
    const categoryTabsContainer = document.querySelector('.category-tabs');
    const itemGrid = document.querySelector('.item-grid');
    const quantityInput = document.querySelector('.quantity-input');
    const quantityMinusBtn = document.querySelector('.quantity-btn.minus');
    const quantityPlusBtn = document.querySelector('.quantity-btn.plus');
    const confirmButton = document.querySelector('.pos-btn-confirm');
    const cancelButton = document.querySelector('.pos-btn-cancel');
    const totalAmountElement = document.querySelector('.total-amount'); // Element to show total

    // State variables
    let selectedCategory = null;
    let selectedItem = null;
    let currentQuantity = 1;
    let selectedReservationId = null; // This would come from the guest selection step
    let selectedItemPrice = 0; // Store the price of the selected item

    // Example: Get reservation ID from a hidden input or URL parameter set by the previous step
    // This is a placeholder - you need to implement how the reservation ID is passed
    selectedReservationId = document.querySelector('#selected-reservation-id')?.value || getReservationIdFromUrl(); // Implement getReservationIdFromUrl()

    if (!selectedReservationId) {
        console.error('No reservation ID found. Cannot proceed with POS.');
        showNotification('No guest/room selected. Please select a guest first.', 'error');
        // Disable POS controls
        disablePosControls();
        return;
    }


    // Attach event listeners for category tabs
    if (categoryTabsContainer) {
        categoryTabsContainer.addEventListener('click', function(e) {
            const clickedTab = e.target.closest('.category-tab');
            if (clickedTab && !clickedTab.classList.contains('active')) {
                // Deactivate current active tab
                document.querySelector('.category-tab.active')?.classList.remove('active');
                // Activate clicked tab
                clickedTab.classList.add('active');
                selectedCategory = clickedTab.dataset.categoryId; // Assuming you set data-category-id on the tab

                // Load items for the selected category
                loadItemsForCategory(selectedCategory);
            }
        });
    }

    // Attach event listeners for item selection
    if (itemGrid) {
        itemGrid.addEventListener('click', function(e) {
            const clickedItemCard = e.target.closest('.item-card');
            if (clickedItemCard) {
                // De-select current item if any
                document.querySelector('.item-card.selected')?.classList.remove('selected');
                // Select clicked item
                clickedItemCard.classList.add('selected');
                selectedItem = clickedItemCard.dataset.itemId; // Assuming you set data-item-id on the card
                selectedItemPrice = parseFloat(clickedItemCard.dataset.price); // Assuming you set data-price on the card
                console.log('Selected item:', selectedItem, 'Price:', selectedItemPrice);

                // Reset quantity to 1 when a new item is selected
                currentQuantity = 1;
                updateQuantityDisplay();
                updateTotalDisplay(); // Update total when item changes
            }
        });
    }

    // Quantity buttons
    if (quantityMinusBtn) {
        quantityMinusBtn.addEventListener('click', function() {
            if (currentQuantity > 1) {
                currentQuantity--;
                updateQuantityDisplay();
                updateTotalDisplay();
            }
        });
    }

    if (quantityPlusBtn) {
        quantityPlusBtn.addEventListener('click', function() {
            currentQuantity++;
            updateQuantityDisplay();
            updateTotalDisplay();
        });
    }

    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value, 10);
            if (isNaN(value) || value < 1) {
                value = 1;
            }
            if (value > 999) { // Optional: set a max limit
                 value = 999;
            }
            currentQuantity = value;
            this.value = currentQuantity; // Ensure display matches internal state
            updateTotalDisplay();
        });
    }


    // Confirm button
    if (confirmButton) {
        confirmButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (!selectedItem) {
                showNotification('Please select an item first.', 'error');
                return;
            }
            if (currentQuantity < 1) {
                showNotification('Quantity must be at least 1.', 'error');
                return;
            }

            // Get charged_by_user_id - this should come from the authenticated user session
            // In a real implementation, this would be passed from the backend or retrieved via an API call
            // For now, let's assume it's available somehow, maybe from a meta tag or hidden input set server-side
            const chargedByUserId = document.querySelector('#current-user-id')?.value; // Example hidden input
            if (!chargedByUserId) {
                console.error('Cannot determine who is charging. User ID not found.');
                showNotification('System error: Unable to determine user.', 'error');
                return;
            }

            addChargeToReservation(selectedReservationId, selectedItem, currentQuantity, chargedByUserId);
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();
            // Reset selections and quantities
            document.querySelector('.item-card.selected')?.classList.remove('selected');
            selectedItem = null;
            selectedItemPrice = 0;
            currentQuantity = 1;
            updateQuantityDisplay();
            updateTotalDisplay();
            // Optionally, clear the selected category view or go back to guest selection
            showNotification('Selection cleared.', 'info');
        });
    }

    // Initialize: Load items for the first category if available
    const firstCategoryTab = document.querySelector('.category-tab');
    if (firstCategoryTab) {
        selectedCategory = firstCategoryTab.dataset.categoryId;
        firstCategoryTab.classList.add('active');
        loadItemsForCategory(selectedCategory);
    }

    // Helper functions
    function updateQuantityDisplay() {
        if (quantityInput) {
            quantityInput.value = currentQuantity;
        }
    }

    function updateTotalDisplay() {
        const total = selectedItemPrice * currentQuantity;
        if (totalAmountElement) {
            totalAmountElement.textContent = `R ${total.toFixed(2)}`;
        }
    }

    function loadItemsForCategory(categoryId) {
        console.log('Loading items for category:', categoryId);
        // This would typically be an API call to fetch items for the category
        // Example API call:
        apiCall(`/api/hotel/pos/items?category_id=${categoryId}`)
            .then(data => {
                if (data.items && Array.isArray(data.items)) {
                    renderItems(data.items);
                } else {
                    console.error('Invalid data structure received for items:', data);
                    showNotification('Error loading items for this category.', 'error');
                }
            })
            .catch(error => {
                console.error('Error loading items for category:', error);
                showNotification('Failed to load items.', 'error');
            });
    }

    function renderItems(items) {
        if (!itemGrid) return;
        itemGrid.innerHTML = ''; // Clear existing items

        items.forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = 'item-card';
            itemCard.dataset.itemId = item.id; // Set data attribute
            itemCard.dataset.price = item.price; // Set data attribute for price
            itemCard.innerHTML = `
                ${item.image_path ? `<img src="${item.image_path}" alt="${item.name}" class="item-image">` : ''}
                <div class="item-name">${item.name}</div>
                <div class="item-price">R ${parseFloat(item.price).toFixed(2)}</div>
            `;
            itemGrid.appendChild(itemCard);
        });
    }

    function addChargeToReservation(reservationId, itemId, quantity, chargedByUserId) {
        const notes = prompt('Add any notes for this charge (optional):', '') || ''; // Simple prompt, consider a better UI

        const chargeData = {
            item_id: itemId,
            quantity: quantity,
            charged_by_user_id: chargedByUserId,
            notes: notes
        };

        apiCall(`/api/hotel/folio/${reservationId}/charge`, 'POST', chargeData)
            .then(result => {
                if (result.success) {
                    showNotification('Charge added successfully!', 'success');
                    // Reset selections after successful add
                    document.querySelector('.item-card.selected')?.classList.remove('selected');
                    selectedItem = null;
                    selectedItemPrice = 0;
                    currentQuantity = 1;
                    updateQuantityDisplay();
                    updateTotalDisplay();
                    // Optionally, update the folio view if it's on the same page
                    // updateFolioView(reservationId); // Implement if needed
                } else {
                    showNotification('Failed to add charge: ' + (result.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error adding charge:', error);
                // Error notification already shown by apiCall
            });
    }

    function disablePosControls() {
        // Disable all interactive elements in the POS interface
        document.querySelectorAll('.category-tab, .item-card, .quantity-btn, .quantity-input, .pos-btn').forEach(el => {
            el.disabled = true;
            el.style.opacity = '0.5';
            el.style.pointerEvents = 'none';
        });
    }

    // Example helper to get reservation ID from URL if not available via hidden input
    function getReservationIdFromUrl() {
        // Example: URL might be /pos/charge?reservation=abc123
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('reservation');
    }

    // Initialize quantity display
    updateQuantityDisplay();
    updateTotalDisplay();
}

// Assume showNotification and apiCall are available from hotel-manager.js or defined here
// If not defined elsewhere:
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