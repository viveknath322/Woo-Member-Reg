<?php
defined('ABSPATH') || exit;

/**
 * 0. GLOBAL HELPER (Defined at the top to prevent "Undefined Function" errors)
 * Check if the Registration Product is in the current cart.
 */
function mr_is_membership_in_cart()
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['product_id']) && $cart_item['product_id'] == MR_REGISTRATION_PRODUCT_ID) {
            return true;
        }
    }
    return false;
}

/**
 * 0B. NEW HELPER: Check if subscription product is in cart
 */
function mr_has_subscription_in_cart()
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        // Check by category OR specific product IDs
        $product_id = $cart_item['product_id'];
        if (has_term(MR_SUBSCRIPTION_CATEGORY, 'product_cat', $product_id) ||
        in_array($product_id, mr_get_subscription_product_ids())) {
            return true;
        }
    }
    return false;
}

/**
 * 1. ENQUEUE ASSETS & BODY CLASSES
 */
add_action('wp_enqueue_scripts', function () {
    if (mr_is_ssc_member_product() || is_account_page()) {
        wp_enqueue_style('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css');
        wp_enqueue_script('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js', [], '1.5.13', true);
        wp_enqueue_style('mr-custom-style', MR_URL . 'assets/css/mr-style.css', [], time());
    }
}, 20);

add_filter('body_class', function ($classes) {
    if (mr_is_membership_in_cart()) {
        $classes[] = 'mr-is-membership';
    }
    return $classes;
});

/**
 * 2. IMAGE PROCESSING (Cropper & Profile Photo)
 */
function mr_upload_base64_image($base64_data, $filename)
{
    if (empty($base64_data))
        return false;
    list($type, $base64_data) = explode(';', $base64_data);
    list(, $base64_data) = explode(',', $base64_data);
    $data = base64_decode($base64_data);
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . sanitize_file_name($filename);
    file_put_contents($file_path, $data);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title' => $filename,
        'post_status' => 'inherit'
    ];
    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file_path));
    return $attach_id;
}

/**
 * 3. ROLE CHECK (SSC Member Verification)
 */
function mr_is_ssc_member_user($user_id = 0)
{
    if (!$user_id)
        $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    return $user && in_array('suremember-ssc-member', (array)$user->roles, true);
}

/**
 * 4. AVATAR FILTER (Global Site Avatars)
 */
add_filter('get_avatar', function ($avatar, $id_or_email, $size, $default, $alt) {
    $default_placeholder = MR_DEFAULT_AVATAR;
    $user = false;
    if ($id_or_email instanceof WP_User) {
        $user = $id_or_email;
    }
    elseif (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int)$id_or_email);
    }
    $url = $default_placeholder;
    if ($user) {
        $attachment_id = get_user_meta($user->ID, 'profile_picture', true);
        if ($attachment_id) {
            $user_pic = wp_get_attachment_image_url($attachment_id, [$size, $size]);
            if ($user_pic)
                $url = $user_pic;
        }
    }
    return sprintf('<img src="%s" alt="%s" class="avatar" width="%d" height="%d" style="border-radius:50%%;object-fit:cover;" />', esc_url($url), esc_attr($alt), (int)$size, (int)$size);
}, 10, 5);

/**
 * 5. ACF IMAGE FALLBACK (Archive & Loop Grid)
 */
add_filter('acf/load_value/name=profile_picture', function ($value) {
    $default_placeholder = MR_DEFAULT_AVATAR;
    if (empty($value) || $value === false || $value === '0') {
        if (!is_admin()) {
            return [
            'url' => $default_placeholder,
            'alt' => 'Member Profile',
            'id' => 0
            ];
        }
        return $default_placeholder;
    }
    return $value;
}, 30);

/**
 * 6. CART & CHECKOUT REDIRECTS (UPDATED FOR SUBSCRIPTIONS)
 */
add_action('woocommerce_add_cart_item_data', function ($cart_item_data) {
    if (isset($_REQUEST['add-to-cart'])) {
        $product_id = absint($_REQUEST['add-to-cart']);

        // Only empty cart for registration product if NO subscription exists
        if (has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $product_id)) {

            // Safety check
            if (!function_exists('WC') || !WC()->cart) {
                return $cart_item_data;
            }

            // ✅ Check if there's already a subscription product in cart
            $has_subscription = false;
            foreach (WC()->cart->get_cart() as $cart_item) {
                $item_product_id = $cart_item['product_id'];
                if (has_term(MR_SUBSCRIPTION_CATEGORY, 'product_cat', $item_product_id) ||
                in_array($item_product_id, mr_get_subscription_product_ids())) {
                    $has_subscription = true;
                    break;
                }
            }

            // Only empty if no subscription
            if (!$has_subscription) {
                WC()->cart->empty_cart();
            }
        }
    }
    return $cart_item_data;
}, 5, 1);

add_filter('woocommerce_add_to_cart_redirect', function ($url) {
    if (isset($_REQUEST['add-to-cart'])) {
        $product_id = absint($_REQUEST['add-to-cart']);

        // ✅ NEW: Redirect subscription products to registration page
        if (has_term(MR_SUBSCRIPTION_CATEGORY, 'product_cat', $product_id) ||
        in_array($product_id, mr_get_subscription_product_ids())) {

            // If user is already a member, go to checkout directly
            if (is_user_logged_in() && function_exists('mr_is_ssc_member_user') && mr_is_ssc_member_user()) {
                return wc_get_checkout_url();
            }

            // Otherwise, go to registration page
            return home_url('/register/');
        }

        // Existing logic: Registration product goes to checkout
        if (has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $product_id)) {
            return wc_get_checkout_url();
        }
    }
    return $url;
}, 10, 1);

add_filter('woocommerce_order_button_text', function ($text) {
    if (!WC()->cart)
        return $text;
    foreach (WC()->cart->get_cart() as $item) {
        if (has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $item['product_id'])) {
            return 'Activate Membership';
        }
    }
    return $text;
});

/**
 * 7. SKIP THANK YOU PAGE
 */
add_filter('woocommerce_get_checkout_order_received_url', function ($url, $order) {
    set_transient('mr_welcome_notice_' . get_current_user_id(), true, 60);
    return wc_get_page_permalink('myaccount');
}, 10, 2);

/**
 * 8. WELCOME POPUP ON DASHBOARD
 */
add_action('woocommerce_account_dashboard', function () {
    $user_id = get_current_user_id();
    if (get_transient('mr_welcome_notice_' . $user_id)) {
        delete_transient('mr_welcome_notice_' . $user_id);
        $user_info = get_userdata($user_id);
        $first_name = !empty($user_info->first_name) ? $user_info->first_name : 'there';
?>
        <div id="mr-welcome-popup" class="mr-welcome-overlay" role="dialog">
            <div class="mr-welcome-card">
                <span id="close-welcome-popup" class="mr-welcome-close">&times;</span>
                <h3 class="mr-welcome-title">Welcome to the Circle, <?php echo esc_html($first_name); ?>!</h3>
                <p class="mr-welcome-text">Your membership is now active. We're so excited to have you with us! What would you like to do first?</p>
                <div class="mr-welcome-actions">
                    <a href="/ssc-members/" class="mr-btn-primary">See All Members</a>
                    <a href="/events/" class="mr-btn-outline">Upcoming Events</a>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const welcomePopup = document.getElementById("mr-welcome-popup");
                const closeIcon = document.getElementById("close-welcome-popup");
                if (closeIcon && welcomePopup) {
                    closeIcon.onclick = () => {
                        welcomePopup.classList.add('fade-out');
                        setTimeout(() => { welcomePopup.style.display = 'none'; }, 400);
                    };
                }
            });
        </script>
        <?php
    }
});

/**
 * 9. EMERGENCY SYNC
 */
add_action('init', function () {
    if (!isset($_GET['sync_all_members']))
        return;
    $members = get_posts(['post_type' => 'member', 'posts_per_page' => -1]);
    foreach ($members as $member) {
        $user = get_userdata(get_post_field('post_author', $member->ID));
        if ($user) {
            update_field('email', $user->user_email, $member->ID);
        }
    }
    wp_die("Sync complete!");
});

/**
 * 10. REMOVE SUCCESS NOTICES ON CHECKOUT AND REGISTRATION PAGE
 */
add_action('wp_head', function () {
    // Clear on checkout page
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
        wc_clear_notices();
    }
    // Clear on registration product page
    if (function_exists('mr_is_ssc_member_product') && mr_is_ssc_member_product()) {
        wc_clear_notices();
    }
});

/**
 * 11. PREMIUM MEMBER PRICE TEASER
 */
function mr_get_member_price_teaser_html()
{
    global $product;
    if (!$product)
        $product = wc_get_product(get_the_ID());
    if (!$product)
        return '';
    if (function_exists('mr_is_ssc_member_user') && mr_is_ssc_member_user())
        return '';

    $product_id = $product->get_id();
    $role_slug = 'suremember-ssc-member';
    $final_price = '';
    $pricing_rules = get_post_meta($product_id, '_role_based_pricing_rules', true);

    if (!empty($pricing_rules) && is_array($pricing_rules)) {
        if (isset($pricing_rules[$role_slug])) {
            $rule = $pricing_rules[$role_slug];
            $final_price = (!empty($rule['sale_price'])) ? $rule['sale_price'] : ($rule['regular_price'] ?? '');
        }
    }

    if (!empty($final_price)) {
        ob_start(); ?>
        <div class="mr-premium-price-card">
            <div class="mr-card-badge">Member Exclusive</div>
            <div class="mr-card-body">
                <div class="mr-price-display"><?php echo wc_price($final_price); ?></div>
                <a href="/register/" class="mr-card-link">Join the Circle & Save</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    return '';
}
add_shortcode('member_price_teaser', 'mr_get_member_price_teaser_html');

/**
 * 12. CUSTOM REGISTER URL (REQUEST HIJACK)
 */
add_action('parse_request', function ($wp) {
    if (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') === 'register') {
        $wp->query_vars['post_type'] = 'product';
        $wp->query_vars['p'] = MR_REGISTRATION_PRODUCT_ID;
        $wp->query_vars['name'] = 'register';
        remove_action('template_redirect', 'redirect_canonical');
        remove_action('template_redirect', 'wc_template_redirect', 10);
        add_filter('redirect_canonical', '__return_false');
        add_filter('woocommerce_redirect_canonical', '__return_false');
        return $wp;
    }
}, 1);

add_action('init', function () {
    add_rewrite_rule('^register/?$', 'index.php?product=register', 'top');
});

/**
 * 13. MEMBER LIST HELPER (Manual Cleanup Assistance)
 */
add_filter('manage_member_posts_columns', function ($columns) {
    $columns['linked_user'] = 'Linked User ID';
    return $columns;
});

add_action('manage_member_posts_custom_column', function ($column, $post_id) {
    if ($column === 'linked_user') {
        $author_id = get_post_field('post_author', $post_id);
        echo $author_id ? '<strong>ID: ' . esc_html($author_id) . '</strong>' : '<span style="color:red;">No User Linked</span>';
    }
}, 10, 2);

/**
 * 14. ORDER EMAIL SUPPRESSION
 */
add_filter('woocommerce_email_enabled_customer_processing_order', 'mr_stop_order_emails', 10, 2);
add_filter('woocommerce_email_enabled_customer_completed_order', 'mr_stop_order_emails', 10, 2);

function mr_stop_order_emails($enabled, $object)
{
    if (isset($_POST['add-to-cart']) && $_POST['add-to-cart'] == MR_REGISTRATION_PRODUCT_ID)
        return false;
    if (is_a($object, 'WC_Order')) {
        foreach ($object->get_items() as $item) {
            if ($item->get_product_id() == MR_REGISTRATION_PRODUCT_ID)
                return false;
        }
    }
    return $enabled;
}

/**
 * 15. FORCE ACCOUNT CREATION FOR MEMBERSHIP
 */
add_filter('woocommerce_checkout_registration_enabled', 'mr_force_registration_logic', 100);
function mr_force_registration_logic($enabled)
{
    if (mr_is_membership_in_cart())
        return true;
    return $enabled;
}

add_filter('woocommerce_create_account_default_checked', function ($checked) {
    if (mr_is_membership_in_cart())
        return true;
    return $checked;
});

add_filter('woocommerce_checkout_fields', function ($fields) {
    if (mr_is_membership_in_cart() && !is_user_logged_in()) {
        $fields['account']['account_password']['required'] = true;
        $fields['account']['account_password']['placeholder'] = 'Create a secure password';
    }
    return $fields;
}, 100);

/**
 * 16. CLASSIC EDITOR AUTHOR DROPDOWN FIX
 * Forces members to appear in the Author dropdown by removing 
 * the requirement for editing capabilities.
 */
add_filter('wp_dropdown_users_args', function ($query_args) {
    // Only target the Member CPT in the admin dashboard
    if (is_admin() && get_post_type() === 'member') {

        // 1. Explicitly include your specific member role
        $query_args['role__in'] = ['administrator', 'suremember-ssc-member'];

        // 2. IMPORTANT: Remove 'who', which defaults to 'authors'
        // This 'who' argument is what filters out non-admins/non-editors
        unset($query_args['who']);

        // 3. Ensure no capability check is being performed
        unset($query_args['capability']);
    }
    return $query_args;
}, 999);

/**
 * 17. ✅ NEW: BYPASS CART PAGE FOR SUBSCRIPTION + REGISTRATION FLOW
 * Redirect cart to checkout when both products are present
 */
add_action('template_redirect', function () {
    // Safety check: Ensure WooCommerce and cart are available
    if (!function_exists('is_cart') || !is_cart() || is_checkout()) {
        return;
    }

    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $has_registration = false;
    $has_subscription = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];

        if ($product_id == MR_REGISTRATION_PRODUCT_ID) {
            $has_registration = true;
        }

        if (has_term(MR_SUBSCRIPTION_CATEGORY, 'product_cat', $product_id) ||
        in_array($product_id, mr_get_subscription_product_ids())) {
            $has_subscription = true;
        }
    }

    // If both are present, skip cart and go to checkout
    if ($has_registration && $has_subscription) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}, 5);
