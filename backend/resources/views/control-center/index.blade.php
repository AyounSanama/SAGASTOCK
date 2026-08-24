@extends('layouts.portal')
@section('title', 'Configuration · PharmaCare')
@section('page-title', 'Configuration')
@section('content')
<section class="configuration-dashboard">
    <header class="configuration-header">
        <div class="configuration-header-icon material-symbols-outlined">settings</div>
        <div><span class="eyebrow">Administration de la plateforme</span><h1>Configuration</h1><p>Gérez les organisations et les référentiels centraux de PharmaCare.</p></div>
        @if(app(\App\Services\GovernanceService::class)->roleCode(auth()->user()) === \App\Services\GovernanceService::SAGO_ADMIN)
            <a class="configuration-add-organization" href="{{ route('configuration.organization', ['create' => 1]) }}"><span class="material-symbols-outlined">add</span><span>Ajouter une organisation</span></a>
        @endif
    </header>
    <div class="configuration-access-grid" aria-label="Rubriques de configuration">
        <a class="configuration-access-card" href="{{ route('configuration.organization') }}">
            <span class="configuration-access-icon material-symbols-outlined">corporate_fare</span>
            <span class="configuration-access-copy"><strong>Organisations</strong><b>{{ $configurationStats['organizations'] }}</b><small>organisation(s) enregistrée(s)</small></span>
            <span class="configuration-access-action">Gérer <span class="material-symbols-outlined">arrow_forward</span></span>
        </a>
        <a class="configuration-access-card" href="{{ route('configuration.platform-standards.index') }}" aria-label="Ouvrir Standards et référentiels">
            <span class="configuration-access-icon material-symbols-outlined">format_list_bulleted</span>
            <span class="configuration-access-copy"><strong>Standards &amp; Référentiels</strong><b>{{ $configurationStats['standards'] }}</b><small>standard(s) plateforme</small></span>
            <span class="configuration-access-action">Gérer <span class="material-symbols-outlined">arrow_forward</span></span>
        </a>
    </div>
</section>
@endsection
@push('styles')
<style>
.configuration-dashboard{display:grid;gap:24px;width:100%;min-width:0}.configuration-header{display:flex;align-items:center;gap:16px;padding:24px;background:#fff;border:1px solid var(--pc-border);border-radius:18px;box-shadow:var(--pc-shadow)}.configuration-header-icon{width:58px;height:58px;flex:0 0 58px;display:grid;place-items:center;border-radius:15px;background:var(--pc-orange-light,#FFF3E8);color:var(--pc-orange);font-size:31px}.configuration-header h1{margin:4px 0 5px;font-size:clamp(1.65rem,2.5vw,2.1rem)!important}.configuration-header p{margin:0;color:var(--pc-muted)}.configuration-header .eyebrow{color:var(--pc-orange);font-size:.73rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.configuration-add-organization{margin-left:auto;min-height:46px;padding:11px 17px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;background:var(--pc-orange);color:#fff;font-size:.86rem;font-weight:800;white-space:nowrap;box-shadow:0 8px 18px rgba(245,124,0,.22)}.configuration-add-organization .material-symbols-outlined{font-size:21px}.configuration-add-organization:hover{background:#D96E00;transform:translateY(-1px)}.configuration-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.configuration-access-card{min-width:0;min-height:230px;padding:22px;display:flex;flex-direction:column;align-items:flex-start;color:var(--pc-ink);background:#fff;border:1px solid var(--pc-border);border-radius:18px;box-shadow:0 10px 28px rgba(36,50,74,.07);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}a.configuration-access-card:hover{transform:translateY(-3px);border-color:rgba(245,124,0,.45);box-shadow:0 16px 34px rgba(36,50,74,.11)}.configuration-access-card-pending{border-style:dashed}.configuration-access-icon{width:52px;height:52px;display:grid;place-items:center;border-radius:14px;color:var(--pc-orange);background:#FFF3E8;font-size:28px}.configuration-access-copy{margin-top:20px;display:flex;flex-direction:column}.configuration-access-copy strong{font-size:1.08rem}.configuration-access-copy b{margin-top:9px;font-size:2rem;line-height:1;color:var(--pc-ink)}.configuration-access-copy small{margin-top:7px;color:var(--pc-muted);font-size:.8rem}.configuration-access-action{margin-top:auto;padding-top:18px;display:inline-flex;align-items:center;gap:6px;color:var(--pc-orange);font-size:.84rem;font-weight:800}.configuration-access-action .material-symbols-outlined{font-size:18px}@media(max-width:1000px){.configuration-header{flex-wrap:wrap}.configuration-add-organization{margin-left:74px}}@media(max-width:650px){.configuration-header{align-items:flex-start;padding:19px}.configuration-add-organization{width:100%;margin-left:0}.configuration-access-grid{grid-template-columns:1fr}.configuration-access-card{min-height:205px}}
</style>
@endpush
