@php
    $pageTitle = trim($__env->yieldContent('page-title', 'PharmaCare'));
@endphp
<nav class="portal-breadcrumb" aria-label="Fil d’Ariane">
    <a href="{{ route('dashboard') }}">Tableau de bord</a>
    @if($pageTitle !== 'Tableau de bord' && $pageTitle !== 'PharmaCare')
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $pageTitle }}</span>
    @endif
</nav>
