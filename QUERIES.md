# Aviv Order Admin Queries

## Main Queries

### Recent Orders with Rental Dates (90 days)
```sql
SELECT DISTINCT 
    o.id as order_id,
    o.date_created_gmt,
    o.billing_email,
    o.total_amount,
    o.status,
    oi.order_item_name as product_name,
    oim.meta_value as rental_dates,
    oim2.meta_value as product_id,
    om.meta_value as billing_first_name,
    om2.meta_value as billing_last_name,
    om3.meta_value as billing_phone
FROM {prefix}wc_orders o
JOIN {prefix}woocommerce_order_items oi ON o.id = oi.order_id
LEFT JOIN {prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
LEFT JOIN {prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id 
    AND oim2.meta_key = '_product_id'
LEFT JOIN {prefix}wc_orders_meta om ON o.id = om.order_id 
    AND om.meta_key = '_billing_first_name'
LEFT JOIN {prefix}wc_orders_meta om2 ON o.id = om2.order_id 
    AND om2.meta_key = '_billing_last_name'
LEFT JOIN {prefix}wc_orders_meta om3 ON o.id = om3.order_id 
    AND om3.meta_key = '_billing_phone'
WHERE o.status != 'trash'
AND o.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY o.date_created_gmt DESC
```

### Orders by Client
```sql
SELECT DISTINCT 
    o.id as order_id,
    o.date_created_gmt,
    o.status,
    oi.order_item_name as product_name,
    oim.meta_value as rental_dates,
    CONCAT(om.meta_value, ' ', om2.meta_value) as client_name,
    om3.meta_value as phone
FROM {prefix}wc_orders o
JOIN {prefix}woocommerce_order_items oi ON o.id = oi.order_id
LEFT JOIN {prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
LEFT JOIN {prefix}wc_orders_meta om ON o.id = om.order_id 
    AND om.meta_key = '_billing_first_name'
LEFT JOIN {prefix}wc_orders_meta om2 ON o.id = om2.order_id 
    AND om2.meta_key = '_billing_last_name'
LEFT JOIN {prefix}wc_orders_meta om3 ON o.id = om3.order_id 
    AND om3.meta_key = '_billing_phone'
WHERE o.status != 'trash'
GROUP BY client_name
ORDER BY o.date_created_gmt DESC
```

### Orders by Product with Reserved Dates
```sql
SELECT 
    oi.order_item_name as product_name,
    oim2.meta_value as product_id,
    GROUP_CONCAT(
        CONCAT(
            'Order #', o.id, ': ',
            oim.meta_value
        ) 
        ORDER BY o.date_created_gmt DESC
        SEPARATOR '\n'
    ) as reserved_dates
FROM {prefix}wc_orders o
JOIN {prefix}woocommerce_order_items oi ON o.id = oi.order_id
LEFT JOIN {prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
LEFT JOIN {prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id 
    AND oim2.meta_key = '_product_id'
WHERE o.status != 'trash'
AND oim.meta_value IS NOT NULL
GROUP BY product_name
ORDER BY product_name ASC
```

## Table Structure

### WooCommerce Order Tables
- `{prefix}wc_orders`: Main orders table
- `{prefix}woocommerce_order_items`: Order line items
- `{prefix}woocommerce_order_itemmeta`: Order item metadata
- `{prefix}wc_orders_meta`: Order metadata

### Important Meta Keys
- `Rental Dates`: Stored in order_itemmeta
- `_product_id`: Product ID in order_itemmeta
- `_billing_first_name`: Customer first name
- `_billing_last_name`: Customer last name
- `_billing_phone`: Customer phone number

## Notes
- Replace `{prefix}` with your actual table prefix (e.g., `gjc_`)
- All queries exclude trashed orders
- Dates are stored in the format DD.MM.YYYY
- Some orders may have multiple rental dates
