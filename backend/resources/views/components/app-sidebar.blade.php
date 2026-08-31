@php
    $actor = auth()->user();
    $navigationItems = $actor ? app(\App\Services\ApplicationNavigationService::class)->items($actor) : [];
    $materialIcons = config('pharmacare_ui.module_icons', []);
    $activeNavigationItem = collect($navigationItems)->first(fn ($item) => $item['route'] && (request()->routeIs($item['route']) || request()->routeIs($item['route'].'*')));
    $isSagoAdmin = $actor && app(\App\Services\GovernanceService::class)->roleCode($actor) === \App\Services\GovernanceService::SAGO_ADMIN;
    $sagoConfigurationOpen = request()->routeIs('configuration.*') || request()->routeIs('organizations.*');
    $sagoStandardsOpen = request()->routeIs('configuration.platform-standards.*');
    $standardsTab = request()->routeIs('configuration.platform-standards.history.*') ? 'history' : request('tab', 'assistance');
@endphp
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,400,0,0">
<link rel="stylesheet" href="/css/pharmacare-portal.css?v={{ filemtime(public_path('css/pharmacare-portal.css')) }}">
<style>
    :root {
        --pc-orange: var(--pc-color-primary);
        --pc-orange-dark: var(--pc-color-primary-dark);
        --pc-blue: var(--pc-color-text);
        --pc-green: var(--pc-color-success);
        --pc-red: var(--pc-color-danger);
        --pc-purple: var(--pc-color-purple);
        --pc-gray: var(--pc-color-text-muted);
        --pc-navy: var(--pc-color-text);
        --pc-navy-2: var(--pc-color-text);
        --pc-ink: var(--pc-color-text);
        --pc-muted: var(--pc-color-text-muted);
        --pc-border: var(--pc-color-border);
        --pc-surface: var(--pc-color-background);
        --pc-white: var(--pc-color-surface);
        --pc-radius: var(--pc-radius-lg);
        --pc-shadow: var(--pc-shadow-card);
        --pc-sidebar: var(--pc-layout-sidebar-open);
    }

    * {
        box-sizing: border-box
    }

    html {
        background: var(--pc-surface)
    }

    body:not(.portal-body) {
        margin: 0 !important;
        padding: var(--pc-layout-topbar) 0 0 var(--pc-sidebar) !important;
        background: var(--pc-surface) !important;
        color: var(--pc-ink) !important;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif !important;
        -webkit-font-smoothing: antialiased;
        transition: padding-left .22s ease
    }

    /* Le portail et les pages historiques partagent exactement le même repère. */
    body.portal-body {
        margin: 0 !important;
        padding: var(--pc-layout-topbar) 0 0 var(--pc-sidebar) !important;
        background: var(--pc-surface) !important;
        color: var(--pc-ink) !important;
        transition: padding-left .22s ease;
    }

    body.portal-body .portal-shell,
    body.portal-body .portal-workspace {
        min-height: calc(100vh - var(--pc-layout-topbar));
        margin-left: 0 !important;
    }

    body.portal-body .portal-content {
        width: 100% !important;
        max-width: var(--pc-layout-content-max) !important;
        margin: 0 auto !important;
        padding: 0 !important;
    }

    body>a.app-mobile-brand {
        display: none !important
    }

    body>header:not(.app-shell-topbar) {
        display: none !important
    }

    body a {
        text-decoration: none
    }

    body main {
        width: auto !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 20px 24px 36px !important
    }

    .portal-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 16px;
        padding: 0;
        color: var(--pc-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .portal-breadcrumb a {
        color: var(--pc-blue);
        font-weight: 700;
    }

    .portal-breadcrumb [aria-current="page"] {
        color: var(--pc-ink);
        font-weight: 700;
    }

    body h1,
    body h2,
    body h3 {
        color: var(--pc-ink);
        letter-spacing: -.02em
    }

    body h1 {
        font-size: clamp(1.75rem, 2.4vw, 2.35rem) !important;
        font-weight: 850 !important
    }

    body .muted,
    body .meta {
        color: var(--pc-muted) !important
    }

    body .card,
    body .org-card,
    body details.section,
    body .archive-list,
    body .empty,
    body .ref-box {
        background: #fff !important;
        border: 1px solid var(--pc-border) !important;
        border-radius: 16px !important;
        box-shadow: var(--pc-shadow) !important;
    }

    body .card,
    body .org-card {
        padding: 20px
    }

    body input:not([type=checkbox]):not([type=radio]),
    body select,
    body textarea,
    body .control {
        width: 100%;
        min-height: 46px;
        padding: 11px 13px;
        border: 1px solid #D5DCE7 !important;
        border-radius: 12px !important;
        background: #fff !important;
        color: var(--pc-ink);
        font: inherit;
        transition: border-color .16s, box-shadow .16s;
    }

    body textarea {
        min-height: 88px
    }

    body input:focus,
    body select:focus,
    body textarea:focus {
        outline: none !important;
        border-color: var(--pc-blue) !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12) !important
    }

    body label {
        color: #344054;
        font-size: 13px;
        font-weight: 750
    }

    body button,
    body .button,
    body .btn {
        min-height: 46px;
        border-radius: 12px !important;
        padding: 10px 17px !important;
        border: 1px solid transparent;
        font: 700 14px/1 Inter, system-ui, sans-serif;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: transform .15s, box-shadow .15s, background .15s, opacity .15s;
    }

    body button:not(:disabled):active,
    body .button:not(:disabled):active,
    body .btn:not(:disabled):active {
        transform: translateY(1px)
    }

    body button:disabled,
    body .button[aria-disabled="true"],
    body .btn[aria-disabled="true"] {
        opacity: .5;
        cursor: not-allowed;
        box-shadow: none !important
    }

    body button:focus-visible,
    body a:focus-visible {
        outline: 3px solid rgba(37, 99, 235, .23);
        outline-offset: 2px
    }

    body button:not(.secondary):not(.danger):not(.success):not(.form-sheet-close):not(.logout-button):not(.nav-group-toggle):not(.oa-configure):not([class*="btn-"]),
    body .button:not(.secondary):not(.danger):not(.success):not([class*="btn-"]),
    body .btn-primary {
        background: var(--pc-orange) !important;
        color: #fff !important;
        box-shadow: 0 7px 16px rgba(255, 122, 0, .2) !important;
    }

    body .secondary,
    body .btn-secondary {
        background: #fff !important;
        color: var(--pc-blue) !important;
        border: 2px solid var(--pc-blue) !important;
        box-shadow: none !important;
    }

    body .success,
    body .btn-success {
        background: var(--pc-green) !important;
        color: #fff !important
    }

    body .danger,
    body .btn-danger {
        background: #fff !important;
        color: var(--pc-red) !important;
        border: 2px solid var(--pc-red) !important
    }

    body .btn-sm,
    body .actions .button {
        min-height: 38px !important;
        padding: 8px 12px !important;
        font-size: 12px !important
    }

    body .actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap
    }

    body .actions form,
    body .row-actions form,
    body .sheet-actions form,
    body td form {
        margin: 0
    }

    body .actions form,
    body .row-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap
    }

    body main img:not(.logo):not([class*="avatar"]):not([class*="brand"]) {
        max-width: 100%;
        height: auto;
        object-fit: contain
    }

    body .item > form + form,
    body .item > .actions,
    body .card > .actions:last-child {
        margin-top: 10px
    }

    body .badge {
        display: inline-flex !important;
        align-items: center;
        gap: 5px;
        border: 0 !important;
        border-radius: 999px !important;
        padding: 6px 10px !important;
        background: rgba(107, 114, 128, .1) !important;
        color: var(--pc-gray) !important;
        font-size: 12px !important;
        font-weight: 800 !important
    }

    body .badge.active,
    body .badge.success {
        background: rgba(22, 163, 74, .1) !important;
        color: var(--pc-green) !important
    }

    body .notice,
    body .message {
        border-radius: 12px !important;
        padding: 13px 15px !important;
        background: rgba(22, 163, 74, .1) !important;
        color: var(--pc-green) !important;
        border: 1px solid rgba(22, 163, 74, .18)
    }

    body .notice.error,
    body .message.error {
        background: rgba(239, 68, 68, .09) !important;
        color: #C52E2E !important;
        border-color: rgba(239, 68, 68, .18)
    }

    body .table-wrap {
        overflow: auto;
        border: 1px solid var(--pc-border);
        border-radius: 14px;
        background: #fff
    }

    body table {
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0 !important
    }

    body th {
        padding: 13px 14px !important;
        background: #F0F4FA !important;
        color: #536079 !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        letter-spacing: .035em;
        border-bottom: 1px solid var(--pc-border) !important
    }

    body td {
        padding: 14px !important;
        border-bottom: 1px solid #EDF0F4 !important;
        color: var(--pc-ink);
        font-size: 13px !important;
        vertical-align: middle
    }

    body tbody tr:hover {
        background: #FAFBFD
    }

    body tbody tr:last-child td {
        border-bottom: 0 !important
    }

    body details.section {
        padding: 20px !important
    }

    body details.section>summary {
        color: var(--pc-ink) !important;
        font-size: 18px !important;
        font-weight: 850 !important
    }

    body input[type=checkbox],
    body input[type=radio] {
        accent-color: var(--pc-orange);
        width: 18px;
        height: 18px
    }

    body .metrics {
        grid-template-columns: repeat(4, minmax(180px, 1fr)) !important;
        gap: 16px !important
    }

    body .metrics .metric {
        min-height: 158px !important;
        padding: 20px !important;
        border: 0 !important;
        color: #fff !important;
        border-radius: 18px !important;
        box-shadow: 0 14px 28px rgba(21, 32, 51, .13) !important;
        position: relative;
        overflow: hidden
    }

    body .metrics .metric:after {
        content: "";
        position: absolute;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .09);
        right: -35px;
        bottom: -45px
    }

    body .metrics .metric:nth-child(4n+1) {
        background: linear-gradient(135deg, #FF9C2A, var(--pc-orange)) !important
    }

    body .metrics .metric:nth-child(4n+2) {
        background: linear-gradient(135deg, #4A84F5, var(--pc-blue)) !important
    }

    body .metrics .metric:nth-child(4n+3) {
        background: linear-gradient(135deg, #38BE6C, var(--pc-green)) !important
    }

    body .metrics .metric:nth-child(4n+4) {
        background: linear-gradient(135deg, #9867F3, var(--pc-purple)) !important
    }

    body .metrics .metric:nth-child(n+5) {
        background: #fff !important;
        color: var(--pc-ink) !important;
        border: 1px solid var(--pc-border) !important
    }

    body .metrics .metric .icon {
        width: 45px !important;
        height: 45px !important;
        border-radius: 50% !important;
        background: rgba(255, 255, 255, .2) !important;
        color: inherit !important
    }

    body .metrics .metric strong {
        color: inherit !important;
        font-size: 30px !important;
        margin-top: 16px !important
    }

    body .metrics .metric small {
        color: inherit !important;
        opacity: .86
    }

    body .shortcut {
        border: 1px solid var(--pc-border) !important;
        border-radius: 14px !important;
        padding: 14px !important;
        background: #fff !important
    }

    body .shortcut:hover {
        border-color: var(--pc-blue) !important;
        transform: translateY(-1px)
    }

    body .shortcut span {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: rgba(37, 99, 235, .09);
        color: var(--pc-blue) !important
    }

    body .shortcut:nth-child(3n+1) span {
        background: rgba(255, 122, 0, .1);
        color: var(--pc-orange) !important
    }

    body .shortcut:nth-child(3n+2) span {
        background: rgba(22, 163, 74, .1);
        color: var(--pc-green) !important
    }

    body .shortcut:nth-child(3n+3) span {
        background: #FFF3E8;
        color: var(--pc-orange) !important
    }

    body .dashboard-metric,
    body .dashboard-metric.blue,
    body .dashboard-metric.cyan,
    body .dashboard-metric.orange,
    body .dashboard-metric.red {
        background: #fff !important;
        color: var(--pc-ink) !important;
        border: 1px solid var(--pc-border) !important;
        box-shadow: var(--pc-shadow) !important
    }

    body .dashboard-metric:after {
        background: #FFF3E8 !important;
        border-color: #FFF3E8 !important
    }

    body .dashboard-metric .metric-icon {
        background: #FFF3E8 !important;
        color: var(--pc-orange) !important;
        border-color: #FFE1C2 !important
    }

    body .dashboard-metric .metric-label,
    body .dashboard-metric .metric-link {
        color: var(--pc-muted) !important
    }

    body .dashboard-shortcut-icon,
    body .dashboard-shortcut-icon.orange,
    body .dashboard-shortcut-icon.blue,
    body .dashboard-shortcut-icon.green,
    body .dashboard-shortcut-icon.purple {
        background: #FFF3E8 !important;
        color: var(--pc-orange) !important
    }

    body .control-card-icon.blue,
    body .control-card-icon.purple,
    body .control-card-icon.cyan {
        background: #FFF3E8 !important;
        color: var(--pc-orange) !important
    }

    .app-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: var(--pc-sidebar);
        z-index: 1100;
        background: #fff;
        color: var(--pc-ink);
        border-right: 1px solid var(--pc-border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: width .22s ease;
        box-shadow: none
    }

    .app-brand {
        height: var(--pc-layout-topbar);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        color: var(--pc-ink);
        border-bottom: 1px solid var(--pc-border)
    }

    .app-brand img {
        width: 48px;
        height: 48px;
        object-fit: contain;
        background: #fff;
        border-radius: 14px;
        padding: 2px;
        flex: 0 0 auto
    }

    .app-brand-copy {
        min-width: 0
    }

    .app-brand strong {
        display: block;
        font-size: 19px;
        font-weight: 850
    }

    .brand-pharma {
        color: var(--pc-orange)
    }

    .brand-care {
        color: var(--pc-green)
    }

    .app-brand small {
        display: block;
        color: var(--pc-muted);
        font-size: 9px;
        white-space: nowrap;
        margin-top: 2px
    }

    .app-sidebar-scroll {
        flex: 1 1 auto;
        min-width: 0;
        padding: 14px 12px 18px;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        scrollbar-width: none
    }

    .app-sidebar-scroll::-webkit-scrollbar {
        width: 0;
        height: 0
    }

    .app-sidebar-scroll>.permission-navigation~* {
        display: none !important
    }

    .app-nav-label {
        padding: 12px 12px 7px;
        color: #89A6CB;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        white-space: nowrap
    }

    .app-sidebar nav {
        display: grid;
        gap: 4px;
        min-width: 0
    }

    .app-sidebar nav a {
        position: relative;
        min-height: 45px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 9px;
        color: var(--pc-ink);
        font-size: 13px;
        font-weight: 680;
        white-space: nowrap;
        transition: background .16s, color .16s
    }

    .app-sidebar nav a:hover {
        background: #F4F6F9;
        color: var(--pc-ink)
    }

    .app-sidebar nav a.active {
        background: #FFF3E8;
        color: var(--pc-orange);
        box-shadow: none
    }

    .app-sidebar nav a.active:before {
        content: "";
        position: absolute;
        left: -12px;
        inset-block: 0;
        width: 3px;
        background: var(--pc-orange)
    }

    .app-sidebar nav a[aria-disabled="true"],
    .nav-group-toggle:disabled {
        opacity: .46;
        cursor: not-allowed;
        pointer-events: none
    }

    .nav-icon {
        width: 23px;
        display: grid;
        place-items: center;
        font-size: 18px;
        flex: 0 0 auto
    }

    .nav-sub {
        margin-left: 35px !important;
        min-height: 38px !important;
        padding: 8px 10px !important;
        font-size: 12px !important;
        color: #BFD0E8 !important
    }

    .nav-group {
        display: grid;
        gap: 4px
    }

    .nav-group-toggle {
        width: 100%;
        min-height: 45px !important;
        padding: 10px 12px !important;
        border: 0 !important;
        border-radius: 11px !important;
        background: transparent !important;
        color: var(--pc-ink) !important;
        box-shadow: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 12px !important;
        text-align: left;
        font-size: 13px !important;
        font-weight: 680 !important
    }

    .nav-group-toggle:hover {
        background: #F4F6F9 !important;
        color: var(--pc-ink) !important
    }

    .nav-group.open>.nav-group-toggle,
    .nav-group-toggle.active {
        background: #FFF3E8 !important;
        color: var(--pc-orange) !important
    }

    .nav-group-chevron {
        margin-left: auto;
        font-size: 15px;
        transition: transform .2s ease
    }

    .nav-group.open>.nav-group-toggle>.nav-group-chevron {
        transform: rotate(180deg)
    }

    .nav-group-items {
        display: none;
        margin: 2px 0 5px 12px;
        padding-left: 8px;
        border-left: 1px solid #E8EDF3
    }

    .nav-group.open>.nav-group-items {
        display: grid;
        gap: 3px
    }

    .nav-group-items a {
        min-height: 38px !important;
        padding: 8px 10px !important;
        font-size: 12px !important;
        color: var(--pc-ink) !important
    }

    .nav-group-items a.active {
        background: #FFF8F1 !important;
        color: var(--pc-orange) !important
    }

    .nav-group-items .nav-group {
        min-width: 0;
        margin-left: -8px
    }

    .nav-group-items .nav-group>.nav-group-toggle {
        min-height: 39px !important;
        padding: 8px 10px !important;
        font-size: 12px !important
    }

    .nav-group-items .nav-group .nav-group-items {
        margin-left: 12px;
        padding-left: 8px
    }

    .nav-group-items .nav-group .nav-group-items a {
        gap: 8px;
        white-space: normal;
        line-height: 1.25
    }

    .sidebar-collapsed .nav-group-chevron {
        display: none
    }

    .sidebar-collapsed .nav-group-toggle {
        justify-content: center !important
    }

    .sidebar-collapsed .nav-group-items {
        display: none !important;
        margin-left: 0;
        padding-left: 0;
        border-left: 0
    }

    @media(max-width:760px) {
        .mobile-menu-open .nav-group-chevron {
            display: block
        }

        .mobile-menu-open .nav-group-toggle {
            justify-content: flex-start !important
        }

        .mobile-menu-open .nav-group-items {
            margin-left: 12px;
            padding-left: 8px;
            border-left: 1px solid #E8EDF3
        }
    }

    .app-account {
        margin-top: auto;
        padding: 14px 16px 18px;
        border-top: 1px solid var(--pc-border)
    }

    .app-account strong,
    .app-account small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    .app-account small {
        color: var(--pc-muted);
        font-size: 11px;
        margin-top: 3px
    }

    .app-account .logout-button {
        width: 100%;
        margin-top: 11px;
        min-height: 40px !important;
        background: #fff !important;
        border: 1px solid var(--pc-red) !important;
        color: var(--pc-red) !important;
        box-shadow: none !important
    }

    .app-account-profile {
        min-height: 40px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 11px;
        border: 1px solid var(--pc-border);
        border-radius: 11px;
        color: var(--pc-ink);
        font-size: 13px;
        font-weight: 750
    }

    .app-account-profile:hover,
    .app-account-profile.active {
        background: #F7F9FC
    }

    .app-account-profile .material-symbols-outlined,
    .logout-button .material-symbols-outlined {
        font-size: 20px
    }

    .app-shell-topbar {
        position: fixed;
        z-index: 1050;
        top: 0;
        left: var(--pc-sidebar);
        right: 0;
        height: var(--pc-layout-topbar);
        padding: 0 24px;
        background: rgba(255, 255, 255, .96);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--pc-border);
        display: flex;
        align-items: center;
        gap: 18px;
        transition: left .22s ease
    }

    .sidebar-toggle {
        width: var(--pc-size-icon-button);
        height: var(--pc-size-icon-button) !important;
        min-height: var(--pc-size-icon-button) !important;
        padding: 0 !important;
        background: #EEF2F7 !important;
        color: var(--pc-ink) !important;
        border: 0 !important;
        box-shadow: none !important;
        font-size: 20px !important
    }

    .topbar-brand-mark {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 10px;
    }

    .topbar-brand-mark img {
        display: block;
        width: 38px;
        height: 38px;
        object-fit: contain;
    }

    .topbar-back {
        min-height: 40px !important;
        padding: 8px 12px !important;
        border: 1px solid var(--pc-border) !important;
        background: #fff !important;
        color: var(--pc-ink) !important;
        box-shadow: none !important;
        white-space: nowrap
    }

    .topbar-back .material-symbols-outlined {
        font-size: 19px
    }

    .topbar-context {
        min-width: 0;
        color: var(--pc-ink);
        font-size: 14px;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    .topbar-search {
        position: relative;
        flex: 1;
        max-width: 560px
    }

    .topbar-search input {
        padding-left: 42px !important;
        background: #F0F4F8 !important;
        border-color: transparent !important
    }

    .topbar-search span {
        position: absolute;
        left: 15px;
        top: 13px;
        color: var(--pc-gray);
        z-index: 1
    }

    .topbar-spacer {
        flex: 1
    }

    .topbar-icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: #F0F4F8;
        color: var(--pc-ink);
        position: relative;
        font-size: 19px
    }

    .topbar-icon b {
        position: absolute;
        right: -4px;
        top: -5px;
        width: 20px;
        height: 20px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--pc-red);
        color: #fff;
        font-size: 10px
    }

    .topbar-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--pc-ink);
        min-width: 0
    }

    .profile-avatar {
        width: 43px;
        height: 43px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: rgba(255, 122, 0, .12);
        color: var(--pc-orange);
        font-weight: 900;
        border: 1px solid rgba(255, 122, 0, .25)
    }

    .topbar-profile strong,
    .topbar-profile small {
        display: block;
        white-space: nowrap
    }

    .topbar-profile small {
        color: var(--pc-muted);
        font-size: 10px;
        margin-top: 2px
    }

    body.sidebar-collapsed {
        --pc-sidebar: var(--pc-layout-sidebar-collapsed)
    }

    .sidebar-collapsed .app-brand {
        padding-inline: 17px
    }

    .sidebar-collapsed .app-brand-copy,
    .sidebar-collapsed .app-nav-label,
    .sidebar-collapsed .nav-text,
    .sidebar-collapsed .app-account strong,
    .sidebar-collapsed .app-account small {
        display: none
    }

    .sidebar-collapsed .app-sidebar nav a {
        justify-content: center
    }

    .sidebar-collapsed .nav-sub {
        margin-left: 0 !important
    }

    .sidebar-collapsed .logout-button {
        padding-inline: 0 !important
    }

    .sidebar-collapsed .app-account {
        padding-inline: 10px
    }

    .sidebar-collapsed .app-account-profile {
        justify-content: center;
        padding-inline: 0
    }

    .form-sheet {
        border: 0;
        padding: 0;
        background: transparent;
        max-width: none;
        width: 100%;
        height: 100%;
        margin: 0;
        overflow: hidden
    }

    .form-sheet::backdrop {
        background: rgba(8, 21, 43, .65);
        backdrop-filter: blur(3px)
    }

    .form-sheet-panel {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: min(var(--sheet-width, var(--pc-layout-form-sheet-max)), var(--pc-layout-form-sheet-max), calc(100% - 24px));
        background: #fff;
        box-shadow: -18px 0 48px rgba(9, 29, 58, .2);
        display: flex;
        flex-direction: column;
        animation: sheet-in .24s ease-out;
        border-radius: var(--pc-radius-lg) 0 0 var(--pc-radius-lg)
    }

    .form-sheet-handle {
        display: none
    }

    .form-sheet-header {
        display: flex !important;
        position: static !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        padding: var(--pc-space-6) !important;
        border-bottom: 1px solid var(--pc-border) !important;
        background: #fff !important
    }

    .form-sheet-header h2 {
        margin: 0 !important;
        color: var(--pc-ink) !important
    }

    .form-sheet-header p {
        margin: 6px 0 0 !important;
        color: var(--pc-muted) !important
    }

    .form-sheet-close {
        width: var(--pc-size-icon-button);
        height: var(--pc-size-icon-button) !important;
        min-height: var(--pc-size-icon-button) !important;
        padding: 0 !important;
        border: 1px solid var(--pc-border) !important;
        border-radius: var(--pc-radius-md) !important;
        background: #F2F4F7 !important;
        color: var(--pc-ink) !important;
        font-size: var(--pc-size-icon) !important;
        box-shadow: none !important
    }

    .form-sheet-body {
        padding: var(--pc-space-6) var(--pc-space-6) var(--pc-space-8);
        overflow-y: auto
    }

    .form-sheet-actions,
    .sheet-actions {
        position: sticky;
        bottom: -32px;
        background: #fff;
        border-top: 1px solid var(--pc-border) !important;
        margin: var(--pc-space-6) calc(-1 * var(--pc-space-6)) calc(-1 * var(--pc-space-8)) !important;
        padding: var(--pc-space-4) var(--pc-space-6) !important;
        display: flex !important;
        justify-content: flex-end !important;
        gap: 10px !important
    }

    .form-sheet-error {
        padding: 13px 15px;
        border-radius: 12px;
        background: rgba(239, 68, 68, .09);
        color: #C52E2E;
        margin-bottom: 16px
    }

    @keyframes sheet-in {
        from {
            transform: translateX(100%);
            opacity: .4
        }

        to {
            transform: none;
            opacity: 1
        }
    }

    @media(max-width:1250px) {
        body .metrics {
            grid-template-columns: repeat(2, minmax(180px, 1fr)) !important
        }
    }

    @media(max-width:1050px) {
        :root {
            --pc-sidebar: var(--pc-layout-sidebar-collapsed)
        }

        .app-brand {
            padding-inline: 17px
        }

        .app-brand-copy,
        .app-nav-label,
        .nav-text,
        .app-account strong,
        .app-account small {
            display: none
        }

        .app-sidebar nav a {
            justify-content: center
        }

        .nav-sub {
            margin-left: 0 !important
        }

        .logout-button {
            font-size: 0
        }

        .logout-button:after {
            content: "↪";
            font-size: 18px
        }
    }

    @media(max-width:760px) {
        body,
        body.portal-body {
            padding: var(--pc-layout-topbar) 0 0 !important
        }

        body.portal-body .portal-content {
            padding: 16px !important
        }

        .app-sidebar {
            transform: translateX(-100%);
            width: var(--pc-layout-sidebar-open)
        }

        .app-shell-topbar {
            left: 0;
            height: var(--pc-layout-topbar);
            padding: 0 14px
        }

        .topbar-search,
        .topbar-context,
        .topbar-profile-copy {
            display: none
        }

        .topbar-spacer {
            display: block
        }

        .mobile-menu-open .app-sidebar {
            transform: translateX(0);
            width: var(--pc-layout-sidebar-open)
        }

        .mobile-menu-open .app-brand-copy,
        .mobile-menu-open .app-nav-label,
        .mobile-menu-open .nav-text,
        .mobile-menu-open .app-account strong,
        .mobile-menu-open .app-account small {
            display: block
        }

        .mobile-menu-open .app-sidebar nav a {
            justify-content: flex-start
        }

        .mobile-menu-open:after {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(8, 21, 43, .55);
            z-index: 1090
        }

        body main {
            padding: 22px 14px 50px !important
        }

        body .metrics {
            grid-template-columns: 1fr 1fr !important;
            gap: 10px !important
        }

        body .metrics .metric {
            min-height: 140px !important;
            padding: 15px !important
        }

        .form-sheet-panel {
            top: auto;
            bottom: 0;
            right: 6px;
            left: 6px;
            width: auto;
            height: min(93%, 900px);
            border-radius: var(--pc-radius-lg) var(--pc-radius-lg) 0 0;
            animation: sheet-up .24s ease-out
        }

        .form-sheet-handle {
            display: block;
            width: 46px;
            height: 5px;
            background: #CAD2DD;
            border-radius: 99px;
            margin: 9px auto 0
        }

        .form-sheet-header {
            padding: 17px 19px !important
        }

        .form-sheet-body {
            padding: 19px 19px 28px
        }

        .form-sheet-actions,
        .sheet-actions {
            margin: 24px -19px -28px !important;
            padding: 15px 19px !important;
            flex-direction: column-reverse
        }

        .form-sheet-actions>*,
        .sheet-actions>* {
            width: 100%
        }

        @keyframes sheet-up {
            from {
                transform: translateY(100%);
                opacity: .4
            }

            to {
                transform: none;
                opacity: 1
            }
        }
    }
</style>

<aside id="pharmacare-sidebar" class="app-sidebar" aria-label="Navigation principale">
    <a class="app-brand" href="{{ $isSagoAdmin ? route('sago.dashboard') : route('dashboard') }}">
        <img src="/images/pharmacare-logo.png"
             alt="Logo PharmaCare" width="48" height="48">
        <span class="app-brand-copy">
            <strong><span class="brand-pharma">Pharma</span><span class="brand-care">Care</span></strong>
            <small>Putting Patients at the Heart of Every Supply.</small>
        </span>
    </a>

    <div class="app-sidebar-scroll">
        <nav class="permission-navigation">
            @if($isSagoAdmin)
                <a class="{{ request()->routeIs('sago.dashboard') ? 'active' : '' }}" href="{{ route('sago.dashboard') }}">
                    <span class="nav-icon material-symbols-outlined">dashboard</span><span class="nav-text">Tableau de bord</span>
                </a>
                <div class="nav-group {{ $sagoConfigurationOpen ? 'open' : '' }}" data-nav-group>
                    <button class="nav-group-toggle {{ $sagoConfigurationOpen ? 'active' : '' }}" type="button" aria-expanded="{{ $sagoConfigurationOpen ? 'true' : 'false' }}" aria-controls="sago-configuration-menu">
                        <span class="nav-icon material-symbols-outlined">settings</span><span class="nav-text">Configuration</span><span class="nav-group-chevron material-symbols-outlined">expand_more</span>
                    </button>
                    <div id="sago-configuration-menu" class="nav-group-items">
                        @if($actor->hasPermission('organizations.view'))
                            <a class="{{ request()->routeIs('configuration.organization') ? 'active' : '' }}" href="{{ route('configuration.organization') }}"><span class="nav-icon material-symbols-outlined">domain</span><span class="nav-text">Organisations</span></a>
                        @endif
                        @if($actor->hasPermission('platform_standards.view'))
                        <div class="nav-group {{ $sagoStandardsOpen ? 'open' : '' }}" data-nav-group>
                            <button class="nav-group-toggle {{ $sagoStandardsOpen ? 'active' : '' }}" type="button" aria-expanded="{{ $sagoStandardsOpen ? 'true' : 'false' }}" aria-controls="sago-standards-menu">
                                <span class="nav-icon material-symbols-outlined">workspace_premium</span><span class="nav-text">Standards &amp; Référentiels</span><span class="nav-group-chevron material-symbols-outlined">expand_more</span>
                            </button>
                            <div id="sago-standards-menu" class="nav-group-items">
                                <a class="{{ $sagoStandardsOpen && $standardsTab === 'assistance' ? 'active' : '' }}" href="{{ route('configuration.platform-standards.index', ['tab' => 'assistance']) }}"><span class="nav-icon material-symbols-outlined">support_agent</span><span class="nav-text">Assistance aux organisations</span></a>
                                <a class="{{ $sagoStandardsOpen && $standardsTab === 'history' ? 'active' : '' }}" href="{{ route('configuration.platform-standards.index', ['tab' => 'history']) }}"><span class="nav-icon material-symbols-outlined">history</span><span class="nav-text">Historique</span></a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @else
                @foreach ($navigationItems as $item)
                    @continue(!$item['url'] || $item['key'] === 'profile')
                    @php($active = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '*') || ($item['key'] === 'projects' && request()->routeIs('modules.funding')))
                    <a class="{{ $active ? 'active' : '' }}" href="{{ $item['url'] }}">
                        <span class="nav-icon material-symbols-outlined">{{ $materialIcons[$item['icon']] ?? 'circle' }}</span>
                        <span class="nav-text">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endif
        </nav>
    </div>

    <div class="app-account">
        <a class="app-account-profile {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}"><span class="material-symbols-outlined">person</span><span class="nav-text">Mon profil</span></a>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button" aria-label="Déconnexion"><span class="material-symbols-outlined">logout</span><span class="nav-text">Déconnexion</span></button>
        </form>
    </div>
</aside>

@include('components.navigation.topbar', ['actor' => $actor])
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body,
            toggle = document.querySelector('.sidebar-toggle');
        if (localStorage.getItem('pc-sidebar') === 'collapsed' && innerWidth > 760) body.classList.add(
            'sidebar-collapsed');
        const updateToggleState = () => toggle?.setAttribute('aria-expanded',
            body.classList.contains('sidebar-collapsed') ? 'false' : 'true');
        updateToggleState();
        toggle?.addEventListener('click', () => {
            if (innerWidth <= 760) {
                body.classList.toggle('mobile-menu-open');
                return
            }
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('pc-sidebar', body.classList.contains('sidebar-collapsed') ?
                'collapsed' : 'expanded');
            updateToggleState();
        });
        document.querySelectorAll('[data-nav-group]').forEach(group => {
            const groupToggle = group.querySelector('.nav-group-toggle');
            groupToggle?.addEventListener('click', () => {
                const opening = !group.classList.contains('open');
                group.classList.toggle('open', opening);
                groupToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            });
        });
        document.querySelectorAll('[data-sheet-open]').forEach(button => button.addEventListener('click',
            () => {
                const sheet = document.getElementById(button.dataset.sheetOpen);
                if (sheet && !sheet.open) {
                    sheet.dataset.dirty = 'false';
                    sheet.showModal();
                }
            }));
        document.querySelectorAll('.form-sheet').forEach(sheet => {
            sheet.querySelectorAll('form').forEach(form => form.addEventListener('input', () => sheet
                .dataset.dirty = 'true'));
            sheet.querySelectorAll('[data-sheet-close]').forEach(button => button.addEventListener(
                'click', () => closeFormSheet(sheet)));
            sheet.addEventListener('cancel', event => {
                event.preventDefault();
                closeFormSheet(sheet)
            });
            sheet.addEventListener('click', event => {
                if (event.target === sheet) closeFormSheet(sheet)
            });
        });
    });

    function closeFormSheet(sheet) {
        if (sheet.dataset.dirty === 'true' && !confirm(
                'Vous avez des modifications non enregistrées. Voulez-vous vraiment quitter ?')) return;
        sheet.close();
    }
</script>
