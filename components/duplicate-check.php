<?php
defined('ABSPATH') || exit;

/**
 * Enqueue duplicate email check JS
 * Safe against missing helpers
 */
add_action('wp_enqueue_scripts', function () {

    // ? HARD SAFETY: function must exist
    if (!function_exists('mr_is_ssc_member_product')) {
        return;
    }

    // ? Only load on SSC Member Registration product
    if (!mr_is_ssc_member_product()) {
        return;
    }

    wp_enqueue_script(
        'mr-duplicate-check',
        MR_URL . 'assets/js/duplicate-check.js',
        [],
        '1.1',
        true
    );

    wp_localize_script('mr-duplicate-check', 'mr_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mr_check_email')
    ]);
});

/**
 * AJAX: check if member email already exists
 */
add_action('wp_ajax_mr_check_member_email', 'mr_check_member_email');
add_action('wp_ajax_nopriv_mr_check_member_email', 'mr_check_member_email');

function mr_check_member_email() {

    // ? Security
    check_ajax_referer('mr_check_email', 'nonce');

    $email = sanitize_email($_POST['email'] ?? '');

    if (!$email || !is_email($email)) {
        wp_send_json_error(['status' => 'invalid']);
    }

    wp_send_json_success([
        'status' => email_exists($email) ? 'exists' : 'ok'
    ]);
}
