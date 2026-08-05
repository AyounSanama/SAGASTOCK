@php
    $steps = [
        1 => 'Organisation / ONG', 2 => 'Mission', 3 => 'Projets',
        4 => 'Bailleurs', 5 => 'Programmes', 6 => 'Modules',
        7 => 'Fonctionnalités', 8 => 'Listes standards',
        9 => 'Formations sanitaires', 10 => 'Sites de dispensation',
        11 => 'Utilisateurs et accès', 12 => 'Résumé et validation',
    ];
@endphp
<nav class="configuration-stepper" aria-label="Étapes de configuration">
    @foreach($steps as $number => $label)
        @php
            $state = $stepStates[(string)$number] ?? 'not_started';
            $done = $state === 'valid';
            $active = $activeStep === $number;
            $accessible = $done || $active || ($number > 1 && $completedSteps->contains($number - 1));
            $url = match (true) {
                $number === 1 && $accessible => route('configuration.organization', ['_flow'=>$workflow->workflow_id]),
                $number === 2 && $accessible => route('configuration.mission', ['_flow'=>$workflow->workflow_id]),
                $number >= 3 && $accessible => route('configuration.step', ['step'=>array_search($number, \App\Http\Controllers\Web\ConfigurationWizardController::STEPS, true),'_flow'=>$workflow->workflow_id]),
                default => null,
            };
        @endphp
        @if($url)
            <a class="configuration-step {{ $done ? 'done' : '' }} {{ $active ? 'active' : '' }} {{ $state === 'needs_correction' ? 'needs-correction' : '' }} {{ $accessible && !$done && !$active ? 'available' : '' }}" href="{{ $url }}">
        @else
            <span class="configuration-step future" aria-disabled="true">
        @endif
            <span class="step-number">{{ $done ? '✓' : $number }}</span>
            <span class="step-label">{{ $label }}</span>
            @if($done)<span class="step-check">✓</span>@endif
            @if($state === 'in_progress' && !$active)<small>En cours</small>@endif
            @if($state === 'needs_correction')<small>À corriger</small>@endif
        @if($url)</a>@else</span>@endif
    @endforeach
</nav>
