<?php
defined('ABSPATH') || exit;

/**
 * Create / update user and FORCE correct role on Order Completion
 * ✅ UPDATED: Handles both standalone registration AND subscription+registration orders
 */
add_action('woocommerce_order_status_completed', 'mr_create_user', 100);

function mr_create_user($order_id)
{
    if (get_post_meta($order_id, '_mr_user_created', true))
        return;

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    $has_registration = false;
    $registration_item = null;

    // Check if order contains registration product
    foreach ($order->get_items() as $item) {
        if (has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $item->get_product_id())) {
            $has_registration = true;
            $registration_item = $item;
            break;
        }
    }

    // If no registration product, nothing to do here
    if (!$has_registration || !$registration_item) {
        return;
    }

    // Get registration data
    $email = $registration_item->get_meta('email');
    $first_name = $registration_item->get_meta('first_name');
    $last_name = $registration_item->get_meta('last_name');

    if (!$email)
        return;

    // Check if user already exists or create new one
    if (email_exists($email)) {
        $user = get_user_by('email', $email);
        $user_id = $user->ID;
    }
    else {
        $password = wp_generate_password(12, true);
        $user_id = wp_create_user($email, $password, $email);
        update_post_meta($order_id, '_mr_password', $password);
    }

    // --- EMAIL SUPPRESSION FLAG ---
    // This tells Section 14 in helpers.php to skip standard emails
    update_user_meta($user_id, '_is_new_ssc_registration', true);

    // Force SSC Role
    $user = new WP_User($user_id);
    $user->set_role('suremember-ssc-member');

    // Update user profile
    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => trim("$first_name $last_name"),
    ]);

    // Save phone to billing for WooCommerce sync
    update_user_meta($user_id, 'billing_phone', $registration_item->get_meta('phone_number'));

    // Save profile image
    $attachment_id = $registration_item->get_meta('profile_picture');
    if ($attachment_id) {
        update_user_meta($user_id, 'profile_picture', $attachment_id);
    }

    // Mark as completed
    update_post_meta($order_id, '_mr_user_id', $user_id);
    update_post_meta($order_id, '_mr_user_created', 1);
}
