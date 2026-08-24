@props(['name', 'label', 'type' => 'text', 'value' => null, 'icon' => null, 'hint' => null, 'required' => false, 'disabled' => false, 'autocomplete' => null])
@php
    $fieldError = isset($errors) ? $errors->first($name) : null;
    $fieldId = $attributes->get('id', $name);
@endphp
<label class="app-field {{ $fieldError ? 'is-error' : '' }}" for="{{ $fieldId }}">
    <span class="app-field__label">{{ $label }} @if($required)<span class="app-field__required" aria-hidden="true">*</span>@endif</span>
    <span class="app-field__control {{ $icon ? 'app-field__control--icon' : '' }}">
        @if($icon)<span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>@endif
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->except(['id', 'class'])->class('app-field__input') }} @required($required) @disabled($disabled) @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($fieldError) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif($hint) aria-describedby="{{ $name }}-help" @endif>
    </span>
    @if($fieldError)<span id="{{ $name }}-error" class="app-field__error">{{ $fieldError }}</span>@elseif($hint)<span id="{{ $name }}-help" class="app-field__help">{{ $hint }}</span>@endif
</label>
