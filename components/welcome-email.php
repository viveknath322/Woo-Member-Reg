<?php
defined('ABSPATH') || exit;

add_action('woocommerce_order_status_completed', function ($order_id) {
    $user_id = get_post_meta($order_id, '_mr_user_id', true);
    if (!$user_id) return;

    $user = get_userdata($user_id);
    $key = get_password_reset_key($user);
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

    wp_mail(
        $user->user_email,
        'Welcome to SSC',
        "Hi {$user->display_name},\n\nYour account has been created successfully!\n\nPlease click the link below to set your password and access your profile:\n\n" . $reset_url . "\n\nLogin: " . wp_login_url()
    );
}, 130);