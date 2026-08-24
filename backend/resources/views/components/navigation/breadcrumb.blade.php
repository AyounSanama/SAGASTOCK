@php
    $pageTitle = trim($__env->yieldContent('page-title', 'PharmaCare'));
    $breadcrumbItems = collect([
        ['label' => 'Tableau de bord', 'url' => route('dashboard')],
        $pageTitle !== 'Tableau de bord' && $pageTitle !== 'PharmaCare' ? ['label' => $pageTitle] : null,
    ])->filter()->values()->all();
@endphp
<x-app-breadcrumb class="portal-breadcrumb" :items="$breadcrumbItems" />
