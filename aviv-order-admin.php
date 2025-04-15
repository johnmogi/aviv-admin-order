<?php
/**
 * Plugin Name: Aviv Order Admin
 * Description: Custom order management for Aviv Rental System
 * Version: 1.0.0
 * Author: John Mogi
 */

if (!defined('ABSPATH')) {
    exit;
}

// Required WordPress core includes
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(ABSPATH . 'wp-includes/formatting.php');
require_once(ABSPATH . 'wp-includes/capabilities.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// WooCommerce dependency check
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Aviv Order Admin requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__) . '/../../../../wp-load.php');
}

// Include required WordPress core files
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-includes/formatting.php');
require_once(ABSPATH . 'wp-includes/capabilities.php');

// Debug: Check table prefix
global $wpdb;
$actual_prefix = $wpdb->get_var("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '%wc_orders' LIMIT 1");
$actual_prefix = str_replace('wc_orders', '', $actual_prefix);

// Include WordPress core files
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(ABSPATH . 'wp-includes/link-template.php');

// Create required tables on plugin activation
register_activation_hook(__FILE__, function() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mogi_booking_dates';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY product_id (product_id)
    ) $charset_collate;";

    dbDelta($sql);
});

// Check if WooCommerce is active
add_action('admin_init', function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="error"><p>This plugin requires WooCommerce to be installed and active.</p></div>';
        });
    }
});

// Add admin menu
function aviv_admin_menu() {
    add_menu_page(
        'Aviv Order Admin',
        'Aviv Orders',
        'manage_options',
        'aviv-order-admin',
        'aviv_render_admin_page',
        'dashicons-calendar-alt'
    );
}
add_action('admin_menu', 'aviv_admin_menu');

function aviv_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="aviv-filters">
            <form method="get">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="text" 
                               name="search" 
                               placeholder="Search by order ID or client email"
                               value="<?php echo esc_attr($_GET['search'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <select name="status">
                            <option value=""><?php esc_html_e('All Statuses', 'aviv-order-admin'); ?></option>
                            <?php
                            $statuses = wc_get_order_statuses();
                            $current_status = $_GET['status'] ?? '';
                            foreach ($statuses as $status => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($status),
                                    selected($current_status, $status, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <input type="date" 
                               name="date_from" 
                               placeholder="From Date"
                               value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <input type="date" 
                               name="date_to" 
                               placeholder="To Date"
                               value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <button type="submit" class="button button-primary">Apply Filters</button>
                        <button type="button" class="button">Reset</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="aviv-admin-section">
            <h2><?php esc_html_e('Recent Orders', 'aviv-order-admin'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Order', 'aviv-order-admin'); ?></th>
                        <th scope="col"><?php esc_html_e('Date', 'aviv-order-admin'); ?></th>
                        <th scope="col"><?php esc_html_e('Product', 'aviv-order-admin'); ?></th>
                        <th scope="col"><?php esc_html_e('Rental Period', 'aviv-order-admin'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'aviv-order-admin'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'aviv-order-admin'); ?></th>
                    </tr>
                </thead>
                <tbody id="orders-list">
                    <tr>
                        <td colspan="6" class="aviv-loading">
                            <?php esc_html_e('Loading orders...', 'aviv-order-admin'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Quick View Modal -->
        <div id="aviv-quick-view-modal" class="aviv-modal">
            <div class="aviv-modal-content">
                <span class="aviv-modal-close">&times;</span>
                <div id="aviv-quick-view-content"></div>
            </div>
        </div>
    </div>
    <?php
}

// Enqueue scripts and styles
function aviv_admin_enqueue_scripts($hook) {
    if ('toplevel_page_aviv-order-admin' !== $hook) {
        return;
    }
    
    wp_enqueue_style('aviv-admin', plugins_url('css/admin.css', __FILE__), [], '1.0.1');
    wp_enqueue_script('aviv-admin', plugins_url('js/admin.js', __FILE__), ['jquery'], '1.0.1', true);
    wp_localize_script('aviv-admin', 'avivAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aviv_admin_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'aviv_admin_enqueue_scripts');

// Helper function to format order status
function aviv_format_status($status) {
    $status = str_replace('wc-', '', $status);
    $statuses = wc_get_order_statuses();
    $label = isset($statuses['wc-' . $status]) ? $statuses['wc-' . $status] : ucfirst($status);
    return sprintf(
        '<span class="aviv-status aviv-status-%s">%s</span>',
        esc_attr($status),
        esc_html($label)
    );
}

// Helper function to format price
function aviv_format_price($price) {
    return sprintf(
        '<span class="aviv-total">%s</span>',
        wc_price($price)
    );
}

// Helper function to format rental dates
function aviv_format_rental_dates($dates) {
    if (!$dates) {
        return '<span class="no-dates">' . esc_html__('No dates specified', 'aviv-order-admin') . '</span>';
    }
    
    return sprintf(
        '<div class="rental-period">
            <span class="rental-date start-date">%s</span>
            <span class="rental-separator">→</span>
            <span class="rental-date end-date">%s</span>
        </div>',
        esc_html($dates['start_date']),
        esc_html($dates['end_date'])
    );
}

// AJAX handler for quick view
add_action('wp_ajax_aviv_quick_view_order', 'aviv_quick_view_order');
function aviv_quick_view_order() {
    check_ajax_referer('aviv_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
        return;
    }
    
    ob_start();
    ?>
    <div class="aviv-quick-view">
        <h2><?php printf(esc_html__('Order #%s Details', 'aviv-order-admin'), $order->get_order_number()); ?></h2>
        
        <div class="order-details">
            <div class="order-meta">
                <p>
                    <strong><?php esc_html_e('Date:', 'aviv-order-admin'); ?></strong>
                    <?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
                </p>
                
                <p>
                    <strong><?php esc_html_e('Status:', 'aviv-order-admin'); ?></strong>
                    <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                        <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                    </span>
                </p>
                
                <p>
                    <strong><?php esc_html_e('Customer:', 'aviv-order-admin'); ?></strong>
                    <?php
                    $billing_name = trim($order->get_formatted_billing_full_name());
                    echo $billing_name ? esc_html($billing_name) : esc_html__('Guest', 'aviv-order-admin');
                    
                    if ($order->get_billing_email()) {
                        echo '<br><span class="aviv-client-email">' . esc_html($order->get_billing_email()) . '</span>';
                    }
                    
                    if ($order->get_billing_phone()) {
                        echo '<br><span class="aviv-client-phone">' . esc_html($order->get_billing_phone()) . '</span>';
                    }
                    ?>
                </p>
                
                <p>
                    <strong><?php esc_html_e('Total:', 'aviv-order-admin'); ?></strong>
                    <?php echo aviv_format_price($order->get_total()); ?>
                </p>
            </div>
            
            <div class="order-items">
                <table>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'aviv-order-admin'); ?></th>
                            <th><?php esc_html_e('Rental Period', 'aviv-order-admin'); ?></th>
                            <th><?php esc_html_e('Total', 'aviv-order-admin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($order->get_items() as $item_id => $item) {
                            $product = $item->get_product();
                            $rental_dates = aviv_get_rental_dates($order_id, $product ? $product->get_id() : 0);
                            ?>
                            <tr>
                                <td>
                                    <?php
                                    if ($product && $product->get_permalink()) {
                                        printf(
                                            '<a href="%s" target="_blank">%s</a>',
                                            esc_url($product->get_permalink()),
                                            esc_html($item->get_name())
                                        );
                                    } else {
                                        echo esc_html($item->get_name());
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo aviv_format_rental_dates($rental_dates); ?>
                                </td>
                                <td><?php echo aviv_format_price($item->get_total()); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

// AJAX handler for getting recent orders
add_action('wp_ajax_get_recent_orders', 'aviv_load_orders');

function aviv_load_orders() {
    // Prevent any output before our JSON response
    ob_start();
    
    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aviv_admin_nonce')) {
            throw new Exception('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            throw new Exception('Unauthorized access');
        }

        global $wpdb;
        
        // Get orders with rental dates
        $orders_query = $wpdb->prepare("
            SELECT DISTINCT 
                o.id as order_id,
                o.date_created_gmt,
                o.billing_email,
                o.total_amount,
                o.status,
                oi.order_item_id,
                oi.order_item_name as product_name,
                oim.meta_value as rental_dates,
                oim2.meta_value as product_id,
                om.meta_value as billing_first_name,
                om2.meta_value as billing_last_name,
                om3.meta_value as billing_phone
            FROM {$wpdb->prefix}wc_orders o
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = 'Rental Dates'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id AND om.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->prefix}wc_orders_meta om2 ON o.id = om2.order_id AND om2.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->prefix}wc_orders_meta om3 ON o.id = om3.order_id AND om3.meta_key = '_billing_phone'
            WHERE o.status NOT IN ('trash', 'auto-draft')
            ORDER BY o.date_created_gmt DESC
            LIMIT 100",
            'trash'
        );

        $orders = $wpdb->get_results($orders_query);
        
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send JSON response
        wp_send_json_success([
            'orders' => array_map(function($order) {
                return [
                    'order_id' => $order->order_id,
                    'date_created_gmt' => $order->date_created_gmt,
                    'billing_email' => $order->billing_email,
                    'billing_first_name' => $order->billing_first_name,
                    'billing_last_name' => $order->billing_last_name,
                    'billing_phone' => $order->billing_phone,
                    'product_name' => $order->product_name,
                    'rental_dates' => $order->rental_dates,
                    'product_id' => $order->product_id,
                    'status' => $order->status,
                    'total_amount' => wc_price($order->total_amount)
                ];
            }, $orders),
            'total' => count($orders)
        ]);

    } catch (Exception $e) {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        wp_send_json_error([
            'message' => $e->getMessage()
        ]);
    }
    
    wp_die();
}

function aviv_get_rental_dates($order_id, $product_id) {
    global $wpdb;
    
    $dates = $wpdb->get_row($wpdb->prepare(
        "SELECT DATE_FORMAT(start_date, %s) as start_date, DATE_FORMAT(end_date, %s) as end_date
        FROM {$wpdb->prefix}mogi_booking_dates
        WHERE order_id = %d AND product_id = %d
        LIMIT 1",
        get_option('date_format'),
        get_option('date_format'),
        $order_id,
        $product_id
    ));
    
    return $dates ? [
        'start_date' => $dates->start_date,
        'end_date' => $dates->end_date
    ] : null;
}
