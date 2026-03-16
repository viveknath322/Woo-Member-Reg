<?php
defined('ABSPATH') || exit;

/**
 * Save cropped image and form data to cart
 */
add_filter('woocommerce_add_cart_item_data', function ($data, $product_id) {
    if (!has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $product_id)) {
        return $data;
    }

    // Security: Only accept whitelisted registration fields
    $allowed_fields = ['first_name', 'last_name', 'email', 'phone_number', 'company_name', 'job_title', 'bio', 'linkedin_url', 'instagram_url'];
    foreach ($allowed_fields as $key) {
        if (!empty($_POST[$key])) {
            $data[$key] = sanitize_text_field($_POST[$key]);
        }
    }

    // Process the cropped Base64 image
    if (!empty($_POST['cropped_image_data'])) {
        $attachment_id = mr_upload_base64_image($_POST['cropped_image_data'], "registration-temp-" . time() . ".png");
        if ($attachment_id) {
            $data['profile_picture'] = $attachment_id;
        }
    }

    return $data;
}, 10, 2);

/**
 * Save cart data to order item metadata
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    foreach ($values as $k => $v) {
        $item->add_meta_data($k, $v);
    }
}, 10, 3);

/**
 * Auto-fill checkout fields from cart data
 */
add_filter('woocommerce_checkout_get_value', function ($value, $input) {
    if (!WC()->cart)
        return $value;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item[$input]))
            return $cart_item[$input];
    }
    return $value;
}, 10, 2);

add_filter('woocommerce_checkout_fields', function ($fields) {
    if (!WC()->cart)
        return $fields;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!isset($cart_item['first_name']))
            continue;
        $fields['billing']['billing_first_name']['default'] = $cart_item['first_name'] ?? '';
        $fields['billing']['billing_last_name']['default'] = $cart_item['last_name'] ?? '';
        $fields['billing']['billing_email']['default'] = $cart_item['email'] ?? '';
        $fields['billing']['billing_phone']['default'] = $cart_item['phone_number'] ?? '';
        break;
    }
    return $fields;
});