document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('main table').forEach(table => {
        const parent = table.parentElement;
        if (parent.matches('.table-wrap, .app-data-table__scroll, .dashboard-table-wrap, .pc-table-scroll')) {
            parent.tabIndex = 0;
            parent.setAttribute('role', 'region');
            parent.setAttribute('aria-label', table.getAttribute('aria-label') || 'Tableau — défilement horizontal');
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'pc-table-scroll'; wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || 'Tableau — défilement horizontal');
        table.before(wrapper); wrapper.append(table);
    });
});
