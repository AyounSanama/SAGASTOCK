@props(['paginator'])
@if($paginator->hasPages())
<nav class="app-pagination" aria-label="Pagination">
    <a class="app-pagination__button" href="{{ $paginator->previousPageUrl() ?: '#' }}" @if($paginator->onFirstPage()) aria-disabled="true" @endif aria-label="Page précédente"><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></a>
    <span class="app-pagination__summary">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
    <a class="app-pagination__button" href="{{ $paginator->nextPageUrl() ?: '#' }}" @if(!$paginator->hasMorePages()) aria-disabled="true" @endif aria-label="Page suivante"><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></a>
</nav>
@endif
