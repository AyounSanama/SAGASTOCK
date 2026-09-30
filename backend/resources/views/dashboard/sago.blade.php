@extends('layouts.portal')
@section('title','Tableau de bord · PharmaCare')
@section('page-title','Tableau de bord')
@section('hide-breadcrumb','1')
@section('content')
<main class="sd-page">
 <header class="sd-head"><h1>Bienvenue, {{ auth()->user()->first_name ?: auth()->user()->name }}</h1><p>Vue d’ensemble globale de la plateforme PharmaCare.</p></header>
 <section class="pc-kpi-grid pc-kpi-grid--five" aria-label="Indicateurs des organisations">
 @foreach([
  ['domain',$sagoStats['organizations_total'],'Organisations enregistrées','blue',route('configuration.organization')],
  ['verified_user',$sagoStats['active'],'Organisations actives','green',route('configuration.organization',['status'=>'active'])],
  ['public',$sagoStats['single'],'Organisations unipays','purple',route('configuration.organization',['access'=>'single_country'])],
  ['language',$sagoStats['multi'],'Organisations multipays','orange',route('configuration.organization',['access'=>'multi_country'])],
  ['shield',$sagoStats['compliance'].'%','Configurations synchronisées','navy',route('configuration.platform-standards.index')]
 ] as [$icon,$value,$label,$tone,$url])
  <x-app-kpi-card :label="$label" :value="$value" :icon="$icon" :href="$url" caption="Voir les détails" />
 @endforeach
 </section>
 <div class="pc-dashboard-grid">
  <section class="sd-card pc-dashboard-main"><header><h2>Gestion de la plateforme</h2></header><div class="sd-action-list">
   <a href="{{ route('configuration.organization') }}"><span class="material-symbols-outlined">domain</span><span><strong>Organisations</strong><small>Consulter et gérer les organisations autorisées.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
   <a href="{{ route('configuration.platform-standards.index') }}"><span class="material-symbols-outlined">tune</span><span><strong>Standards et référentiels</strong><small>Accéder aux paramètres de plateforme autorisés.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
   <a href="{{ route('profile.show') }}"><span class="material-symbols-outlined">person</span><span><strong>Mon profil</strong><small>Gérer vos informations personnelles et votre mot de passe.</small></span><span class="material-symbols-outlined">arrow_forward</span></a>
  </div></section>
  <aside class="sd-card sd-chart pc-dashboard-aside"><h2>Répartition des organisations</h2><div class="sd-donut" style="--active:{{ $sagoStats['organizations_total'] ? ($sagoStats['active']/$sagoStats['organizations_total'])*100 : 0 }}%"><strong>{{ $sagoStats['organizations_total'] }}</strong><small>Total</small></div><ul><li><i></i><span>Actives</span><b>{{ $sagoStats['active'] }}</b></li><li><i></i><span>Inactives</span><b>{{ $sagoStats['inactive'] }}</b></li><li><i></i><span>Unipays</span><b>{{ $sagoStats['single'] }}</b></li><li><i></i><span>Multipays</span><b>{{ $sagoStats['multi'] }}</b></li></ul></aside>
 </div>
</main>
@endsection
