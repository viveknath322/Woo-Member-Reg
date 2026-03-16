<?php
defined('ABSPATH') || exit;

/**
 * Automatically create a Member CPT post when a WooCommerce order is completed.
 * ✅ UPDATED: Works with both standalone registration and subscription+registration orders
 */
add_action('woocommerce_order_status_completed', 'mr_create_member_post', 110);

function mr_create_member_post($order_id)
{
    if (get_post_meta($order_id, '_mr_member_created', true)) {
        return;
    }

    $order = wc_get_order($order_id);
    $user_id = get_post_meta($order_id, '_mr_user_id', true);

    if (!$order || !$user_id) {
        return;
    }

    $user = get_userdata($user_id);

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();

        // Only process registration product
        if (has_term(MR_REGISTRATION_CATEGORY, 'product_cat', $product_id)) {

            $post_title = trim($item->get_meta('first_name') . ' ' . $item->get_meta('last_name'));

            $member_post_id = wp_insert_post([
                'post_type' => 'member',
                'post_title' => $post_title,
                'post_status' => 'publish',
                'post_author' => $user_id,
            ]);

            if ($member_post_id && !is_wp_error($member_post_id)) {

                // ACF Safety Check
                if (!function_exists('update_field')) {
                    error_log('MR Plugin: ACF not available for member post ' . $member_post_id);
                    update_post_meta($order_id, '_mr_member_created', 1);
                    continue;
                }

                // Map ACF Fields
                update_field('company_name', sanitize_text_field($item->get_meta('company_name')), $member_post_id);
                update_field('job_title', sanitize_text_field($item->get_meta('job_title')), $member_post_id);
                update_field('phone_number', sanitize_text_field($item->get_meta('phone_number')), $member_post_id);
                update_field('linkedin_url', esc_url_raw($item->get_meta('linkedin_url')), $member_post_id);
                update_field('instagram_url', esc_url_raw($item->get_meta('instagram_url')), $member_post_id);
                update_field('email', $user->user_email, $member_post_id); // SYNC EMAIL
                update_field('bio', wp_kses_post($item->get_meta('bio')), $member_post_id);

                $pic_id = $item->get_meta('profile_picture');
                if ($pic_id) {
                    update_field('profile_picture', $pic_id, $member_post_id);
                }

                update_post_meta($order_id, '_mr_member_created', 1);
            }
        }
    }
}

/**
 * --- ADMIN DASHBOARD IMPROVEMENTS ---
 */
add_filter('manage_member_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['job_title'] = 'Job Title';
            $new_columns['company'] = 'Company';
            $new_columns['linked_user'] = 'Linked User';
        }
    }
    return $new_columns;
});

add_action('manage_member_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'job_title':
            echo esc_html(get_field('job_title', $post_id) ?: '—');
            break;
        case 'company':
            echo esc_html(get_field('company_name', $post_id) ?: '—');
            break;
        case 'linked_user':
            $author_id = get_post_field('post_author', $post_id);
            $user = get_userdata($author_id);
            if ($user) {
                echo '<strong>' . esc_html($user->display_name) . '</strong><br>';
                echo '<small>' . esc_html($user->user_email) . '</small>';
            }
            else {
                echo '<span style="color:red;">No User Linked</span>';
            }
            break;
    }
}, 10, 2);
