document.addEventListener('input', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'email') return;
    input.setCustomValidity(input.validity.typeMismatch ? 'Enter a valid email address.' : '');
});