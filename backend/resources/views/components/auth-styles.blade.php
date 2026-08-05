<style>
:root{--orange:#FF7A00;--blue:#2563EB;--green:#16A34A;--red:#EF4444;--ink:#152033;--muted:#667085;--line:#E4E9F0}
body{margin:0!important;min-height:100vh!important;padding:32px 16px!important;display:grid!important;place-items:center!important;background:radial-gradient(circle at 15% 15%,rgba(37,99,235,.12),transparent 30%),radial-gradient(circle at 85% 80%,rgba(255,122,0,.14),transparent 28%),#F5F7FB!important;color:var(--ink)!important;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif!important}
body .card{width:min(440px,100%)!important;padding:38px!important;border:1px solid var(--line)!important;border-radius:24px!important;background:rgba(255,255,255,.97)!important;box-shadow:0 24px 65px rgba(23,42,78,.13)!important}
body .logo{width:104px!important;height:104px!important;border-radius:22px!important}
body .brand,body h1{color:var(--ink)!important;font-weight:900!important;letter-spacing:-.03em}
body .brand{font-size:30px!important}.sub,body .card>p{color:var(--muted)!important;line-height:1.5}
body label{display:block;margin:15px 0 6px;color:#344054;font-size:13px;font-weight:750}
body input{width:100%;min-height:48px;box-sizing:border-box;padding:12px 14px;border:1px solid #D5DCE7!important;border-radius:12px!important;background:#fff;font:inherit;color:var(--ink)}
body input:focus{outline:none;border-color:var(--blue)!important;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
body button:not(.toggle-password){min-height:48px;border:0;border-radius:12px!important;background:var(--orange)!important;color:#fff!important;font:750 14px Inter,system-ui,sans-serif;box-shadow:0 8px 18px rgba(255,122,0,.2);cursor:pointer}
body a{color:var(--blue)!important;text-decoration:none!important;font-weight:700}
body .message{padding:12px 14px!important;border-radius:12px!important;background:rgba(22,163,74,.09)!important;color:var(--green)!important}
body .message.error,body .error{background:rgba(239,68,68,.09)!important;color:#C52E2E!important;border-radius:12px!important}
body .toggle-password{border-radius:10px!important;color:var(--blue)!important}
@media(max-width:520px){body{padding:16px!important}body .card{padding:26px 22px!important}.links{align-items:flex-start!important;gap:12px;flex-direction:column}}
</style>
