@php
    $keys = array_flip(\App\Http\Controllers\Web\ConfigurationWizardController::STEPS);
    $stepKey = $keys[$activeStep];
    $titles = [
        3=>'Projets',4=>'Bailleurs',5=>'Programmes',6=>'Modules',7=>'Fonctionnalités',
        8=>'Listes standards',9=>'Formations sanitaires',10=>'Sites de dispensation',
        11=>'Utilisateurs et accès',12=>'Résumé et validation',
    ];
    $previous = $activeStep === 3
        ? route('configuration.mission', ['_flow'=>$workflow->workflow_id])
        : route('configuration.step', ['step'=>$keys[$activeStep - 1], '_flow'=>$workflow->workflow_id]);
    $currentEntity = match($activeStep) {
        3 => $project, 4 => $donor, 5 => $program, 8 => $standardList,
        9 => $facility, 10 => $site, 11 => $configuredUser, default => null,
    };
    $archivedEntity = $archivedEntities->get($activeStep);
@endphp
<section class="wizard-step-content">
    <header class="step-content-header">
        <div><span class="eyebrow">Étape {{ $activeStep }}</span><h2>{{ $titles[$activeStep] }}</h2>
            <p>Renseignez et validez les informations obligatoires de cette étape.</p></div>
        @if(($stepStates[(string)$activeStep] ?? null)==='valid')<span class="status-badge success">✓ Étape validée</span>
        @elseif(($stepStates[(string)$activeStep] ?? null)==='needs_correction')<span class="status-badge error">À corriger</span>
        @else<span class="status-badge">En cours</span>@endif
    </header>
    @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error" role="alert">Le formulaire contient des erreurs. Vérifiez les champs signalés.</div>@endif
    @if($currentEntity)
        <div class="entity-actions">
            <div><strong>{{ $currentEntity->name }}</strong><small>Élément actuellement configuré</small></div>
            <a class="button outline" href="#configuration-form">✎ Modifier</a>
            <form method="post" action="{{ route('configuration.step.archive', ['step'=>$stepKey,'_flow'=>$workflow->workflow_id]) }}" data-confirm="Archiver cet élément ? Les étapes dépendantes devront être validées de nouveau.">
                @csrf @method('DELETE')
                <button class="button danger-outline" type="submit">⌑ Archiver</button>
            </form>
        </div>
    @elseif($archivedEntity)
        <div class="entity-actions archived">
            <div><strong>{{ $archivedEntity->name }}</strong><small>Élément archivé</small></div>
            <form method="post" action="{{ route('configuration.step.restore', ['step'=>$stepKey,'id'=>$archivedEntity->getKey(),'_flow'=>$workflow->workflow_id]) }}" data-confirm="Restaurer cet élément ?">
                @csrf
                <button class="button success-outline" type="submit">↻ Restaurer</button>
            </form>
        </div>
    @endif

    <form class="organization-form" id="configuration-form" method="post" action="{{ route('configuration.step.save', ['step'=>$stepKey,'_flow'=>$workflow->workflow_id]) }}" data-loading-form novalidate>
        @csrf
        <div class="form-section">
        @switch($activeStep)
            @case(3)
                <h3>Projet principal</h3><div class="form-grid two-columns">
                    <label class="field">Mission <b>*</b><select name="mission_id" required><option value="">Sélectionner</option>@foreach($missions as $item)<option value="{{ $item->id }}" @selected(old('mission_id',$project?->mission_id)===$item->id)>{{ $item->name }}</option>@endforeach</select>@error('mission_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field">Statut <b>*</b><select name="status" required><option value="active">Actif</option></select></label>
                    <label class="field">Nom du projet <b>*</b><input name="name" value="{{ old('name',$project?->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field">Code projet <b>*</b><input name="code" value="{{ old('code',$project?->code) }}" required>@error('code')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field">Date de début <b>*</b><input type="date" name="starts_on" value="{{ old('starts_on',$project?->starts_on?->format('Y-m-d')) }}" required>@error('starts_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field">Date de fin <b>*</b><input type="date" name="ends_on" value="{{ old('ends_on',$project?->ends_on?->format('Y-m-d')) }}" required>@error('ends_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field full">Description<textarea name="description">{{ old('description',$project?->description) }}</textarea></label>
                </div>
                @break
            @case(4)
                <h3>Bailleur et financement</h3><div class="form-grid two-columns">
                    <label class="field">Nom du bailleur <b>*</b><input name="name" value="{{ old('name',$donor?->name) }}" required></label>
                    <label class="field">Code bailleur <b>*</b><input name="code" value="{{ old('code',$donor?->code) }}" required></label>
                    <label class="field">E-mail<input type="email" name="email" value="{{ old('email',$donor?->email) }}"></label>
                    <label class="field">Téléphone<input name="phone" value="{{ old('phone',$donor?->phone) }}"></label>
                    <label class="field">Montant du financement<input type="number" min="0" step="0.01" name="funding_amount" value="{{ old('funding_amount',$project?->donors?->first()?->pivot?->funding_amount) }}"></label>
                    <label class="field">Devise<select name="currency"><option value="">Sélectionner</option>@foreach(['XAF'=>'FCFA (XAF)','EUR'=>'Euro (EUR)','USD'=>'Dollar américain (USD)','GBP'=>'Livre sterling (GBP)'] as $v=>$l)<option value="{{ $v }}" @selected(old('currency')===$v)>{{ $l }}</option>@endforeach</select></label>
                    <label class="field full">Référence de convention<input name="agreement_reference" value="{{ old('agreement_reference') }}"></label>
                </div>
                @break
            @case(5)
                <h3>Programme associé</h3><div class="form-grid two-columns">
                    <label class="field">Bailleur<select name="donor_id"><option value="">Aucun bailleur</option>@foreach($donors as $item)<option value="{{ $item->id }}" @selected(old('donor_id',$program?->donor_id)===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field">Nom du programme <b>*</b><input name="name" value="{{ old('name',$program?->name) }}" required></label>
                    <label class="field">Code programme <b>*</b><input name="code" value="{{ old('code',$program?->code) }}" required></label>
                    <label class="field">Date de début<input type="date" name="starts_on" value="{{ old('starts_on',$program?->starts_on?->format('Y-m-d')) }}"></label>
                    <label class="field">Date de fin<input type="date" name="ends_on" value="{{ old('ends_on',$program?->ends_on?->format('Y-m-d')) }}"></label>
                    <label class="field full">Description<textarea name="description">{{ old('description',$program?->description) }}</textarea></label>
                </div>
                @break
            @case(6)
            @case(7)
                <h3>{{ $activeStep===6 ? 'Modules à activer' : 'Fonctionnalités à activer' }}</h3>
                <div class="choice-grid">@foreach($activeStep===6 ? $modules : $features as $code=>$label)@php($stored=$activeStep===6?$code:'feature:'.$code)<label class="choice-card"><input type="checkbox" name="choices[]" value="{{ $code }}" @checked(old('choices') ? in_array($code,old('choices')) : $enabledModules->contains($stored))><span><strong>{{ $label }}</strong><small>Activer pour l’organisation</small></span></label>@endforeach</div>
                @error('choices')<small class="field-error">{{ $message }}</small>@enderror
                @break
            @case(8)
                <h3>Liste standard initiale</h3><div class="form-grid two-columns">
                    <label class="field">Projet <select disabled><option>{{ $project?->name }}</option></select></label>
                    <label class="field">Nom de la liste <b>*</b><input name="name" value="{{ old('name',$standardList?->name) }}" required></label>
                    <label class="field">Code liste <b>*</b><input name="code" value="{{ old('code',$standardList?->code) }}" required></label>
                    <label class="field">Produits hors liste<select name="allow_outside_list"><option value="0" @selected(!old('allow_outside_list',$standardList?->allow_outside_list))>Interdits</option><option value="1" @selected(old('allow_outside_list',$standardList?->allow_outside_list))>Autorisés avec contrôle</option></select></label>
                    <label class="field full">Description<textarea name="description">{{ old('description',$standardList?->description) }}</textarea></label>
                </div>
                @break
            @case(9)
                <h3>Formation sanitaire</h3><div class="form-grid two-columns">
                    <label class="field">Mission <b>*</b><select name="mission_id" required>@foreach($missions as $item)<option value="{{ $item->id }}" @selected(old('mission_id',$facility?->mission_id)===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field">Projet<select disabled><option>{{ $project?->name }}</option></select></label>
                    <label class="field">Nom <b>*</b><input name="name" value="{{ old('name',$facility?->name) }}" required></label>
                    <label class="field">Code <b>*</b><input name="code" value="{{ old('code',$facility?->code) }}" required></label>
                    <label class="field">Type <b>*</b><select name="facility_type" required>@foreach(['hospital'=>'Hôpital','health_center'=>'Centre de santé','clinic'=>'Clinique','warehouse'=>'Dépôt','community'=>'Structure communautaire','other'=>'Autre'] as $v=>$l)<option value="{{ $v }}" @selected(old('facility_type',$facility?->facility_type)===$v)>{{ $l }}</option>@endforeach</select></label>
                    <label class="field">Niveau de soins <b>*</b><select name="care_level" required>@foreach(['primary'=>'Primaire','secondary'=>'Secondaire','tertiary'=>'Tertiaire','national'=>'National'] as $v=>$l)<option value="{{ $v }}" @selected(old('care_level',$facility?->care_level)===$v)>{{ $l }}</option>@endforeach</select></label>
                    <label class="field">E-mail<input type="email" name="email" value="{{ old('email',$facility?->email) }}"></label><label class="field">Téléphone<input name="phone" value="{{ old('phone',$facility?->phone) }}"></label>
                    <label class="field full">Adresse<textarea name="address">{{ old('address',$facility?->address) }}</textarea></label>
                </div>
                @break
            @case(10)
                <h3>Site de stockage ou de dispensation</h3><div class="form-grid two-columns">
                    <label class="field">Formation sanitaire <b>*</b><select name="health_facility_id" required>@foreach($facilities as $item)<option value="{{ $item->id }}" @selected(old('health_facility_id',$site?->health_facility_id)===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field">Type de site <b>*</b><select name="site_type" required>@foreach(['stock'=>'Stockage','dispensing'=>'Dispensation','stock_and_dispensing'=>'Stockage et dispensation','quarantine'=>'Quarantaine','other'=>'Autre'] as $v=>$l)<option value="{{ $v }}" @selected(old('site_type',$site?->site_type)===$v)>{{ $l }}</option>@endforeach</select></label>
                    <label class="field">Nom <b>*</b><input name="name" value="{{ old('name',$site?->name) }}" required></label>
                    <label class="field">Code <b>*</b><input name="code" value="{{ old('code',$site?->code) }}" required></label>
                    <label class="field full">Localisation<input name="location" value="{{ old('location',$site?->location) }}"></label>
                </div>
                @break
            @case(11)
                <h3>Premier administrateur inférieur</h3>
                @if($configuredUser)<div class="alert success">Compte configuré : {{ $configuredUser->name }} — {{ $configuredUser->email }}</div>@else
                <div class="form-grid two-columns">
                    <label class="field">Rôle / fonction <b>*</b><select name="role_id" required><option value="">Sélectionner</option>@foreach($roles as $item)<option value="{{ $item->id }}" @selected(old('role_id')==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field">Nom complet <b>*</b><input name="name" value="{{ old('name') }}" required></label>
                    <label class="field">E-mail <b>*</b><input type="email" name="email" value="{{ old('email') }}" required></label>
                    <label class="field">Téléphone<input name="phone" value="{{ old('phone') }}"></label>
                    <label class="field">Mot de passe provisoire <b>*</b><input type="password" name="password" required></label>
                    <label class="field">Confirmation <b>*</b><input type="password" name="password_confirmation" required></label>
                </div>@endif
                @break
            @case(12)
                <h3>Contrôle final</h3>
                <div class="summary-grid">
                    @foreach([1=>'Organisation / ONG',2=>'Mission',3=>'Projets',4=>'Bailleurs',5=>'Programmes',6=>'Modules',7=>'Fonctionnalités',8=>'Listes standards',9=>'Formations sanitaires',10=>'Sites de dispensation',11=>'Utilisateurs et accès'] as $n=>$label)
                        @php($state=$stepStates[(string)$n] ?? 'not_started')
                        <div class="summary-item"><span>{{ $label }}</span><b class="{{ $state==='valid'?'ok':'missing' }}">{{ match($state){'valid'=>'✓ Validé','needs_correction'=>'À corriger','in_progress'=>'En cours',default=>'À compléter'} }}</b></div>
                    @endforeach
                </div>
                <label class="choice-card confirmation"><input type="checkbox" name="confirmation" value="1" required><span><strong>Je confirme l’exactitude de cette configuration</strong><small>La validation rendra la configuration opérationnelle.</small></span></label>
                @break
        @endswitch
        </div>
        <footer class="wizard-footer">
            <a class="button secondary" href="{{ $previous }}">← Précédent</a>
            <button class="button primary" type="submit">{{ $activeStep===12 ? 'Terminer la configuration' : 'Enregistrer et continuer →' }}</button>
        </footer>
    </form>
</section>
