{{-- Niveau 2 — Étapes 1 et 2 : le projet est enregistré (brouillon) à la fin de l'étape 2. --}}
@php
    $stepNumber = $step === 'donor' ? 2 : 1;
    $stepSubtitle = $step === 'donor' ? 'bailleur, code et intitulé.' : 'définissez le type et le cadre du projet.';
    $value = fn (string $field, $default = null) => old($field, $project ? ($project->{$field} ?? $default) : $default);
    $type = $value('type', 'donor_project');
    $missionId = $value('mission_id', $mission?->id);
    $currentMission = $missions->firstWhere('id', $missionId) ?? $mission;
    $donorId = old('donor_id', $project?->donors->first()?->id);
    $fieldError = fn (string $field) => $errors->has($field) ? 'invalid' : '';
    // Après une erreur de validation, rouvrir l'étape qui contient le champ fautif.
    if ($errors->hasAny(['donor_id', 'code', 'name', 'starts_on', 'ends_on'])) { $step = 'donor'; $stepNumber = 2; $stepSubtitle = 'bailleur, code et intitulé.'; }
    elseif ($errors->hasAny(['type', 'mission_id', 'implementing_partner'])) { $step = 'identity'; $stepNumber = 1; $stepSubtitle = 'définissez le type et le cadre du projet.'; }
@endphp
@extends('projects.wizard.layout')
@section('wizard')
<div class="wz-grid">
    <form id="wizard-form" method="post" action="{{ $project ? route('projects.wizard.identity.update', $project) : route('projects.wizard.store') }}" novalidate data-wizard-identity data-step="{{ $step }}">
        @csrf
        @if($project) @method('PUT') @endif
        <input type="hidden" name="_step" value="{{ $step }}" data-step-input>

        <section class="wz-card" data-pane="identity" @if($step !== 'identity') hidden @endif>
            <h2>Type</h2>
            <p class="wz-sub">Un programme national du Ministère de la Santé se crée comme un projet.</p>
            <div class="wz-types" role="radiogroup" aria-label="Type de projet">
                <label class="wz-type"><input type="radio" name="type" value="donor_project" @checked($type === 'donor_project') data-recap-source="type" data-recap-text="Projet bailleur"><span><strong>Projet bailleur</strong><span>Financé par un bailleur (ex. : GFFO5, FH4). Un bailleur par projet.</span></span></label>
                <label class="wz-type"><input type="radio" name="type" value="national_program" @checked($type === 'national_program') data-recap-source="type" data-recap-text="Programme national"><span><strong>Programme national</strong><span>Programme du Ministère de la Santé (ex. : PNLT, PNLP). Bailleur facultatif.</span></span></label>
            </div>
            @error('type')<span class="wz-error">{{ $message }}</span>@enderror
            <div class="wz-fields">
                <label class="wz-field {{ $fieldError('mission_id') }}">Mission (pays) *
                    <select name="mission_id" required data-mission-select data-recap-source="mission">
                        @foreach($missions as $item)
                            <option value="{{ $item->id }}" data-coordination="{{ $item->name }}" data-organization="{{ $item->organization_id }}" data-organization-name="{{ $item->organization?->name }}" @selected($missionId === $item->id)>{{ $item->country?->name }}{{ $missions->where('country_id', $item->country_id)->count() > 1 ? ' · '.$item->name : '' }}</option>
                        @endforeach
                    </select>
                    @error('mission_id')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
                <label class="wz-field {{ $fieldError('implementing_partner') }}">Organisation ou programme *
                    <input name="implementing_partner" value="{{ $value('implementing_partner', $currentMission?->organization?->name) }}" required maxlength="190" data-recap-source="organization">
                    @error('implementing_partner')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
                <label class="wz-field wide">Coordination
                    <input value="{{ $currentMission?->name }} (votre coordination)" readonly data-coordination-label tabindex="-1">
                </label>
            </div>
            <div class="wz-foot">
                <a class="wz-btn" href="{{ route('modules.missions') }}">Annuler</a>
                <div class="wz-end"><span>Les champs marqués * sont obligatoires</span><button class="wz-btn primary" type="button" data-go="donor">Suivant : bailleur et projet</button></div>
            </div>
        </section>

        <section class="wz-card" data-pane="donor" @if($step !== 'donor') hidden @endif>
            <h2>Bailleur et projet</h2>
            <p class="wz-sub">Un projet est rattaché à un seul bailleur.</p>
            <div class="wz-fields">
                <div class="wz-field {{ $fieldError('donor_id') }}">
                    <label for="wizard-donor"><span data-donor-label>{{ $type === 'national_program' ? 'Bailleur (facultatif)' : 'Bailleur *' }}</span></label>
                    <select id="wizard-donor" name="donor_id" data-donor-select data-recap-source="donor">
                        <option value="">{{ $type === 'national_program' ? 'Aucun ('.\App\Models\Project::NATIONAL_PROGRAM_DEFAULT_DONOR.')' : 'Sélectionner un bailleur' }}</option>
                        @foreach($donors as $donor)
                            <option value="{{ $donor->id }}" data-organization="{{ $donor->organization_id }}" @selected($donorId === $donor->id)>{{ $donor->name }}</option>
                        @endforeach
                    </select>
                    @error('donor_id')<span class="wz-error">{{ $message }}</span>@enderror
                    @if($canAddDonor)<p style="margin:8px 0 0"><button class="wz-link" type="button" data-open-dialog="donor-dialog">+ Ajouter un bailleur</button></p>@endif
                </div>
                <label class="wz-field {{ $fieldError('code') }}">Code bailleur / programme MoH *
                    <input name="code" value="{{ $value('code') }}" required maxlength="50" placeholder="ex. GFFO5, PNLT" data-recap-source="code">
                    @error('code')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
                <label class="wz-field wide {{ $fieldError('name') }}">Intitulé du projet *
                    <input name="name" value="{{ $value('name') }}" required maxlength="180" data-recap-source="name">
                    @error('name')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
                <label class="wz-field {{ $fieldError('starts_on') }}">Date de début
                    <input type="date" name="starts_on" value="{{ old('starts_on', $project?->starts_on?->format('Y-m-d')) }}">
                    @error('starts_on')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
                <label class="wz-field {{ $fieldError('ends_on') }}">Date de fin
                    <input type="date" name="ends_on" value="{{ old('ends_on', $project?->ends_on?->format('Y-m-d')) }}">
                    @error('ends_on')<span class="wz-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="wz-foot">
                <button class="wz-btn" type="button" data-go="identity">Précédent</button>
                <div class="wz-end"><span>Les champs marqués * sont obligatoires</span><button class="wz-btn primary" type="submit" name="intent" value="next">Suivant : Liste Standard</button></div>
            </div>
        </section>
    </form>

    <aside class="wz-recap" aria-label="Récapitulatif">
        <h2>Récapitulatif</h2>
        <dl>
            <dt>Type</dt><dd data-recap="type">{{ \App\Models\Project::TYPES[$type] ?? '—' }}</dd>
            <dt>Mission</dt><dd data-recap="mission">{{ $currentMission?->country?->name ?? '—' }}</dd>
            <dt>Organisation</dt><dd data-recap="organization">{{ $value('implementing_partner', $currentMission?->organization?->name) ?: '—' }}</dd>
            <dt>Bailleur</dt><dd data-recap="donor">{{ $donors->firstWhere('id', $donorId)?->name ?? '—' }}</dd>
            <dt>Code</dt><dd data-recap="code">{{ $value('code') ?: '—' }}</dd>
            <dt>Intitulé</dt><dd data-recap="name" title="{{ $value('name') }}">{{ $value('name') ?: '—' }}</dd>
        </dl>
    </aside>
</div>

@if($canAddDonor)
<dialog class="wz-dialog" id="donor-dialog" aria-labelledby="donor-dialog-title">
    <form method="dialog" data-donor-form data-url="{{ route('projects.wizard.donors.store') }}">
        <h2 id="donor-dialog-title">Ajouter un bailleur</h2>
        <p>Le bailleur est ajouté au référentiel (Référentiels &gt; Bailleurs) et sélectionné pour ce projet.</p>
        <label class="wz-field">Nom du bailleur *<input name="donor_name" required maxlength="180" autocomplete="off"></label>
        <label class="wz-field">Sigle *<input name="donor_code" required maxlength="50" autocomplete="off" placeholder="ex. GF"></label>
        <span class="wz-error" data-donor-error role="alert"></span>
        <footer><button class="wz-btn" type="button" data-close-dialog>Annuler</button><button class="wz-btn primary" type="submit">Ajouter</button></footer>
    </form>
</dialog>
@endif
@endsection

@push('scripts')<script>
(() => {
    const form = document.querySelector('[data-wizard-identity]');
    if (!form) return;
    const subtitles = {identity: 'définissez le type et le cadre du projet.', donor: 'bailleur, code et intitulé.'};
    const panes = form.querySelectorAll('[data-pane]');
    const recap = (key, text) => { const node = document.querySelector(`[data-recap="${key}"]`); if (node) { node.textContent = text || '—'; node.title = text || ''; } };

    const show = (step) => {
        panes.forEach((pane) => { pane.hidden = pane.dataset.pane !== step; });
        form.querySelector('[data-step-input]').value = step;
        const number = step === 'donor' ? 2 : 1;
        document.querySelector('.wz-head p').textContent = `Étape ${number} sur 4 : ${subtitles[step]}`;
        document.querySelectorAll('[data-step-item]').forEach((item) => {
            const n = Number(item.dataset.stepItem);
            item.classList.toggle('current', n === number);
            item.classList.toggle('done', n < number);
            item.querySelector('.wz-num').textContent = n < number ? '✓' : n;
        });
        const progress = document.querySelector('.wz-progress');
        if (progress) { progress.querySelector('strong').textContent = `Étape ${number} sur 4`; progress.querySelector('i').style.width = `${number * 25}%`; }
    };
    form.querySelectorAll('[data-go]').forEach((button) => button.addEventListener('click', () => {
        const target = button.dataset.go;
        if (target === 'donor') {
            const invalid = [...form.querySelector('[data-pane="identity"]').querySelectorAll('input[required],select[required]')].find((field) => !field.checkValidity());
            if (invalid) { invalid.reportValidity(); return; }
        }
        show(target);
        form.querySelector(`[data-pane="${target}"] :is(input,select)`)?.focus();
    }));

    // Projet bailleur : bailleur obligatoire ; programme national : facultatif.
    const donorSelect = form.querySelector('[data-donor-select]');
    const syncType = () => {
        const national = form.querySelector('input[name="type"]:checked')?.value === 'national_program';
        donorSelect.required = !national;
        form.querySelector('[data-donor-label]').textContent = national ? 'Bailleur (facultatif)' : 'Bailleur *';
        donorSelect.options[0].textContent = national ? 'Aucun ({{ \App\Models\Project::NATIONAL_PROGRAM_DEFAULT_DONOR }})' : 'Sélectionner un bailleur';
    };
    // Bailleurs de l'organisation de la mission choisie.
    const missionSelect = form.querySelector('[data-mission-select]');
    const syncMission = () => {
        const option = missionSelect.selectedOptions[0];
        form.querySelector('[data-coordination-label]').value = `${option.dataset.coordination} (votre coordination)`;
        [...donorSelect.options].slice(1).forEach((donor) => { donor.hidden = donor.dataset.organization !== option.dataset.organization; });
        if (donorSelect.selectedOptions[0]?.hidden) donorSelect.value = '';
    };

    form.addEventListener('input', (event) => {
        const field = event.target.closest('[data-recap-source]');
        if (!field) return;
        const key = field.dataset.recapSource;
        if (field.type === 'radio') recap(key, field.dataset.recapText);
        else if (field.tagName === 'SELECT') recap(key, field.value ? field.selectedOptions[0].textContent.trim() : '');
        else recap(key, field.value.trim());
    });
    form.addEventListener('change', (event) => {
        if (event.target.name === 'type') { syncType(); form.dispatchEvent(new Event('input')); }
        if (event.target === missionSelect) syncMission();
        if (event.target.matches('[data-recap-source]')) event.target.dispatchEvent(new Event('input', {bubbles: true}));
    });
    syncType();
    syncMission();

    // « + Ajouter un bailleur ».
    const dialog = document.getElementById('donor-dialog');
    if (!dialog) return;
    document.querySelector('[data-open-dialog="donor-dialog"]').addEventListener('click', () => dialog.showModal());
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
    const donorForm = dialog.querySelector('[data-donor-form]');
    donorForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const error = donorForm.querySelector('[data-donor-error]');
        error.textContent = '';
        const response = await fetch(donorForm.dataset.url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value},
            body: JSON.stringify({mission_id: missionSelect.value, name: donorForm.donor_name.value.trim(), code: donorForm.donor_code.value.trim()}),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) { error.textContent = Object.values(payload.errors || {})[0]?.[0] || payload.message || 'Le bailleur n’a pas pu être ajouté.'; return; }
        const option = new Option(payload.name, payload.id, true, true);
        option.dataset.organization = missionSelect.selectedOptions[0].dataset.organization;
        donorSelect.add(option);
        donorSelect.dispatchEvent(new Event('change', {bubbles: true}));
        donorForm.reset();
        dialog.close();
    });
})();
</script>@endpush
