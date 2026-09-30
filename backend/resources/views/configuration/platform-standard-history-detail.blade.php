@extends('layouts.portal')
@section('title', 'Détail de l’intervention · PharmaCare')
@section('page-title', 'Détail de l’intervention')
@section('content')
@php
 $labels=['pending'=>'En attente de synchronisation','synced'=>'Synchronisée','error'=>'Échec'];
 $isCurrent=$configuration->status==='active';
 $isRestore=$configuration->intervention_action==='restored';
 $format=function($value){if(is_bool($value))return $value?'Oui':'Non';if(is_array($value))return implode(', ',$value);return filled($value)?(string)$value:'Non défini';};
@endphp
<main class="hd-page">
 <a class="hd-back" href="{{ route('configuration.platform-standards.index',['tab'=>'history']) }}"><span class="material-symbols-outlined">arrow_back</span>Retour à l’historique</a>
 <x-app-page-header title="Détail de l’intervention" description="Consultez les informations et les modifications de cette version de configuration." />
 <section class="hd-summary">
   <div><small>Organisation</small><strong>{{ optional($configuration->organization)->name ?: 'Organisation indisponible' }}</strong></div>
   <div><small>Date et heure</small><strong>{{ optional($configuration->effective_at)->format('d/m/Y à H:i') }}</strong></div>
   <div><small>Auteur</small><strong>{{ optional($configuration->appliedBy)->name ?: 'Système' }}</strong></div>
   <div><small>Catégorie</small><strong>{{ $categoryLabels[$configuration->configuration_category] ?? 'Configuration' }}</strong></div>
   <div><small>Version précédente</small><strong>{{ $configuration->previous_configuration_version ? 'v1.'.($configuration->previous_configuration_version-1) : 'Aucune' }}</strong></div>
   <div><small>Nouvelle version</small><strong>v1.{{ max(0,$configuration->configuration_version-1) }}</strong></div>
   <div><small>Intervention</small><strong>{{ $isRestore?'Restauration':($configuration->previous_configuration_version?'Modification':'Création') }}</strong></div>
   <div><small>Statut</small><strong>{{ $isRestore?'Restaurée':($labels[$configuration->synchronization_status]??'Appliquée') }}</strong></div>
 </section>
 <section class="hd-card"><header><span class="material-symbols-outlined">difference</span><div><small>MODIFICATIONS</small><h2>Avant / Après</h2></div></header>
  <div class="hd-changes">
  @forelse((array)$configuration->changes as $change)
   <article><strong>{{ $change['label']??$change['key']??'Paramètre' }}</strong><div><span><small>Avant</small>{{ $format($change['old']??null) }}</span><i class="material-symbols-outlined">arrow_forward</i><span><small>Après</small>{{ $format($change['new']??null) }}</span></div></article>
  @empty
   <div class="hd-empty"><span class="material-symbols-outlined">history</span>Cette intervention restaure le contenu complet d’une version antérieure. Aucune donnée historique n’a été supprimée.</div>
  @endforelse
  </div>
 </section>
 <footer class="hd-actions">
   <a class="hd-button outline" href="{{ route('configuration.platform-standards.organizations.assist',$configuration->organization_id) }}"><span class="material-symbols-outlined">tune</span>Voir la configuration actuelle</a>
   @unless($isCurrent)<form method="post" action="{{ route('configuration.platform-standards.history.restore',$configuration) }}" onsubmit="return confirm('Restaurer cette configuration dans une nouvelle version ? L’historique actuel sera conservé.')">@csrf<button class="hd-button primary" type="submit"><span class="material-symbols-outlined">settings_backup_restore</span>Restaurer cette configuration</button></form>@endunless
 </footer>
</main>
@endsection
@push('styles')
<style>
.hd-page{color:var(--pc-color-text);max-width:1180px}.hd-back{display:inline-flex;align-items:center;gap:6px;color:var(--pc-status-info-text);text-decoration:none;font-weight:700;margin-bottom:12px}.hd-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}.hd-summary div,.hd-card{background:#fff;border:1px solid var(--pc-color-border);border-radius:14px;box-shadow:0 8px 22px rgba(36,50,74,.035)}.hd-summary div{padding:16px}.hd-summary small,.hd-summary strong{display:block}.hd-summary small{color:var(--pc-color-text-muted);margin-bottom:7px}.hd-card{padding:18px}.hd-card header{display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--pc-color-border);padding-bottom:14px}.hd-card header>.material-symbols-outlined{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}.hd-card h2,.hd-card header small{margin:0}.hd-card header small{color:var(--pc-color-primary-strong);font-weight:800}.hd-changes{display:grid;gap:10px;margin-top:14px}.hd-changes article{display:grid;grid-template-columns:minmax(180px,.7fr) 1fr;align-items:center;gap:18px;padding:14px;border:1px solid var(--pc-color-border);border-radius:11px}.hd-changes article>div{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center}.hd-changes article span{padding:10px;background:var(--pc-color-background);border-radius:9px}.hd-changes article span small{display:block;color:var(--pc-color-text-muted);margin-bottom:4px}.hd-changes article i{color:var(--pc-color-primary-strong)}.hd-empty{text-align:center;color:var(--pc-color-text-muted);padding:30px}.hd-empty span{display:block;font-size:36px}.hd-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.hd-button{min-height:46px;padding:0 17px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-weight:700}.hd-button.outline{border:1px solid var(--pc-color-primary-strong);color:var(--pc-color-primary-strong);background:#fff}.hd-button.primary{border:1px solid var(--pc-color-primary-strong);color:#fff;background:var(--pc-color-primary-strong)}
@media(max-width:900px){.hd-summary{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.hd-summary{grid-template-columns:1fr}.hd-changes article{grid-template-columns:1fr}.hd-actions{flex-direction:column}.hd-actions form,.hd-button{width:100%}}
</style>
@endpush
