<?php
defined('ABSPATH') || exit;

add_action('woocommerce_before_add_to_cart_button', 'mr_render_member_fields');

function mr_render_member_fields()
{
    // Only show the registration fields for the specific membership product
    if (!mr_is_ssc_member_product())
        return;

    // ✅ NEW: If user is already an SSC member, show message and skip form
    if (is_user_logged_in() && mr_is_ssc_member_user()) {
        echo '
        <div class="woocommerce-info" style="background: #245A5C; color: #fff; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
            <p style="margin: 0; font-size: 16px;">
                ✓ You are already a member! You can proceed directly to checkout.
            </p>
        </div>';
        return;
    }

    echo '
    <fieldset class="member-registration-fields">
        <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:20px;">
            <div class="mr-preview-area"><img id="reg-avatar-preview" src="https://www.gravatar.com/avatar/?d=mp" alt="Profile Preview"></div>
            <a id="mr-reg-upload-link" role="button" aria-label="Upload profile picture">Upload profile picture</a>
        </div>
        
        <input type="file" id="reg-image-input" accept="image/*" style="display:none;" aria-hidden="true">
        <input type="hidden" name="cropped_image_data" id="reg-cropped-data">

        <div style="display:flex; gap:15px; margin-bottom:15px;">
            <div style="flex:1;">
                <label for="reg_first_name">First Name <span class="required">*</span></label>
                <input type="text" id="reg_first_name" name="first_name" required>
            </div>
            <div style="flex:1;">
                <label for="reg_last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="reg_last_name" name="last_name" required>
            </div>
        </div>

        <p>
            <label for="reg_email">Email Address <span class="required">*</span></label>
            <input type="email" id="reg_email" name="email" required>
        </p>

        <p>
            <label for="reg_phone">Phone Number <span class="required">*</span></label>
            <input type="text" id="reg_phone" name="phone_number" required>
        </p>

        <p>
            <label for="reg_company">Company Name <span class="required">*</span></label>
            <input type="text" id="reg_company" name="company_name" required>
        </p>

        <p>
            <label for="reg_job_title">Job Title <span class="required">*</span></label>
            <input type="text" id="reg_job_title" name="job_title" required>
        </p>

        <p>
            <input type="hidden" id="reg_bio" name="bio" value="">
        </p>

        <p>
            <label for="reg_linkedin">LinkedIn URL <span class="required">*</span></label>
            <input type="url" id="reg_linkedin" name="linkedin_url" required>
        </p>

        <p>
            <label for="reg_instagram">Instagram URL </label>
            <input type="url" id="reg_instagram" name="instagram_url">
        </p>
    </fieldset>

    <div id="mr-error-modal" class="mr-custom-modal" role="dialog" aria-labelledby="error-title">
        <div class="mr-modal-content">
            <h3 id="error-title">Wait! We need your photo! 📸</h3>
            <p>It helps people find you. Just a quick upload and you\'re all set. Let\'s finish your signup!</p>
            <button type="button" id="close-error-modal" class="button">Sure thing!</button>
        </div>
    </div>

    <div id="mr-crop-modal" class="mr-custom-modal" role="dialog" aria-labelledby="crop-title">
        <div class="mr-crop-container">
            <h3 id="crop-title">Adjust your photo</h3>
            <div class="mr-crop-wrapper"><img id="reg-image-to-crop" alt="Image to crop"></div>
            <button type="button" id="reg-save-crop" class="button">Save Photo</button>
            <button type="button" id="reg-cancel-crop" class="button" style="background:#eee;color:#333;margin-left:10px;">Cancel</button>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const input = document.getElementById("reg-image-input"), 
              link = document.getElementById("mr-reg-upload-link"),
              cropModal = document.getElementById("mr-crop-modal"),
              errorModal = document.getElementById("mr-error-modal"),
              img = document.getElementById("reg-image-to-crop"), 
              hidden = document.getElementById("reg-cropped-data"), 
              preview = document.getElementById("reg-avatar-preview"),
              form = document.querySelector("form.cart");

        let cropper;
        if(!link || !input) return;

        link.onclick = (e) => { e.preventDefault(); input.click(); };
        
        input.onchange = (e) => {
            const file = e.target.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = () => { 
                img.src = reader.result; 
                cropModal.style.display = "flex"; 
                if(cropper) cropper.destroy(); 
                cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1 }); 
            };
            reader.readAsDataURL(file);
        };

        document.getElementById("reg-save-crop").onclick = () => {
            const dataUrl = cropper.getCroppedCanvas({ width: 400, height: 400 }).toDataURL("image/png");
            preview.src = dataUrl; 
            hidden.value = dataUrl; 
            cropModal.style.display = "none";
            link.innerText = "Change profile picture";
        };

        document.getElementById("reg-cancel-crop").onclick = () => { cropModal.style.display = "none"; input.value = ""; };
        
        document.getElementById("close-error-modal").onclick = () => { errorModal.style.display = "none"; };

        if (form) {
            form.addEventListener("submit", function(e) {
                if (!hidden.value) {
                    e.preventDefault();
                    errorModal.style.display = "flex";
                }
            });
        }
    });
    </script>';
}
