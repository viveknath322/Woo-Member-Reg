document.addEventListener('DOMContentLoaded', function () {

    const emailInput = document.querySelector('input[name="email"]');
    if (!emailInput) return;

    emailInput.addEventListener('blur', function () {

        const email = this.value;
        if (!email) return;

        const data = new FormData();
        data.append('action', 'mr_check_member_email');
        data.append('email', email);
        data.append('nonce', mr_ajax.nonce);

        fetch(mr_ajax.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data.status === 'exists') {
                alert('This email is already registered.');
                emailInput.value = '';
                emailInput.focus();
            }
        });
    });
});
