jQuery(document).ready(function($) {
    console.log('Aviv Admin initialized');
    
    // Load initial orders
    loadOrders();

    // Handle product filter change
    $('#product-filter').on('change', function() {
        loadOrders();
    });

    // Handle client filter change
    $('#client-filter').on('change', function() {
        loadOrders();
    });

    function loadOrders() {
        const productId = $('#product-filter').val();
        const clientId = $('#client-filter').val();
        
        $('#orders-list').html('<tr><td colspan="5">Loading orders...</td></tr>');
        
        $.ajax({
            url: avivAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_recent_orders',
                nonce: avivAdmin.nonce,
                product_id: productId,
                client_id: clientId
            },
            success: function(response) {
                if (response.success) {
                    renderOrders(response.data.orders);
                } else {
                    $('#orders-list').html('<tr><td colspan="5">Error: ' + (response.data || 'Failed to load orders') + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                $('#orders-list').html('<tr><td colspan="5">Error loading orders. Please try again.</td></tr>');
            }
        });
    }

    function renderOrders(orders) {
        const tbody = $('#orders-list');
        tbody.empty();

        if (!orders || !orders.length) {
            tbody.html('<tr><td colspan="5">No orders found</td></tr>');
            return;
        }

        orders.forEach(function(order) {
            tbody.append(`
                <tr>
                    <td><a href="post.php?post=${order.order_id}&action=edit">#${order.order_id}</a></td>
                    <td>${order.client_name}</td>
                    <td>${order.product_name}</td>
                    <td>${order.start_date} - ${order.end_date}</td>
                    <td>${order.total}</td>
                </tr>
            `);
        });
    }
});
