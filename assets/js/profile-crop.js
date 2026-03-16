document.addEventListener('DOMContentLoaded', function () {

    const input = document.getElementById('mr-avatar-input');
    const preview = document.getElementById('mr-avatar-preview');
    const modal = document.getElementById('mr-crop-modal');
    const cropImg = document.getElementById('mr-crop-image');
    const saveBtn = document.getElementById('mr-save-crop');
    const cancelBtn = document.getElementById('mr-cancel-crop');
    const changeBtn = document.getElementById('mr-change-avatar');

    if (!input) return;

    let cropper;

    changeBtn.onclick = () => input.click();

    input.onchange = () => {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = () => {
            cropImg.src = reader.result;
            modal.style.display = 'flex';
            cropper = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1
            });
        };
        reader.readAsDataURL(file);
    };

    cancelBtn.onclick = () => {
        cropper.destroy();
        modal.style.display = 'none';
    };

    saveBtn.onclick = () => {
        cropper.getCroppedCanvas({ width: 400, height: 400 })
            .toBlob(blob => {

                const fd = new FormData();
                fd.append('action', 'mr_upload_cropped_avatar');
                fd.append('nonce', mrCrop.nonce);
                fd.append('avatar', blob);

                fetch(mrCrop.ajax_url, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        preview.src = res.data.url;
                        modal.style.display = 'none';
                        cropper.destroy();
                    }
                });
            });
    };
});
