@php
    $status = old('status', $mission?->is_active === false ? 'inactive' : 'active');
    $selectedCountry = old(
        'country_id',
        $mission?->country_id
            ?? $countries->firstWhere('iso2', $organization->country_code)?->id
    );
@endphp
<section class="wizard-step-content">
    <header class="step-content-header">
        <div>
            <span class="eyebrow">Étape 2</span>
            <h2>Mission</h2>
            <p>Définissez la mission nationale portée par {{ $organization->name }}.</p>
        </div>
        @if(($stepStates['2'] ?? null)==='valid')
            <span class="status-badge success">✓ Étape validée</span>
        @elseif(($stepStates['2'] ?? null)==='needs_correction')
            <span class="status-badge error">À corriger</span>
        @else
            <span class="status-badge">En cours</span>
        @endif
    </header>
    <div class="step-toolbar">
        <div><strong>{{ $missions->count() }} mission(s)</strong><small>Gérez toutes les missions de l’organisation.</small></div>
        <x-app-button icon="add" :href="route('configuration.mission', ['new' => 1, '_flow'=>$workflow->workflow_id])">Ajouter une mission</x-app-button>
    </div>

    @if($missions->isNotEmpty())
        <div class="entity-list">
            @foreach($missions as $item)
                <article class="{{ $mission?->is($item) ? 'selected' : '' }}">
                    <div><strong>{{ $item->name }}</strong><small>{{ $item->code }} · {{ $item->country?->name }} · {{ $item->is_active ? 'Active' : 'Inactive' }}</small></div>
                    <x-app-button variant="outline" icon="edit" :href="route('configuration.mission', ['edit' => $item->id, '_flow'=>$workflow->workflow_id])">Modifier</x-app-button>
                    <form method="post" action="{{ route('configuration.mission.archive', ['mission'=>$item, '_flow'=>$workflow->workflow_id]) }}" data-confirm="Archiver cette mission ?">
                        @csrf @method('DELETE')
                        <x-app-button variant="danger-outline" icon="archive" type="submit">Archiver</x-app-button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif

    @if($archivedMissions->isNotEmpty())
        <details class="archived-entities">
            <summary>Missions archivées ({{ $archivedMissions->count() }})</summary>
            @foreach($archivedMissions as $item)
                <div><span><strong>{{ $item->name }}</strong><small>{{ $item->code }} · {{ $item->country?->name }}</small></span>
                    <form method="post" action="{{ route('configuration.mission.restore', ['mission'=>$item, '_flow'=>$workflow->workflow_id]) }}" data-confirm="Restaurer cette mission ?">@csrf<x-app-button variant="outline" icon="restore" type="submit">Restaurer</x-app-button></form>
                </div>
            @endforeach
        </details>
    @endif

    @if(session('success'))
        <div class="alert success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" role="alert">
            Le formulaire contient des erreurs. Vérifiez les champs signalés.
        </div>
    @endif
    @if($creatingMission)
        <dialog id="mission-create-sheet" class="configuration-form-sheet" aria-labelledby="mission-create-sheet-title">
            <section class="configuration-form-sheet-panel">
                <header><div><h2 id="mission-create-sheet-title">Ajouter une mission</h2><p>Créez une mission rattachée uniquement à {{ $organization->name }}.</p></div><button type="button" data-mission-sheet-close aria-label="Fermer">×</button></header>
                <div class="configuration-form-sheet-body">
    @endif
    <form class="organization-form" id="mission-form" method="post"
          action="{{ route('configuration.mission.save', ['_flow'=>$workflow->workflow_id]) }}"
          data-loading-form novalidate>
        @csrf
        @if($mission)<input type="hidden" name="mission_id" value="{{ $mission->id }}">@endif
        @if($creatingMission)<input type="hidden" name="create_new" value="1">@endif
        <div class="form-section">
            <h3>Rattachement</h3>
            <div class="form-grid two-columns">
                <label class="field">Organisation
                    <input value="{{ $organization->name }}" disabled>
                    <small>Organisation validée à l’étape précédente.</small>
                </label>
                <label class="field">Pays de la mission <b>*</b>
                    <select name="country_id" required>
                        <option value="">Sélectionner un pays</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected($selectedCountry === $country->id)>
                                {{ $country->name }} ({{ $country->iso2 }})
                            </option>
                        @endforeach
                    </select>
                    @error('country_id')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3>Identité de la mission</h3>
            <div class="form-grid two-columns">
                <label class="field">Nom de la mission <b>*</b>
                    <input name="name" value="{{ old('name', $mission?->name) }}"
                           maxlength="160" placeholder="Ex. Mission Santé Cameroun" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">Code mission <b>*</b>
                    <input name="code" value="{{ old('code', $mission?->code) }}"
                           maxlength="40" placeholder="Ex. MSC-CM" required>
                    @error('code')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3>Période et statut</h3>
            <div class="form-grid two-columns">
                <label class="field">Date de début
                    <input type="date" name="starts_on"
                           value="{{ old('starts_on', $mission?->starts_on?->format('Y-m-d')) }}">
                    @error('starts_on')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">Date de fin
                    <input type="date" name="ends_on"
                           value="{{ old('ends_on', $mission?->ends_on?->format('Y-m-d')) }}">
                    @error('ends_on')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="field">Statut <b>*</b>
                    <select name="status" required>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>
        </div>

        <div class="configuration-help">
            <strong>Information</strong>
            <p>Cette mission servira de périmètre aux projets configurés à l’étape suivante.</p>
        </div>

        <footer class="wizard-footer">
            <a class="button secondary" href="{{ route('configuration.organization', ['_flow'=>$workflow->workflow_id]) }}">← Précédent</a>
            <div class="footer-primary-actions">
                <button class="button outline" type="submit" name="action" value="save">
                    Enregistrer
                </button>
                <button class="button primary" type="submit" name="action" value="continue">
                    Enregistrer et continuer →
                </button>
            </div>
        </footer>
    </form>
    @if($creatingMission)
                </div>
            </section>
        </dialog>
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded',()=>{
            const sheet=document.getElementById('mission-create-sheet');
            if(sheet&&!sheet.open)sheet.showModal();
            document.querySelector('[data-mission-sheet-close]')?.addEventListener('click',()=>sheet?.close());
            sheet?.addEventListener('cancel',event=>{event.preventDefault();sheet.close()});
        });
        </script>
        @endpush
    @endif
</section>
