<?php
defined('ABSPATH') || exit;

/**
 * Register the custom endpoint for My Account
 */
add_action('init', function () {
    add_rewrite_endpoint('edit-profile', EP_ROOT | EP_PAGES);
});

/**
 * Security: Redirect non-members away from the Edit Profile URL
 */
add_action('template_redirect', function() {
    global $wp;

    // Check if we are on the 'edit-profile' endpoint
    if (isset($wp->query_vars['edit-profile'])) {
        
        // If user is not logged in OR is not an SSC member, redirect to My Account dashboard
        if (!is_user_logged_in() || !mr_is_ssc_member_user()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
    }
});

/**
 * Add the "Edit Profile" tab to the My Account menu ONLY for SSC Members
 */
add_filter('woocommerce_account_menu_items', function ($items) {
    
    // Completely hide the tab for non-members
    if (!function_exists('mr_is_ssc_member_user') || !mr_is_ssc_member_user()) {
        return $items;
    }

    $new_items = [];
    foreach ($items as $key => $label) {
        // Insert Edit Profile right before Logout
        if ($key === 'customer-logout') {
            $new_items['edit-profile'] = __('Edit Profile', 'member-registration');
        }
        $new_items[$key] = $label;
    }

    return $new_items;
});

/**
 * Display the profile form
 */
add_action('woocommerce_account_edit-profile_endpoint', function () {
    // This calls the shared handler from profile-edit.php
    echo mr_shared_profile_edit_handler();
});