@extends('layouts.portal')
@section('title', 'Configuration Initiale · PharmaCare')
@section('page-title', $workflow->flow_type->label())
@section('content')
@php
    $completedCount = $completedSteps->filter(fn($step)=>$step <= 11)->count();
    $percent = (int) round(($completedCount / 11) * 100);
@endphp
<section class="configuration-page"
         data-workflow-step="{{ $activeStep }}"
         data-workflow-draft-url="{{ route('configuration.workflow.draft', $workflow->workflow_id) }}">
    <a class="configuration-home-back" href="{{ route('configuration.index', ['_flow'=>$workflow->workflow_id]) }}">
        ← Retour à l’accueil Configuration
    </a>
    <article class="configuration-intro">
        <div class="intro-icon">⚙</div>
        <div class="intro-copy"><h2>{{ $workflow->flow_type->label() }}</h2><p>Workflow version {{ $workflow->version }} · progression indépendante et sécurisée.</p></div>
        <div class="intro-progress">
            <strong>Étape {{ min($activeStep,11) }} sur 11</strong>
            <div class="progress-row"><span><i style="width:{{ $percent }}%"></i></span><b>{{ $percent }}%</b></div>
            <small data-draft-status aria-live="polite">Brouillon synchronisé</small>
        </div>
    </article>
    <section class="configuration-wizard">
        @include('configuration.components.stepper')
        <div class="wizard-panel">
            @if($activeStep === 1)
                @include('configuration.steps.organization')
            @elseif($activeStep === 2)
                @include('configuration.steps.mission')
            @else
                @include('configuration.steps.advanced')
            @endif
        </div>
    </section>
</section>
@endsection
