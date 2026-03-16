<?php
/**
 * Plugin Name: Member Registration (Woo + ACF)
 * Author: Vivek Nath
 * Version: 1.3.0
 */

defined('ABSPATH') || exit;

// 1. Define Plugin Constants
define('MR_PATH', plugin_dir_path(__FILE__));
define('MR_URL', plugin_dir_url(__FILE__));
define('MR_FILE', __FILE__); // Required for rewrite rules and activation hooks

// Product & Category Constants (Centralized for easy maintenance)
define('MR_REGISTRATION_PRODUCT_ID', 2341);
define('MR_REGISTRATION_CATEGORY', 'ssc-member-registration');
define('MR_SUBSCRIPTION_CATEGORY', 'memberships');
define('MR_DEFAULT_AVATAR', 'https://shortstaycircle.com/wp-content/uploads/2026/01/icon-7797704_640.png');

/**
 * Get subscription product IDs (function for PHP < 7.0 compatibility)
 */
function mr_get_subscription_product_ids()
{
    return array(1947, 1871);
}

/**
 * Global Helper: Check if current page is the SSC Member product
 */
function mr_is_ssc_member_product()
{
    if (!function_exists('is_product') || !is_product())
        return false;
    $product_id = get_queried_object_id();
    return $product_id ? has_term('ssc-member-registration', 'product_cat', $product_id) : false;
}

/**
 * Load CSS Assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('mr-custom-style', MR_URL . 'assets/css/mr-style.css', array(), '1.2.0');
});

/**
 * Ensure default WooCommerce "Edit Account" form supports file uploads
 */
add_filter('woocommerce_edit_account_form_tag', function ($tag) {
    if (strpos($tag, 'enctype') === false) {
        $tag = str_replace('<form', '<form enctype="multipart/form-data"', $tag);
    }
    return $tag;
});

// 2. Load Components
require_once MR_PATH . 'components/helpers.php';
require_once MR_PATH . 'components/product-fields.php';
require_once MR_PATH . 'components/cart-order-handler.php';
require_once MR_PATH . 'components/duplicate-check.php';
require_once MR_PATH . 'components/user-creator.php';
require_once MR_PATH . 'components/member-post-creator.php';
require_once MR_PATH . 'components/auto-login.php';

/**
 * DEPRECATED: Standard welcome email logic moved to helpers.php Sections 16 & 17
 * require_once MR_PATH . 'components/welcome-email.php'; 
 */

require_once MR_PATH . 'components/profile-edit.php';
require_once MR_PATH . 'components/woocommerce-edit-profile-tab.php';