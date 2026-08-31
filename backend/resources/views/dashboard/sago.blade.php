@extends('layouts.portal')
@section('title','Tableau de bord · PharmaCare')
@section('page-title','Tableau de bord')
@section('hide-breadcrumb','1')
@section('content')
<main class="sd-page">
 <header class="sd-head"><h1>Bienvenue, {{ auth()->user()->first_name ?: auth()->user()->name }}</h1><p>Vue d’ensemble globale de la plateforme PharmaCare.</p></header>
 <section class="sd-kpis" aria-label="Indicateurs des organisations">
 @foreach([
  ['domain',$sagoStats['organizations_total'],'Organisations enregistrées','blue',route('configuration.organization')],
  ['verified_user',$sagoStats['active'],'Organisations actives','green',route('configuration.organization',['status'=>'active'])],
  ['public',$sagoStats['single'],'Organisations unipays','purple',route('configuration.organization',['access'=>'single_country'])],
  ['language',$sagoStats['multi'],'Organisations multipays','orange',route('configuration.organization',['access'=>'multi_country'])],
  ['shield',$sagoStats['compliance'].'%','Taux global de conformité','navy',route('configuration.platform-standards.index')]
 ] as [$icon,$value,$label,$tone,$url])
  <a href="{{ $url }}" class="{{ $tone }}"><i class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</i><strong>{{ $value }}</strong><span>{{ $label }}</span><small>Voir les détails →</small></a>
 @endforeach
 </section>
 <div class="sd-grid">
  <section class="sd-card"><header><h2>Gestion de la plateforme</h2></header><div class="sd-action-list">
   <a href="{{ route('configuration.organization') }}"><span class="material-symbols-outlined">domain</span><span><strong>Organisations</strong><small>Consulter et gérer les organisations autorisées.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
   <a href="{{ route('configuration.platform-standards.index') }}"><span class="material-symbols-outlined">tune</span><span><strong>Standards et référentiels</strong><small>Accéder aux paramètres de plateforme autorisés.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
   <a href="{{ route('profile.show') }}"><span class="material-symbols-outlined">person</span><span><strong>Mon profil</strong><small>Gérer vos informations personnelles et votre mot de passe.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
  </div></section>
  <aside class="sd-card sd-chart"><h2>Répartition des organisations</h2><div class="sd-donut" style="--active:{{ $sagoStats['organizations_total'] ? ($sagoStats['active']/$sagoStats['organizations_total'])*100 : 0 }}%"><strong>{{ $sagoStats['organizations_total'] }}</strong><small>Total</small></div><ul><li><i></i><span>Actives</span><b>{{ $sagoStats['active'] }}</b></li><li><i></i><span>Inactives</span><b>{{ $sagoStats['inactive'] }}</b></li><li><i></i><span>Unipays</span><b>{{ $sagoStats['single'] }}</b></li><li><i></i><span>Multipays</span><b>{{ $sagoStats['multi'] }}</b></li></ul></aside>
 </div>
</main>
@endsection
@push('styles')<style>
.sd-page{display:grid;gap:18px;color:#17233d}.sd-head h1{font-size:22px;margin:0 0 5px}.sd-head p{margin:0;color:#66738b}.sd-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.sd-kpis a{min-width:0;display:grid;grid-template-columns:42px minmax(0,1fr);gap:3px 10px;padding:16px;background:#fff;border:1px solid #e8edf3;border-radius:14px;color:#17233d}.sd-kpis i{grid-row:1/4;width:42px;height:42px;display:grid;place-items:center;border-radius:12px}.sd-kpis strong{font-size:24px}.sd-kpis span{font-size:11px;font-weight:700}.sd-kpis small{grid-column:1/-1;margin-top:10px;font-size:10px}.sd-kpis .blue{background:#eaf2ff;color:#2563eb}.sd-kpis .green{background:#e9f8ed;color:#22a447}.sd-kpis .purple{background:#f0e9ff;color:#7c3aed}.sd-kpis .orange{background:#fff3e8;color:#f57c00}.sd-kpis .navy{background:#eef2f7;color:#17233d}.sd-grid{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:16px}.sd-card{background:#fff;border:1px solid #e8edf3;border-radius:14px;overflow:hidden}.sd-card>header{padding:15px;border-bottom:1px solid #e8edf3}.sd-card h2{font-size:14px;margin:0}.sd-action-list{display:grid;padding:8px}.sd-action-list>a{display:grid;grid-template-columns:38px minmax(0,1fr) 20px;align-items:center;gap:12px;padding:14px;border-radius:10px;color:#17233d}.sd-action-list>a:hover{background:#fff3e8}.sd-action-list>a>span:first-child{width:38px;height:38px;display:grid;place-items:center;border-radius:10px;background:#fff3e8;color:#f57c00}.sd-action-list strong,.sd-action-list small{display:block}.sd-action-list small{margin-top:3px;color:#66738b}.sd-chart{padding:17px}.sd-donut{position:relative;width:145px;height:145px;margin:24px auto;display:grid;place-content:center;text-align:center;border-radius:50%;background:conic-gradient(#22a447 0 var(--active),#f57c00 var(--active) 100%)}.sd-donut:before{content:'';position:absolute;inset:27px;background:#fff;border-radius:50%}.sd-donut>*{position:relative}.sd-chart ul{list-style:none;padding:0}.sd-chart li{display:flex;gap:8px;padding:6px;font-size:11px}.sd-chart li i{width:8px;height:8px;background:#22a447;border-radius:3px}.sd-chart li:nth-child(2) i{background:#f57c00}.sd-chart li:nth-child(3) i{background:#2563eb}.sd-chart li:nth-child(4) i{background:#7c3aed}.sd-chart li span{flex:1}
@media(max-width:1100px){.sd-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.sd-kpis{grid-template-columns:repeat(2,1fr)}.sd-grid{grid-template-columns:1fr}.sd-kpis a:last-child{grid-column:1/-1}}
</style>@endpush
