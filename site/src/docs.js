// Kopier-Knöpfe der Code-Blöcke im Handbuch. Ohne JavaScript bleibt der Text markierbar.
document.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-copy]') : null;
    if (!button) return;

    const code = button.closest('figure')?.querySelector('code');
    if (!code) return;

    try {
        await navigator.clipboard.writeText(code.textContent ?? '');
        button.textContent = 'Kopiert';
        button.setAttribute('data-done', '');
    } catch {
        button.textContent = 'Nicht möglich';
    }

    setTimeout(() => {
        button.textContent = 'Kopieren';
        button.removeAttribute('data-done');
    }, 1800);
});
