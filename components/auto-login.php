<?php
defined('ABSPATH') || exit;

add_action('woocommerce_order_status_completed', function ($order_id) {

    $user_id = get_post_meta($order_id, '_mr_user_id', true);
    if (!$user_id || is_user_logged_in())
        return;

    // Safety: Don't auto-login if this is an admin action or cron job
    if (is_admin() || (defined('DOING_CRON') && DOING_CRON))
        return;

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
}, 120);
