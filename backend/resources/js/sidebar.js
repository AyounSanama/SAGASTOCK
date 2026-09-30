
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body,
            toggle = document.querySelector('.sidebar-toggle');
        document.querySelectorAll('.permission-navigation a, .nav-group-toggle').forEach(item => {
            const label = item.querySelector('.nav-text')?.textContent.trim();
            if (label) { item.setAttribute('aria-label', label); item.title = label; }
        });
        if (!localStorage.getItem('pc-sidebar') && innerWidth > 760 && innerWidth <= 1050) body.classList.add('sidebar-collapsed');
        if (localStorage.getItem('pc-sidebar') === 'collapsed' && innerWidth > 760) body.classList.add(
            'sidebar-collapsed');
        const updateToggleState = () => toggle?.setAttribute('aria-expanded', String(
            innerWidth <= 760 ? body.classList.contains('mobile-menu-open') : !body.classList.contains('sidebar-collapsed')));
        updateToggleState();
        toggle?.addEventListener('click', () => {
            if (innerWidth <= 760) {
                body.classList.toggle('mobile-menu-open');
                updateToggleState();
                return
            }
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('pc-sidebar', body.classList.contains('sidebar-collapsed') ?
                'collapsed' : 'expanded');
            updateToggleState();
        });
        window.addEventListener('resize', updateToggleState);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && body.classList.contains('mobile-menu-open')) {
                body.classList.remove('mobile-menu-open'); updateToggleState(); toggle?.focus();
            }
        });
        document.addEventListener('click', event => {
            if (body.classList.contains('mobile-menu-open') && !event.target.closest('.app-sidebar, .sidebar-toggle')) {
                body.classList.remove('mobile-menu-open'); updateToggleState();
            }
        });
        document.querySelectorAll('[data-nav-group]').forEach(group => {
            const groupToggle = group.querySelector('.nav-group-toggle');
            groupToggle?.addEventListener('click', () => {
                const opening = !group.classList.contains('open');
                group.classList.toggle('open', opening);
                groupToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            });
        });
    });
