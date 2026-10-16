(() => {
    const form = document.querySelector('[data-tonight-form]');
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    const loading = form.querySelector('[data-tonight-loading]');
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Recherche en cours…';
        loading.hidden = false;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = 'Trouver une idée';
        loading.hidden = true;
        form.removeAttribute('aria-busy');
    });
})();
