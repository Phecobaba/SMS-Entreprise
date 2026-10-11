import './bootstrap';

const oakSheets = () => {
    document.querySelectorAll('.oak-scrim[data-open]').forEach((sheet) => sheet.removeAttribute('data-open'));
};

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-sheet]');
    if (opener) {
        event.preventDefault();
        const sheet = document.getElementById(opener.dataset.openSheet);
        if (!sheet) return;
        sheet._oakReturnFocus = document.activeElement;
        sheet.setAttribute('data-open', '');
        sheet.querySelector('button, a, input, select, textarea')?.focus();
        return;
    }

    const closer = event.target.closest('[data-close-sheet]');
    const scrim = closer?.closest('.oak-scrim') ?? (event.target.classList?.contains('oak-scrim') ? event.target : null);
    if (scrim) {
        scrim.removeAttribute('data-open');
        scrim._oakReturnFocus?.focus?.();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') oakSheets();
});

const startTicker = (ticker) => {
    if (ticker.dataset.ready) return;
    ticker.dataset.ready = 'true';
    const items = [...ticker.querySelectorAll('.oak-ticker-track > a')];
    const phoneItem = ticker.querySelector('[data-ticker-static]');
    const pause = ticker.querySelector('[data-ticker-pause]');
    let index = 0;
    let timer = null;

    const show = () => {
        const item = items[index];
        if (!item || !phoneItem) return;
        phoneItem.replaceChildren(item.children[1].cloneNode(true), document.createTextNode(` · ${item.children[2].textContent}`));
        phoneItem.href = item.href;
        phoneItem.removeAttribute('data-fade');
        void phoneItem.offsetWidth;
        phoneItem.setAttribute('data-fade', '');
    };
    const stop = () => { window.clearInterval(timer); timer = null; };
    const start = () => {
        if (timer || !items.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        timer = window.setInterval(() => { index = (index + 1) % items.length; show(); }, 5000);
    };

    pause?.addEventListener('click', () => {
        const paused = pause.getAttribute('aria-pressed') !== 'true';
        pause.setAttribute('aria-pressed', String(paused));
        pause.setAttribute('aria-label', paused ? 'Resume moving notifications' : 'Pause moving notifications');
        ticker.toggleAttribute('data-paused', paused);
        paused ? stop() : start();
    });
    ticker.addEventListener('focusin', stop);
    ticker.addEventListener('focusout', () => { if (pause?.getAttribute('aria-pressed') !== 'true') start(); });
    start();
};

const bootOakUi = () => document.querySelectorAll('[data-ticker]').forEach(startTicker);
document.addEventListener('DOMContentLoaded', bootOakUi);
document.addEventListener('livewire:navigated', bootOakUi);
