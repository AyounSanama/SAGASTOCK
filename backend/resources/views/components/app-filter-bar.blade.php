@props(['method' => 'get', 'action' => null])
<form method="{{ $method }}" @if($action) action="{{ $action }}" @endif {{ $attributes->class('app-filter-bar') }}>
    <div class="app-filter-bar__fields">{{ $slot }}</div>
    @isset($actions)<div class="app-filter-bar__actions">{{ $actions }}</div>@endisset
</form>
