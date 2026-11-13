document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm') || document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const isLogin = form.id === 'loginForm';
        const error = document.getElementById('errorMsg');
        const button = form.querySelector('button[type="submit"]');
        error.hidden = false;
        error.textContent = '';
        button.disabled = true;

        try {
            const response = await fetch(isLogin
                ? 'backend/authentication/login.php'
                : 'backend/authentication/register.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'The request could not be completed.');
            }

            if (isLogin) {
                sessionStorage.setItem('sasOjtCsrfToken', result.csrfToken);
                window.location.assign(result.redirect);
                return;
            }
            window.location.assign('index.html?registered=1');
        } catch (requestError) {
            error.textContent = requestError.message || 'The service is unavailable. Please try again.';
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });

    if (new URLSearchParams(window.location.search).has('registered')) {
        const notice = document.getElementById('formNotice');
        if (notice) {
            notice.textContent = 'Registration complete. You can now sign in.';
            notice.hidden = false;
        }
    }
});