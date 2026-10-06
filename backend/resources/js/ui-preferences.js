const themeKey = 'pc-theme';

window.pcTranslate = value => {
    const translations = window.PC_I18N?.texts;
    return window.PC_I18N?.locale === 'en' && translations?.[value]
        ? translations[value]
        : value;
};

// Niveau 4 — Clair / Sombre / Système. « Système » suit le réglage de l'appareil.
const systemDark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
const resolveTheme = preference => preference === 'system' ? (systemDark?.matches ? 'dark' : 'light') : (preference === 'dark' ? 'dark' : 'light');

function applyTheme(preference) {
    document.documentElement.dataset.theme = resolveTheme(preference);
    document.documentElement.dataset.themePreference = preference;
    document.querySelectorAll('[data-theme-option]').forEach(option => {
        const active = option.value === preference;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

function translateText(root) {
    const translations = window.PC_I18N?.texts;
    if (window.PC_I18N?.locale !== 'en' || !translations) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            if (!node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
            if (node.parentElement?.closest('script,style,noscript,textarea,pre,code,.material-symbols-outlined,[data-no-i18n]')) {
                return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
        const original = node.nodeValue;
        const translated = window.pcTranslate(original.trim());
        if (translated !== original.trim()) node.nodeValue = original.replace(original.trim(), translated);
    }

    root.querySelectorAll?.('[placeholder],[aria-label],[title]').forEach(element => {
        for (const attribute of ['placeholder', 'aria-label', 'title']) {
            const original = element.getAttribute(attribute);
            if (original) element.setAttribute(attribute, window.pcTranslate(original));
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // AM-161 — Indicateur de connexion de la barre supérieure.
    const connection = document.querySelector('[data-connection-status]');
    const renderConnection = () => {
        if (!connection) return;
        const online = navigator.onLine;
        connection.classList.toggle('is-offline', !online);
        const label = connection.querySelector('[data-connection-label]');
        // Niveau 3 : « Synchronisé il y a … » (Coordination) remplace « En ligne » quand la page le fournit.
        const onlineLabel = connection.dataset.onlineLabel || 'En ligne';
        if (label) label.textContent = online
            ? (connection.dataset.onlineLabel ? onlineLabel : (window.pcTranslate ? window.pcTranslate(onlineLabel) : onlineLabel))
            : (window.pcTranslate ? window.pcTranslate('Hors ligne') : 'Hors ligne');
    };
    window.addEventListener('online', renderConnection);
    window.addEventListener('offline', renderConnection);
    renderConnection();

    // Choix enregistré dans le profil (serveur), sinon dernier choix de ce navigateur.
    const themeSwitch = document.querySelector('[data-theme-switch]');
    let preference = 'light';
    if (window.PC_DARK_MODE !== false && (themeSwitch || window.PC_DARK_MODE)) {
        let stored = null;
        try { stored = localStorage.getItem(themeKey); } catch (_) {}
        preference = themeSwitch?.dataset.themePreference || window.PC_THEME || stored || 'light';
    }
    applyTheme(preference);
    systemDark?.addEventListener?.('change', () => { if (preference === 'system') applyTheme('system'); });

    themeSwitch?.addEventListener('click', event => {
        const option = event.target.closest('[data-theme-option]');
        if (!option) return;
        event.preventDefault();
        preference = option.value;
        applyTheme(preference);
        try { localStorage.setItem(themeKey, preference); } catch (_) {}
        const data = new FormData(themeSwitch);
        data.set('theme', preference);
        fetch(themeSwitch.action, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => {});
    });

    translateText(document.body);
    document.title = window.pcTranslate(document.title);
    new MutationObserver(records => {
        for (const record of records) {
            record.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) translateText(node);
                else if (node.nodeType === Node.TEXT_NODE && window.PC_I18N?.locale === 'en') {
                    const original = node.nodeValue.trim();
                    const translation = window.pcTranslate(original);
                    if (translation !== original) node.nodeValue = node.nodeValue.replace(original, translation);
                }
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
});
