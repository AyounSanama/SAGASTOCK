@extends('layouts.portal')
@section('title', 'Configuration médicale · '.$project->name.' · PharmaCare')
@section('page-title', $canManage ? 'Configuration des projets' : 'Mon projet')
@section('content')
@php
    $selectedLevels = collect($configuration['care_levels'])->pluck('id')->all();
    $selectedPopulations = collect($configuration['target_populations'])->pluck('id')->all();
    $pathologyLinks = collect($configuration['pathologies'])->mapWithKeys(fn ($row) => [$row['id'] => collect($row['target_population_ids'])->all()]);
@endphp
<x-app-page-header :title="'Configuration médicale — '.$project->name" :description="$project->code.' · '.$project->mission?->name.' · Niveaux de soins, populations cibles et pathologies qui déterminent les produits autorisés.'">
 <x-slot:actions><a class="app-button app-button--secondary" href="{{ route('modules.projects') }}">Retour aux projets</a></x-slot:actions>
</x-app-page-header>
@if(session('status'))<div class="mc-alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mc-alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@unless($configuration['is_configured'])<div class="mc-alert warning">Configuration incomplète : sélectionnez au moins un niveau de soins et une population cible.</div>@endunless

@if($canManage)
<form method="post" action="{{ route('projects.medical-configuration.update', $project) }}">@csrf @method('PUT')
 <div class="mc-grid">
  <x-app-card>
   <h3 class="mc-title">1. Niveaux de soins</h3>
   <p class="mc-muted">Cochez les niveaux, catégories ou programmes couverts par le projet. <a href="{{ route('projects.medical-references') }}">Gérer le référentiel</a></p>
   <ul class="mc-tree">@foreach($options['care_level_tree'] as $node)@include('projects.partials.care-level-checkbox', ['node' => $node, 'selected' => $selectedLevels])@endforeach</ul>
  </x-app-card>
  <x-app-card>
   <h3 class="mc-title">2. Populations cibles</h3>
   @forelse($options['target_populations'] as $population)
    <label class="mc-check"><input type="checkbox" name="target_population_ids[]" value="{{ $population->id }}" @checked(in_array($population->id, old('target_population_ids', $selectedPopulations)))> {{ $population->name }}</label>
   @empty<p class="mc-muted">Aucune population cible. <a href="{{ route('projects.medical-references') }}">En ajouter</a></p>@endforelse
  </x-app-card>
 </div>
 <x-app-card class="mc-block">
  <h3 class="mc-title">3. Pathologies par population cible</h3>
  <p class="mc-muted">Associez chaque pathologie aux populations concernées. Seules les populations retenues à l’étape 2 sont acceptées.</p>
  @if($options['pathologies']->isEmpty())<p class="mc-muted">Aucune pathologie. <a href="{{ route('projects.medical-references') }}">En ajouter</a></p>@else
  <div class="mc-table-wrap"><table class="mc-table"><thead><tr><th>Pathologie</th>@foreach($options['target_populations'] as $population)<th>{{ $population->name }}</th>@endforeach</tr></thead><tbody>
  @foreach($options['pathologies'] as $pathology)<tr><td>{{ $pathology->name }}</td>@foreach($options['target_populations'] as $population)<td><input type="checkbox" aria-label="{{ $pathology->name }} — {{ $population->name }}" name="pathologies[{{ $pathology->id }}][]" value="{{ $population->id }}" @checked(in_array($population->id, $pathologyLinks->get($pathology->id, [])))></td>@endforeach</tr>@endforeach
  </tbody></table></div>
  @endif
 </x-app-card>
 <div class="mc-actions"><x-app-button type="submit" icon="save">Enregistrer la configuration médicale</x-app-button></div>
</form>
@else
<div class="mc-grid">
 <x-app-card><h3 class="mc-title">Niveaux de soins</h3>@forelse($configuration['care_levels'] as $level)<span class="mc-chip">{{ $level->name }} <small>{{ $levels[$level->depth] ?? '' }}</small></span>@empty<p class="mc-muted">Non configuré.</p>@endforelse</x-app-card>
 <x-app-card><h3 class="mc-title">Populations cibles</h3>@forelse($configuration['target_populations'] as $population)<span class="mc-chip">{{ $population->name }}</span>@empty<p class="mc-muted">Non configuré.</p>@endforelse</x-app-card>
</div>
<x-app-card class="mc-block"><h3 class="mc-title">Pathologies</h3>
 @php($populationNames = collect($configuration['target_populations'])->pluck('name', 'id'))
 @forelse($configuration['pathologies'] as $pathology)<div class="mc-row"><strong>{{ $pathology['name'] }}</strong><span>{{ collect($pathology['target_population_ids'])->map(fn ($id) => $populationNames[$id] ?? '')->filter()->join(', ') }}</span></div>@empty<p class="mc-muted">Aucune pathologie associée.</p>@endforelse
 <p class="mc-muted">Cette configuration est définie par la Coordination.</p>
</x-app-card>
@endif
@endsection

@push('styles')<style>
.mc-alert{margin:0 0 14px;padding:12px 14px;border-radius:12px;border:1px solid;font-size:13px}.mc-alert.success{background:var(--pc-status-success-bg);border-color:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.mc-alert.error{background:var(--pc-status-danger-bg);border-color:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.mc-alert.warning{background:var(--pc-status-info-bg);border-color:var(--pc-color-border);color:var(--pc-status-info-text)}.mc-alert ul{margin:0;padding-left:18px}
.mc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}.mc-block{margin-top:16px}
.mc-title{margin:0 0 6px;font-size:15px}.mc-muted{margin:0 0 10px;color:var(--pc-color-text-muted);font-size:12px}
.mc-tree,.mc-tree ul{list-style:none;margin:0;padding:0}.mc-tree ul{margin-left:18px;border-left:1px dashed var(--pc-color-border);padding-left:10px}
.mc-check{display:flex;align-items:center;gap:8px;padding:5px 4px;font-size:13px;cursor:pointer}.mc-check small{color:var(--pc-color-text-muted);font-size:11px}.mc-check input{accent-color:var(--pc-color-primary)}
.mc-table-wrap{overflow-x:auto}.mc-table{width:100%;border-collapse:collapse;font-size:13px}.mc-table th,.mc-table td{padding:8px 10px;border-bottom:1px solid var(--pc-color-border);text-align:center}.mc-table th:first-child,.mc-table td:first-child{text-align:left}.mc-table th{font-size:11px;text-transform:uppercase;color:var(--pc-color-text-muted);background:var(--pc-color-background)}.mc-table input{accent-color:var(--pc-color-primary);width:16px;height:16px}
.mc-actions{display:flex;justify-content:flex-end;margin-top:16px}
.mc-chip{display:inline-block;margin:0 6px 6px 0;padding:4px 10px;border:1px solid var(--pc-color-border);border-radius:999px;font-size:12px}.mc-chip small{color:var(--pc-color-text-muted);font-size:10px}
.mc-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--pc-color-border);font-size:13px}.mc-row span{color:var(--pc-color-text-muted)}
@media(max-width:900px){.mc-grid{grid-template-columns:1fr}}
</style>@endpush
