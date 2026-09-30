@props(['id', 'title', 'description' => null, 'width' => '720px', 'autoOpen' => false])
<dialog id="{{ $id }}" class="form-sheet" style="--sheet-width:{{ $width }}" aria-labelledby="{{ $id }}-title" @if($autoOpen) data-sheet-auto-open @endif @if($description) aria-describedby="{{ $id }}-description" @endif>
    <section class="form-sheet-panel">
        <header class="form-sheet-header">
            <div>
                <h2 id="{{ $id }}-title">{{ $title }}</h2>
                @if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif
            </div>
            <button class="form-sheet-close material-symbols-outlined" type="button" data-sheet-close="{{ $id }}" aria-label="Fermer" title="Fermer">close</button>
        </header>
        <div class="form-sheet-body">{{ $slot }}</div>
        @isset($footer)<footer class="form-sheet-footer">{{ $footer }}</footer>@endisset
    </section>
</dialog>
