@props(['id', 'title', 'description' => null, 'width' => '620px'])
<dialog id="{{ $id }}" class="form-sheet" style="--sheet-width:{{ $width }}" aria-labelledby="{{ $id }}-title" @if($description) aria-describedby="{{ $id }}-description" @endif>
    <section class="form-sheet-panel">
        <div class="form-sheet-handle" aria-hidden="true"></div>
        <header class="form-sheet-header">
            <div>
                <h2 id="{{ $id }}-title">{{ $title }}</h2>
                @if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif
            </div>
            <button class="form-sheet-close material-symbols-outlined" type="button" data-sheet-close="{{ $id }}" aria-label="Fermer" title="Fermer">close</button>
        </header>
        <div class="form-sheet-body">{{ $slot }}</div>
    </section>
</dialog>
