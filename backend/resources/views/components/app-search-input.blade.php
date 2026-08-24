@props(['name' => 'search', 'value' => null, 'placeholder' => 'Rechercher...'])
<label class="app-search-input">
    <span class="sr-only">{{ $placeholder }}</span>
    <span class="material-symbols-outlined" aria-hidden="true">search</span>
    <input name="{{ $name }}" value="{{ $value ?? request($name) }}" placeholder="{{ $placeholder }}" {{ $attributes->class('app-search-input__control') }}>
</label>
