<style>
    .auth-page{margin:0;min-height:100vh;padding:var(--pc-space-4);display:grid;place-items:center;background:var(--pc-color-background);color:var(--pc-color-text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
    .auth-card{width:min(100%,420px);padding:var(--pc-space-6);border:1px solid var(--pc-color-border);border-radius:var(--pc-radius-lg);background:var(--pc-color-surface);box-shadow:var(--pc-shadow-card)}
    .auth-brand{text-align:center;margin-bottom:var(--pc-space-4)}
    .auth-logo{display:block;width:60px;height:60px;object-fit:contain;margin:0 auto var(--pc-space-1)}
    .auth-wordmark{margin:0;font-size:26px;line-height:1.15;font-weight:800;letter-spacing:-.02em}.auth-wordmark span{color:var(--pc-color-primary)}.auth-wordmark strong{color:var(--pc-color-success)}
    .auth-brand p{margin:var(--pc-space-1) 0 0;color:var(--pc-color-text-muted);font-size:var(--pc-font-size-secondary);line-height:1.35}
    .auth-form{display:grid;gap:var(--pc-space-3)}
    .auth-options{display:flex;align-items:center;justify-content:space-between;gap:var(--pc-space-3);font-size:var(--pc-font-size-secondary)}
    .auth-options a{color:var(--pc-color-link);font-weight:600;text-decoration:none}.auth-options a:hover{text-decoration:underline}.auth-options a:focus-visible{outline:0;box-shadow:var(--pc-focus-ring);border-radius:var(--pc-radius-sm)}
    .auth-remember{display:flex;align-items:center;gap:var(--pc-space-2);cursor:pointer}.auth-remember input{width:16px;height:16px;accent-color:var(--pc-color-primary)}
    .auth-password-control .app-field__input{padding-right:52px}.auth-password-control>.auth-password-toggle{position:absolute!important;left:auto!important;right:4px!important;top:50%!important;translate:0 -50%;width:40px;height:40px;padding:0;border:0;border-radius:var(--pc-radius-md);display:grid;place-items:center;background:transparent;color:var(--pc-color-text-muted);cursor:pointer;pointer-events:auto}.auth-password-toggle .material-symbols-outlined{position:static!important;translate:none!important;font-size:var(--pc-size-icon);pointer-events:none}.auth-password-toggle:hover{background:var(--pc-color-primary-soft);color:var(--pc-color-primary)}.auth-password-toggle:focus-visible{outline:0;box-shadow:var(--pc-focus-ring)}
    .auth-alert{display:flex;align-items:flex-start;gap:var(--pc-space-2);margin-bottom:var(--pc-space-3);padding:var(--pc-space-2) var(--pc-space-3);border-radius:var(--pc-radius-md);font-size:var(--pc-font-size-secondary);line-height:1.4}.auth-alert .material-symbols-outlined{font-size:18px;flex:0 0 auto}.auth-alert--error{background:#FFF1F0;color:var(--pc-color-danger)}.auth-alert--success{background:#ECF8F0;color:var(--pc-color-success)}
    @media(max-width:600px){.auth-page{padding:var(--pc-space-4)}.auth-card{padding:var(--pc-space-6)}.auth-logo{width:56px;height:56px}.auth-options{align-items:flex-start;flex-direction:column;gap:var(--pc-space-2)}}
</style>
