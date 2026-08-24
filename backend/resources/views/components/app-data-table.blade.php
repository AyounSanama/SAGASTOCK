@props(['label' => 'Tableau de données', 'minWidth' => '760px'])
<div {{ $attributes->class('app-data-table') }}>
    <div class="app-data-table__scroll">
        <table aria-label="{{ $label }}" style="--app-table-min-width:{{ $minWidth }}">{{ $slot }}</table>
    </div>
</div>
