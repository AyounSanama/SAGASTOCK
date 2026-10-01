const themeKey = 'pc-theme';

window.pcTranslate = value => {
    const translations = window.PC_I18N?.texts;
    return window.PC_I18N?.locale === 'en' && translations?.[value]
        ? translations[value]
        : value;
};

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';

    const toggle = document.querySelector('[data-theme-toggle]');
    if (toggle) {
        const icon = toggle.querySelector('[data-theme-icon]');
        const label = isDark
            ? (window.PC_I18N?.locale === 'en' ? 'Enable light mode' : 'Activer le mode clair')
            : (window.PC_I18N?.locale === 'en' ? 'Enable dark mode' : 'Activer le mode sombre');
        if (icon) icon.textContent = isDark ? 'light_mode' : 'dark_mode';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
    }
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
    let savedTheme = 'light';
    // Mode sombre masqué en V1 : thème clair imposé, même si un choix est mémorisé.
    if (window.PC_DARK_MODE) {
        try { savedTheme = localStorage.getItem(themeKey) || 'light'; } catch (_) {}
    }
    applyTheme(savedTheme);

    document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(themeKey, nextTheme); } catch (_) {}
        applyTheme(nextTheme);
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
