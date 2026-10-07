@extends('layouts.portal')
@section('title', 'Référentiel médical · PharmaCare')
@section('page-title', auth()->user() && app(\App\Services\GovernanceService::class)->roleCode(auth()->user()) === \App\Services\GovernanceService::COORDINATION_ADMIN ? 'Référentiels' : 'Configuration des projets')
@section('content')
<x-app-page-header title="Référentiel médical" description="Niveaux de soins hiérarchiques utilisés pour générer les listes standards : Niveau → Catégorie → Programme." />
@include('projects.partials.configuration-tabs', ['activeTab' => 'medical'])
@if(session('status'))<div class="med-alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="med-alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="med-layout">
 <x-app-card>
  <h3 class="med-title">Niveaux de soins</h3>
  <p class="med-muted">Les éléments marqués « Référentiel global » sont fournis par la plateforme ; vous pouvez y rattacher vos propres catégories et programmes.</p>
  @if(empty($tree) || count($tree) === 0)
   <x-app-empty-state icon="account_tree" title="Aucun niveau de soins" description="Ajoutez un premier niveau de service." />
  @else
   <ul class="med-tree">@foreach($tree as $node)@include('projects.partials.care-level-node', ['node' => $node])@endforeach</ul>
  @endif
 </x-app-card>

 @if($canManage)
 <x-app-card>
  <h3 class="med-title" id="ajouter-un-service">Ajouter un service (niveau, catégorie ou programme)</h3>
  <p class="mc-muted">Le service ajouté est propre à votre organisation ; le référentiel commun reste inchangé.</p>
  <form method="post" action="{{ route('projects.medical-references.care-levels.store') }}" class="med-form">@csrf
   <label>Rattacher à
    <select name="parent_id">
     <option value="">— Nouveau niveau de soins (racine) —</option>
     @foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id') === $parent->id)>{{ str_repeat('— ', $parent->depth - 1) }}{{ $parent->name }} ({{ $levels[$parent->depth] ?? '' }})</option>@endforeach
    </select>
    <small>Racine = niveau de service ; sous un niveau = catégorie ; sous une catégorie = programme.</small>
   </label>
   <label>Code *<input name="code" value="{{ old('code') }}" required maxlength="60"></label>
   <label>Nom *<input name="name" value="{{ old('name') }}" required maxlength="180"></label>
   <label>Description<input name="description" value="{{ old('description') }}" maxlength="2000"></label>
   <x-app-button type="submit" icon="add">Ajouter</x-app-button>
  </form>
 </x-app-card>
 @endif
</div>

<div class="med-layout med-flat">
 @foreach(['target_population' => ['Populations cibles', $populations, 'Ex. Adultes, Femmes enceintes, Enfants < 5 ans'], 'pathology' => ['Pathologies', $pathologies, 'Ex. Paludisme, Tuberculose, Malnutrition aiguë'], 'laboratory_exam' => ['Examens de laboratoire', $laboratoryExams, 'Ex. Goutte épaisse, Charge virale VIH']] as $type => [$title, $items, $hint])
 <x-app-card>
  <h3 class="med-title">{{ $title }}</h3>
  <p class="med-muted">Référentiel configurable, jamais codé en dur. Les éléments retenus pour chaque projet se choisissent dans sa configuration médicale.</p>
  @if($items->isEmpty())<p class="med-muted">Aucun élément pour le moment.</p>@else
  <div class="med-chips">@foreach($items as $item)<span class="med-chip {{ $item->is_active ? 'active' : 'inactive' }}" title="{{ $item->code }}">{{ $item->name }}@if($item->organization_id === null) <small>global</small>@endif</span>@endforeach</div>
  @endif
  @if($canManage)
  <form method="post" action="{{ route('projects.medical-references.store', $type) }}" class="med-inline">@csrf
   <input name="code" placeholder="Code *" required maxlength="60"><input name="name" placeholder="{{ $hint }}" required maxlength="180">
   <x-app-button type="submit" variant="secondary" icon="add">Ajouter</x-app-button>
  </form>
  @endif
 </x-app-card>
 @endforeach
</div>
@endsection

@push('styles')<style>
.med-alert{margin:0 0 16px;padding:12px 14px;border-radius:12px;border:1px solid;font-size:13px}.med-alert.success{background:var(--pc-status-success-bg);border-color:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.med-alert.error{background:var(--pc-status-danger-bg);border-color:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.med-alert ul{margin:0;padding-left:18px}
.med-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:16px;align-items:start}
.med-title{margin:0 0 6px;font-size:15px}.med-muted{margin:0 0 12px;color:var(--pc-color-text-muted);font-size:12px}
.med-tree,.med-tree ul{list-style:none;margin:0;padding:0}.med-tree ul{margin-left:18px;border-left:1px dashed var(--pc-color-border);padding-left:12px}
.med-node{display:flex;align-items:center;gap:8px;justify-content:space-between;padding:7px 8px;border-radius:9px;font-size:13px}.med-node:hover{background:var(--pc-color-background)}
.med-node strong{font-weight:700}.med-node small{color:var(--pc-color-text-muted);font-size:11px;margin-left:6px}.med-node .tags{display:flex;gap:6px;align-items:center}.med-node form{margin:0}
.med-node .inactive{opacity:.55}
.med-form{display:grid;gap:10px}.med-form label{display:grid;gap:5px;font-size:12px;font-weight:700}.med-form small{font-weight:400;color:var(--pc-color-text-muted)}
.med-form input,.med-form select{min-height:38px;border:1px solid var(--pc-color-border);border-radius:10px;padding:7px 10px;font:inherit;font-size:13px;background:var(--pc-color-surface)}
.med-flat{margin-top:16px;grid-template-columns:repeat(2,minmax(0,1fr))}
.med-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}.med-chip{padding:4px 10px;border:1px solid var(--pc-color-border);border-radius:999px;font-size:12px;background:var(--pc-color-surface)}.med-chip small{color:var(--pc-color-text-muted);font-size:10px}.med-chip.inactive{opacity:.5}
.med-inline{display:grid;grid-template-columns:110px minmax(0,1fr) auto;gap:8px}.med-inline input{min-height:36px;border:1px solid var(--pc-color-border);border-radius:10px;padding:6px 10px;font:inherit;font-size:13px}
@media(max-width:900px){.med-layout,.med-flat{grid-template-columns:1fr}.med-inline{grid-template-columns:1fr}}
</style>@endpush
