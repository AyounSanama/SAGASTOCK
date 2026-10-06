{{-- Niveau 5 — Styles communs aux écrans Admin Projet (mise en page des maquettes, couleurs de la charte). --}}
@once
@push('styles')<style>
.pa{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.pa-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.pa-head h1{margin:0;font-size:24px}.pa-head p{margin:4px 0 0;color:var(--pc-color-text-muted)}
.pa-eyebrow{margin:0 0 4px;color:var(--pc-color-text-muted);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.pa-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:8px 14px;border-radius:10px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);color:var(--pc-color-text);font:inherit;font-weight:700;text-decoration:none;cursor:pointer;white-space:nowrap}
.pa-btn.primary{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}.pa-btn.sm{min-height:32px;padding:5px 11px;font-size:13px;font-weight:600}.pa-btn .material-symbols-outlined{font-size:18px}
.pa-link{color:var(--pc-color-primary-strong);font-weight:600;text-decoration:underline;text-underline-offset:3px}
.pa-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}.pa-kpi{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:16px 18px}
.pa-kpi small{color:var(--pc-color-text-muted);font-size:13px}.pa-kpi strong{display:block;font-size:28px;margin:6px 0 4px}.pa-kpi span{color:var(--pc-color-text-muted);font-size:13px}.pa-kpi strong.danger{color:var(--pc-status-danger-text)}
.pa-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px}.pa-card>header{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--pc-color-border)}.pa-card>header h2{margin:0;font-size:16px}
.pa-pad{padding:18px 20px}.pa-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:18px;align-items:start}.pa-side{display:grid;gap:18px}
.pa-table{width:100%;border-collapse:collapse}.pa-table th{font-size:12px;text-align:left;color:var(--pc-color-text-muted);padding:12px 16px;border-bottom:1px solid var(--pc-color-border);text-transform:uppercase;letter-spacing:.03em}
.pa-table td{padding:12px 16px;border-bottom:1px solid var(--pc-color-border);vertical-align:middle;font-size:14px}.pa-table tr:last-child td{border-bottom:0}.pa-table small{display:block;color:var(--pc-color-text-muted);font-size:12px}.pa-scroll{overflow-x:auto}
.pa-badge{display:inline-flex;white-space:nowrap;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:600}.pa-badge.success{background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.pa-badge.info{background:var(--pc-status-info-bg);color:var(--pc-status-info-text)}.pa-badge.danger{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.pa-badge.neutral{background:var(--pc-status-neutral-bg);color:var(--pc-status-neutral-text)}
.pa-dl{display:grid;grid-template-columns:auto 1fr;margin:0}.pa-dl dt,.pa-dl dd{margin:0;padding:9px 0;font-size:14px}.pa-dl dt{color:var(--pc-color-text-muted)}.pa-dl dd{text-align:right;font-weight:600}
.pa-note{margin:10px 0 0;padding-top:10px;border-top:1px solid var(--pc-color-border);color:var(--pc-color-text-muted);font-size:13px}
.pa-alert{padding:14px 16px;border-radius:12px;background:var(--pc-status-info-bg);color:var(--pc-status-info-text);font-size:14px}.pa-alert strong{display:block;margin-bottom:2px}.pa-alert.danger{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}
.pa-lock{display:flex;gap:10px;align-items:flex-start}.pa-lock .material-symbols-outlined{font-size:20px}
.pa-notice{padding:12px 15px;border-radius:10px;margin:16px 0 0;background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.pa-notice.error{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.pa-notice ul{margin:0;padding-left:18px}
.pa-tabs{display:flex;gap:26px;border-bottom:1px solid var(--pc-color-border);margin:18px 0;overflow-x:auto}.pa-tabs a{display:inline-flex;gap:6px;align-items:center;padding:10px 2px;color:var(--pc-color-text-muted);text-decoration:none;font-weight:600;white-space:nowrap;border-bottom:3px solid transparent}.pa-tabs a.on{color:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong)}
.pa-filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.pa-filters input,.pa-filters select{min-height:42px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;background:var(--pc-color-surface,#fff);color:inherit}.pa-filters input{flex:0 1 380px;min-width:220px}
.pa-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 16px;border-top:1px solid var(--pc-color-border);color:var(--pc-color-text-muted);font-size:14px}.pa-foot nav{display:flex;gap:8px}
.pa-empty{padding:30px;text-align:center;color:var(--pc-color-text-muted)}.pa-muted{color:var(--pc-color-text-muted)}
@media(max-width:1100px){.pa-kpis{grid-template-columns:repeat(2,1fr)}.pa-grid{grid-template-columns:1fr}}
@media(max-width:650px){.pa{padding:18px 16px 40px}.pa-kpis{grid-template-columns:1fr 1fr}.pa-head .pa-btn{width:100%}.pa-filters input,.pa-filters select{width:100%;flex:1 1 100%;min-width:0}}
</style>@endpush
@endonce
