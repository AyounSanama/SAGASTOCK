{{-- Niveau 2 — Assistant « Créer un projet / programme » : cadre commun aux 4 étapes.
     Variables : $project (null avant l'étape 2), $stepNumber (1 à 4), $stepSubtitle, $summary. --}}
@extends('layouts.portal')
@section('title', ($project && $project->status !== 'draft' ? 'Projet '.$project->code : 'Créer un projet / programme').' · PharmaCare')
@section('page-title', 'Ma Coordination')
@php
    $editing = $project && $project->status !== 'draft';
    $stepUrls = $project ? [
        1 => route('projects.wizard.show', [$project, 'identity']),
        2 => route('projects.wizard.show', [$project, 'identity', 'step' => 'donor']),
        3 => route('projects.wizard.show', [$project, 'standard-list']),
        4 => route('projects.wizard.show', [$project, 'supply']),
    ] : [];
    $stepLabels = array_values(\App\Services\ProjectWizardService::STEPS);
@endphp
@push('styles')<style>
.wz{width:100%;box-sizing:border-box;max-width:1440px;margin:auto;padding:28px 32px 60px;color:var(--pc-color-text)}
.wz-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.wz-head h1{margin:0;font-size:24px}.wz-head p{margin:4px 0 0;color:var(--pc-color-text-muted)}
.wz-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:8px 14px;border-radius:10px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);color:var(--pc-color-text);font:inherit;font-weight:700;text-decoration:none;cursor:pointer;white-space:nowrap}
.wz-btn.primary{background:var(--pc-color-primary-strong);border-color:var(--pc-color-primary-strong);color:#fff}.wz-btn.sm{min-height:32px;padding:5px 11px;font-size:13px;font-weight:600}.wz-btn .material-symbols-outlined{font-size:18px}
.wz-link{border:0;background:none;padding:0;font:inherit;font-weight:700;color:var(--pc-color-primary-strong);cursor:pointer;text-decoration:none}
.wz-steps{display:flex;align-items:center;gap:14px;margin:20px 0;padding:14px 18px;border:1px solid var(--pc-color-border);border-radius:14px;background:var(--pc-color-surface,#fff);list-style:none}
.wz-steps li{display:flex;align-items:center;gap:10px;flex:1 1 0;min-width:0;color:var(--pc-color-text-muted);font-weight:600}.wz-steps li:last-child{flex:0 0 auto}
.wz-steps li::after{content:"";flex:1 1 auto;height:1px;background:var(--pc-color-border);min-width:16px}.wz-steps li:last-child::after{display:none}
.wz-steps a{color:inherit;text-decoration:none;display:flex;align-items:center;gap:10px}.wz-steps a:hover span:last-child{text-decoration:underline}
.wz-num{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;border:1px solid var(--pc-color-border);font-size:13px;font-weight:700}
.wz-steps .done .wz-num{border-color:var(--pc-status-success-text);background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}
.wz-steps .current{color:var(--pc-color-text);font-weight:700}.wz-steps .current .wz-num{border-color:var(--pc-color-primary-strong);background:var(--pc-color-primary-strong);color:#fff}
.wz-progress{display:none}
.wz-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}.wz-grid.full{grid-template-columns:minmax(0,1fr)}
.wz-card{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:20px 20px 22px}.wz-card+.wz-card{margin-top:18px}
.wz-card h2{margin:0;font-size:16px}.wz-card h3{margin:20px 0 10px;font-size:15px}.wz-card h3:first-child{margin-top:0}.wz-sub{margin:4px 0 16px;color:var(--pc-color-text-muted);font-size:14px}
.wz-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}.wz-fields.three{grid-template-columns:repeat(3,minmax(0,1fr))}.wz-fields .wide{grid-column:1/-1}
.wz-field{display:block;font-weight:600;font-size:14px}.wz-field :is(input,select){display:block;box-sizing:border-box;width:100%;margin-top:6px;min-height:42px;border:1px solid var(--pc-color-border);border-radius:10px;padding:8px 12px;font:inherit;font-weight:400;background:var(--pc-color-surface,#fff);color:var(--pc-color-text)}
.wz-field input[readonly]{background:var(--pc-color-surface-subtle,#f3f3f1);color:var(--pc-color-text-muted)}.wz-field small{display:block;margin-top:5px;color:var(--pc-color-text-muted);font-weight:400;font-size:13px}
.wz-field.invalid :is(input,select){border-color:var(--pc-status-danger-text)}.wz-error{display:block;margin-top:5px;color:var(--pc-status-danger-text);font-size:13px;font-weight:600}
.wz-types{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.wz-type{display:flex;gap:12px;padding:16px;border:1px solid var(--pc-color-border);border-radius:12px;cursor:pointer}.wz-type input{margin-top:3px;width:18px;height:18px;accent-color:var(--pc-color-primary-strong)}
.wz-type strong{display:block}.wz-type span{display:block;margin-top:4px;color:var(--pc-color-text-muted);font-size:14px}.wz-type:has(input:checked){border-color:var(--pc-color-primary-strong);background:var(--pc-color-primary-soft)}
.wz-chips{display:flex;flex-wrap:wrap;gap:8px}
.wz-chip{position:relative;display:inline-flex;align-items:center;gap:4px;padding:7px 13px;border-radius:999px;border:1px solid var(--pc-color-border);background:var(--pc-color-surface,#fff);font-size:14px;cursor:pointer;user-select:none}
.wz-chip input{position:absolute;opacity:0;pointer-events:none}.wz-chip:has(input:focus-visible){outline:2px solid var(--pc-color-primary-strong);outline-offset:2px}
.wz-chip:has(input:checked){border-color:var(--pc-color-primary);background:var(--pc-color-primary-soft);color:var(--pc-color-primary-strong)}.wz-chip:has(input:checked)::before{content:"✓";font-weight:700}
.wz-chip small{color:var(--pc-color-text-muted);font-size:12px}
.wz-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:22px}.wz-foot .wz-end{display:flex;align-items:center;gap:14px;margin-left:auto}.wz-foot .wz-end span{color:var(--pc-color-text-muted);font-size:14px}
.wz-recap{background:var(--pc-color-surface,#fff);border:1px solid var(--pc-color-border);border-radius:14px;padding:18px 18px 6px;position:sticky;top:16px}.wz-recap h2{margin:0 0 6px;font-size:16px}
.wz-recap dl{margin:0;display:grid;grid-template-columns:auto minmax(0,1fr)}.wz-recap dt,.wz-recap dd{margin:0;padding:11px 0;border-top:1px solid var(--pc-color-border);font-size:14px}.wz-recap dt{color:var(--pc-color-text-muted);padding-right:12px}.wz-recap dd{text-align:right;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wz-info{margin:18px 0 0;padding:14px 16px;border-radius:10px;background:var(--pc-status-info-bg);color:var(--pc-status-info-text);font-size:14px}
.wz-notice{padding:12px 15px;border-radius:10px;margin:0 0 16px;background:var(--pc-status-success-bg);color:var(--pc-status-success-text)}.wz-notice.error{background:var(--pc-status-danger-bg);color:var(--pc-status-danger-text)}.wz-notice ul{margin:0;padding-left:18px}
.wz-dialog{width:min(460px,calc(100% - 32px));border:0;border-radius:16px;padding:22px;box-shadow:0 24px 70px rgba(15,30,55,.24);color:var(--pc-color-text);background:var(--pc-color-surface,#fff)}.wz-dialog::backdrop{background:rgba(15,30,55,.45)}.wz-dialog h2{margin:0 0 4px;font-size:18px}.wz-dialog p{margin:0 0 16px;color:var(--pc-color-text-muted);font-size:14px}.wz-dialog .wz-field{margin-bottom:14px}.wz-dialog footer{display:flex;justify-content:flex-end;gap:10px;margin-top:6px}
@media(max-width:1100px){.wz-grid{grid-template-columns:1fr}.wz-recap{position:static}}
@media(max-width:900px){.wz-steps{display:none}.wz-progress{display:block;margin:16px 0}.wz-progress strong{display:block;font-size:14px}.wz-progress div{height:4px;margin-top:8px;border-radius:99px;background:var(--pc-color-border)}.wz-progress i{display:block;height:100%;border-radius:99px;background:var(--pc-color-primary)}}
@media(max-width:650px){.wz{padding:18px 16px 40px}.wz-head{flex-direction:column}.wz-head .wz-btn{width:100%}.wz-fields,.wz-fields.three,.wz-types{grid-template-columns:1fr}.wz-foot{flex-wrap:wrap}.wz-foot .wz-end{width:100%;flex-direction:column-reverse;align-items:stretch}.wz-foot .wz-btn{width:100%}}
</style>@endpush
@section('content')
<div class="wz">
    <header class="wz-head">
        <div>
            <h1>{{ $editing ? 'Projet '.$project->code : 'Créer un projet / programme' }}</h1>
            <p>Étape {{ $stepNumber }} sur 4 : {{ $stepSubtitle }}</p>
        </div>
        @unless($editing)
            <button class="wz-btn" type="submit" form="wizard-form" name="intent" value="draft" formnovalidate>Enregistrer le brouillon</button>
        @endunless
    </header>

    @if(session('status'))<div class="wz-notice" role="status" style="margin-top:16px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="wz-notice error" role="alert" style="margin-top:16px"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <ol class="wz-steps" aria-label="Étapes">
        @foreach($stepLabels as $index => $label)
            @php($number = $index + 1)
            @php($state = $number < $stepNumber ? 'done' : ($number === $stepNumber ? 'current' : ''))
            <li class="{{ $state }}" data-step-item="{{ $number }}" @if($state === 'current') aria-current="step" @endif>
                @if(isset($stepUrls[$number]) && $state !== 'current')
                    <a href="{{ $stepUrls[$number] }}"><span class="wz-num">{{ $state === 'done' ? '✓' : $number }}</span><span>{{ $label }}</span></a>
                @else
                    <span class="wz-num">{{ $state === 'done' ? '✓' : $number }}</span><span>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
    <div class="wz-progress" aria-hidden="true"><strong>Étape {{ $stepNumber }} sur 4 · {{ $stepLabels[$stepNumber - 1] }}</strong><div><i style="width:{{ $stepNumber * 25 }}%"></i></div></div>

    @yield('wizard')
</div>
@endsection
