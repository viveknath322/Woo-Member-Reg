<?php
defined('ABSPATH') || exit;

function mr_shared_profile_edit_handler()
{
    if (!is_user_logged_in())
        return '<p>Please log in.</p>';

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $member = get_posts(['post_type' => 'member', 'author' => $user_id, 'posts_per_page' => 1, 'post_status' => 'publish']);
    $member_id = $member ? $member[0]->ID : 0;

    // --- HANDLE SAVING ---
    if (isset($_POST['mr_profile_nonce']) && wp_verify_nonce($_POST['mr_profile_nonce'], 'mr_update_profile')) {
        $new_phone = sanitize_text_field($_POST['phone_number'] ?? '');
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? '')
        ]);
        update_user_meta($user_id, 'billing_phone', $new_phone);

        if (!empty($_POST['cropped_image_data'])) {
            $old_id = get_user_meta($user_id, 'profile_picture', true);
            if ($old_id)
                wp_delete_attachment($old_id, true);
            $attachment_id = mr_upload_base64_image($_POST['cropped_image_data'], "profile-" . $user_id . "-" . time() . ".png");
            if ($attachment_id) {
                update_user_meta($user_id, 'profile_picture', $attachment_id);
                if ($member_id)
                    update_field('profile_picture', $attachment_id, $member_id);
            }
        }

        if ($member_id) {
            // ACF Safety Check
            if (function_exists('update_field')) {
                // Added email sync
                update_field('email', sanitize_email($user->user_email), $member_id);
                update_field('company_name', sanitize_text_field($_POST['company_name'] ?? ''), $member_id);
                update_field('job_title', sanitize_text_field($_POST['job_title'] ?? ''), $member_id);
                update_field('bio', wp_kses_post($_POST['bio'] ?? ''), $member_id);
                update_field('linkedin_url', esc_url_raw($_POST['linkedin_url'] ?? ''), $member_id);
                update_field('instagram_url', esc_url_raw($_POST['instagram_url'] ?? ''), $member_id);
                update_field('phone_number', $new_phone, $member_id);
            }
        }
        echo '<div class="woocommerce-message">Profile updated successfully.</div>';
    }

    $pic_id = get_user_meta($user_id, 'profile_picture', true);
    $pic_url = $pic_id ? wp_get_attachment_url($pic_id) : 'https://www.gravatar.com/avatar/?d=mp';

    ob_start(); ?>
    <form method="post" enctype="multipart/form-data" class="mr-edit-profile-form">
        <?php wp_nonce_field('mr_update_profile', 'mr_profile_nonce'); ?>
        
        <div style="display:flex; flex-direction:column; align-items:center; width:fit-content; margin: 0 auto 15px auto;">
            <div class="mr-preview-area">
                <img id="avatar-preview" src="<?php echo $pic_url; ?>" alt="Current Avatar">
            </div>
            <a id="mr-change-image-link" role="button" aria-label="Change profile image">Change image</a>
        </div>
        
        <input type="file" id="mr-image-input" accept="image/*" style="display:none;" aria-hidden="true">
        <input type="hidden" name="cropped_image_data" id="cropped-image-data">

        <p>
            <label for="prof_first_name">First Name <span class="required">*</span></label>
            <input type="text" id="prof_first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
        </p>

        <p>
            <label for="prof_last_name">Last Name <span class="required">*</span></label>
            <input type="text" id="prof_last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
        </p>

        <p>
            <label for="prof_email">Email Address</label>
            <input type="email" id="prof_email" value="<?php echo esc_attr($user->user_email); ?>" disabled>
        </p>

        <p>
            <label for="prof_phone">Phone Number <span class="required">*</span></label>
            <input type="text" id="prof_phone" name="phone_number" value="<?php echo esc_attr(get_user_meta($user_id, 'billing_phone', true)); ?>" required>
        </p>

        <p>
            <label for="prof_company">Company Name <span class="required">*</span></label>
            <input type="text" id="prof_company" name="company_name" value="<?php echo esc_attr($member_id && function_exists('get_field') ? get_field('company_name', $member_id) : ''); ?>" required>
        </p>

        <p>
            <label for="prof_job_title">Job Title <span class="required">*</span></label>
            <input type="text" id="prof_job_title" name="job_title" value="<?php echo esc_attr($member_id && function_exists('get_field') ? get_field('job_title', $member_id) : ''); ?>" required>
        </p>

        <p>
            <label for="prof_linkedin">LinkedIn URL <span class="required">*</span></label>
            <input type="url" id="prof_linkedin" name="linkedin_url" value="<?php echo esc_attr($member_id && function_exists('get_field') ? get_field('linkedin_url', $member_id) : ''); ?>" required>
        </p>

        <p>
            <label for="prof_instagram">Instagram URL</label>
            <input type="url" id="prof_instagram" name="instagram_url" value="<?php echo esc_attr($member_id && function_exists('get_field') ? get_field('instagram_url', $member_id) : ''); ?>">
        </p>
        
        <p><button type="submit" class="button">Save Profile</button></p>
    </form>

    <div id="mr-crop-modal" role="dialog" aria-labelledby="edit-crop-title">
        <div class="mr-crop-container">
            <h3 id="edit-crop-title">Crop your photo</h3>
            <div class="mr-crop-wrapper"><img id="image-to-crop" alt="Image to crop"></div>
            <button type="button" id="save-crop" class="button">Apply Crop</button>
            <button type="button" id="reg-cancel-crop" class="button" style="background:#eee;color:#333;margin-left:10px;">Cancel</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('mr-image-input'), 
              link = document.getElementById('mr-change-image-link'),
              modal = document.getElementById('mr-crop-modal'), 
              img = document.getElementById('image-to-crop'), 
              hidden = document.getElementById('cropped-image-data'), 
              preview = document.getElementById('avatar-preview');
        let cropper;
        link.onclick = (e) => { e.preventDefault(); input.click(); };
        input.onchange = (e) => {
            const file = e.target.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = () => {
                img.src = reader.result;
                modal.style.display = 'flex';
                if(cropper) cropper.destroy();
                cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        };
        document.getElementById('save-crop').onclick = () => {
            const dataUrl = cropper.getCroppedCanvas({ width: 400, height: 400 }).toDataURL('image/png');
            preview.src = dataUrl;
            hidden.value = dataUrl;
            modal.style.display = 'none';
        };
        document.getElementById('reg-cancel-crop').onclick = () => {
            modal.style.display = 'none';
            input.value = '';
        };
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('mr_edit_profile', 'mr_shared_profile_edit_handler');