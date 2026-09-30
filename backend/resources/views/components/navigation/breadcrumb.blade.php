@php
    $pageTitle = html_entity_decode(trim($__env->yieldContent('page-title', 'PharmaCare')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $breadcrumbItems = collect([
        ['label' => 'Tableau de bord', 'url' => route('dashboard')],
        $pageTitle !== 'Tableau de bord' && $pageTitle !== 'PharmaCare' ? ['label' => $pageTitle] : null,
    ])->filter()->values()->all();
@endphp
<x-app-breadcrumb class="portal-breadcrumb" :items="$breadcrumbItems" />
