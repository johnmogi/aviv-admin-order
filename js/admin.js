jQuery(document).ready(function($) {
    // Quick View Modal
    const $modal = $('#aviv-quick-view-modal');
    const $modalContent = $('#aviv-quick-view-content');
    const $modalClose = $('.aviv-modal-close');
    
    // Close modal when clicking the close button or outside the modal
    $modalClose.on('click', closeModal);
    $modal.on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            closeModal();
        }
    });
    
    function closeModal() {
        $modal.fadeOut(200);
        $modalContent.html('');
    }
    
    // Handle quick view button click
    $(document).on('click', '.quick-view-order', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        
        $modal.fadeIn(200);
        $modalContent.html('<div class="aviv-loading">Loading order details...</div>');
        
        $.ajax({
            url: avivAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aviv_quick_view_order',
                nonce: avivAdmin.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $modalContent.html(response.data.html);
                } else {
                    $modalContent.html('<div class="error">Error: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $modalContent.html('<div class="error">Error loading order details: ' + error + '</div>');
            }
        });
    });
    
    // Handle filter form submission
    $('.aviv-filters form').on('submit', function(e) {
        e.preventDefault();
        loadOrders();
    });
    
    function formatStatus(status) {
        const statusMap = {
            'wc-processing': 'Processing',
            'wc-completed': 'Completed',
            'wc-on-hold': 'On Hold',
            'wc-cancelled': 'Cancelled',
            'wc-refunded': 'Refunded',
            'wc-failed': 'Failed',
            'wc-rental-confirmed': 'Rental Confirmed',
            'wc-rental-completed': 'Rental Completed',
            'wc-rental-cancelled': 'Rental Cancelled'
        };
        
        return statusMap[status] || status;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('he-IL', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }
    
    function loadOrders() {
        const $ordersList = $('#orders-list');
        const search = $('input[name="search"]').val();
        const status = $('select[name="status"]').val();
        const dateFrom = $('input[name="date_from"]').val();
        const dateTo = $('input[name="date_to"]').val();
        
        $ordersList.html('<tr><td colspan="6" class="aviv-loading">Loading orders...</td></tr>');
        
        $.ajax({
            url: avivAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_recent_orders',
                nonce: avivAdmin.nonce,
                search: search,
                status: status,
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function(response) {
                if (response.success) {
                    const orders = response.data.orders;
                    if (orders.length === 0) {
                        $ordersList.html('<tr><td colspan="6" class="aviv-no-results">No orders found matching your criteria.</td></tr>');
                        return;
                    }
                    
                    let output = '';
                    orders.forEach(function(order) {
                        const rentalDates = order.rental_dates ? order.rental_dates.split(' - ') : [];
                        const startDate = rentalDates[0] ? formatDate(rentalDates[0]) : '';
                        const endDate = rentalDates[1] ? formatDate(rentalDates[1]) : '';
                        
                        output += `
                            <tr>
                                <td>
                                    <a href="#" 
                                       class="row-title quick-view-order" 
                                       data-order-id="${order.order_id}"
                                       title="Quick view order details">
                                        #${order.order_id}
                                    </a>
                                </td>
                                <td>${formatDate(order.date_created_gmt)}</td>
                                <td>
                                    ${order.product_url ? 
                                        `<a href="${order.product_url}" target="_blank">${order.product_name}</a>` : 
                                        order.product_name}
                                </td>
                                <td class="aviv-rental-dates">
                                    ${rentalDates.length === 2 ? `
                                        <div class="rental-period">
                                            <span class="rental-date start-date">${startDate}</span>
                                            <span class="rental-separator">→</span>
                                            <span class="rental-date end-date">${endDate}</span>
                                        </div>
                                    ` : '<span class="no-dates">No dates specified</span>'}
                                </td>
                                <td>
                                    <span class="order-status status-${order.status}">
                                        ${formatStatus(order.status)}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="${order.edit_url}" class="button button-small">View</a>
                                        ${order.product_id ? 
                                            `<a href="${order.product_edit_url}" class="button button-small">Edit Product</a>` : 
                                            ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $ordersList.html(output);
                } else {
                    console.error('Error loading orders:', response.data);
                    $ordersList.html(
                        '<tr><td colspan="6" class="aviv-no-results">Error loading orders: ' + response.data + '</td></tr>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $ordersList.html(
                    '<tr><td colspan="6" class="aviv-no-results">Error loading orders: ' + error + '</td></tr>'
                );
            }
        });
    }
    
    // Load orders on page load
    loadOrders();
    
    // Handle filter form reset
    $('.aviv-filters form .button').on('click', function() {
        if (!$(this).is('[type="submit"]')) {
            $('.aviv-filters form')[0].reset();
            loadOrders();
        }
    });
});
