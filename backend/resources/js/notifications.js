document.addEventListener('DOMContentLoaded', () => {
    const center = document.querySelector('[data-notifications]');
    if (!center) return;
    const list = center.querySelector('[data-notification-list]');
    const status = center.querySelector('[data-notification-status]');
    const count = center.querySelector('[data-notification-count]');
    const more = center.querySelector('[data-notification-more]');
    const t = value => window.pcTranslate?.(value) ?? value;
    const dateLocale = window.PC_I18N?.locale === 'en' ? 'en-GB' : 'fr-FR';
    let nextPage = null;
    let busy = false;
    const badge = value => {
        count.textContent = String(value);
        count.hidden = value === 0;
        center.querySelector('summary').setAttribute('aria-label', `${t('Notifications')} : ${value}${t(' non lues')}`);
    };
    const request = async (path = '', method = 'GET') => {
        const response = await fetch(center.dataset.url + path, {
            method, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': center.dataset.token },
        });
        if (!response.ok) throw new Error(t('Impossible de charger les notifications. Réessayez.'));
        return response.json();
    };
    const element = (tag, text) => {
        const node = document.createElement(tag);
        node.textContent = text;
        return node;
    };
    const render = item => {
        const row = document.createElement('article');
        row.className = 'notification-item';
        row.dataset.unread = String(!item.read);
        row.append(element('strong', item.title), element('p', item.message));
        const date = element('time', new Date(item.created_at).toLocaleString(dateLocale));
        date.dateTime = item.created_at;
        row.append(date, element('small', t(item.read ? 'Lue' : 'Non lue')));
        if (item.action_path) {
            const url = new URL(item.action_path, location.origin);
            if (url.origin === location.origin) {
                const link = element('a', t('Voir le détail')); link.href = url.href; row.append(link);
            }
        }
        if (!item.read) {
            const button = element('button', t('Marquer comme lue'));
            button.type = 'button'; button.className = 'secondary';
            button.addEventListener('click', async () => {
                if (busy) return;
                busy = true;
                button.disabled = true;
                try {
                    badge((await request(`/${encodeURIComponent(item.id)}/read`, 'POST')).unread_count);
                    row.replaceWith(render({ ...item, read: true }));
                    status.textContent = t('Notification marquée comme lue.');
                } catch (error) { status.textContent = error.message; button.disabled = false; }
                finally { busy = false; }
            });
            row.append(button);
        }
        return row;
    };
    const load = async (append = false) => {
        if (busy) return;
        busy = true; more.disabled = true; list.setAttribute('aria-busy', 'true');
        status.textContent = t('Chargement…');
        try {
            const result = await request(`?page=${append ? nextPage : 1}`);
            if (!append) list.replaceChildren();
            result.data.forEach(item => list.append(render(item)));
            nextPage = result.next_page; more.hidden = !nextPage;
            badge(result.unread_count);
            status.textContent = list.children.length ? '' : t('Aucune notification pour le moment.');
        } catch (error) { status.textContent = error.message; }
        finally { busy = false; more.disabled = false; list.setAttribute('aria-busy', 'false'); }
    };
    center.addEventListener('toggle', () => { if (center.open) load(); });
    center.querySelector('[data-notification-refresh]').addEventListener('click', () => load());
    more.addEventListener('click', () => load(true));
    center.querySelector('[data-notification-read-all]').addEventListener('click', async event => {
        if (busy) return;
        busy = true;
        const button = event.currentTarget; button.disabled = true;
        try { badge((await request('/read-all', 'POST')).unread_count); busy = false; await load(); }
        catch (error) { status.textContent = error.message; }
        finally { busy = false; button.disabled = false; }
    });
    const menus = document.querySelectorAll('.notification-center, .profile-menu');
    document.addEventListener('click', event => menus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') menus.forEach(menu => { if (menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
});
