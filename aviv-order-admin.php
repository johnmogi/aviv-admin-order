<?php
/**
 * Plugin Name: Aviv Order Admin
 * Description: Simple order management system
 * Version: 1.0.0
 * Author: John Mogi
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Aviv Orders',
        'Aviv Orders',
        'manage_woocommerce',
        'aviv-orders',
        'render_orders_page',
        'dashicons-calendar-alt',
        30
    );
});

// Enqueue scripts
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_aviv-orders') return;

    wp_enqueue_script('aviv-admin', plugins_url('js/admin.js', __FILE__), ['jquery'], '1.0.0', true);
    wp_localize_script('aviv-admin', 'avivAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aviv_admin_nonce')
    ));
});

// Render admin page
function render_orders_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized');
    }
    ?>
    <div class="wrap">
        <h1>Aviv Orders</h1>
        
        <div class="aviv-filters">
            <select id="product-filter">
                <option value="">All Products</option>
                <?php
                $products = wc_get_products(['limit' => -1]);
                foreach ($products as $product) {
                    printf(
                        '<option value="%s">%s</option>',
                        esc_attr($product->get_id()),
                        esc_html($product->get_name())
                    );
                }
                ?>
            </select>

            <select id="client-filter">
                <option value="">All Clients</option>
                <?php
                $customers = get_users(['role' => 'customer']);
                foreach ($customers as $customer) {
                    printf(
                        '<option value="%s">%s</option>',
                        esc_attr($customer->ID),
                        esc_html($customer->display_name)
                    );
                }
                ?>
            </select>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Client</th>
                    <th>Product</th>
                    <th>Dates</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody id="orders-list">
                <tr><td colspan="5">Loading orders...</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}

// AJAX handler for getting recent orders
add_action('wp_ajax_get_recent_orders', function() {
    check_ajax_referer('aviv_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $days = 90; // Show orders from last 90 days
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    
    $where = [];
    $where[] = $wpdb->prepare("o.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    
    if ($product_id) {
        $where[] = $wpdb->prepare("oi.product_id = %d", $product_id);
    }
    
    if ($client_id) {
        $where[] = $wpdb->prepare("pm_customer.meta_value = %d", $client_id);
    }
    
    $where_clause = implode(' AND ', $where);
    
    $query = "
        SELECT DISTINCT
            o.ID as order_id,
            u.display_name as client_name,
            p.post_title as product_name,
            DATE_FORMAT(bd.start_date, '%Y-%m-%d') as start_date,
            DATE_FORMAT(bd.end_date, '%Y-%m-%d') as end_date,
            pm_total.meta_value as total
        FROM {$wpdb->posts} o
        JOIN {$wpdb->prefix}mogi_booking_dates bd ON bd.order_id = o.ID
        JOIN {$wpdb->postmeta} pm_customer ON pm_customer.post_id = o.ID AND pm_customer.meta_key = '_customer_user'
        JOIN {$wpdb->users} u ON u.ID = pm_customer.meta_value
        JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = o.ID AND pm_total.meta_key = '_order_total'
        JOIN {$wpdb->posts} p ON p.ID = bd.product_id
        WHERE o.post_type = 'shop_order'
        AND {$where_clause}
        ORDER BY o.post_date DESC
        LIMIT 50
    ";
    
    $orders = $wpdb->get_results($query);
    wp_send_json_success(['orders' => $orders]);
});
