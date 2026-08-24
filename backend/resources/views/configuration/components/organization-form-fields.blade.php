@php
    $useOld = old('_form_mode') === $mode || (old('_form_mode') === null && $mode === 'createOrganization' && session()->getOldInput() !== []);
    $value = fn(string $key, mixed $fallback = null) => $useOld ? old($key, $fallback) : data_get($item, $key, $fallback);
    $selectedCountries = collect($useOld ? old('country_ids', []) : ($item?->countries?->pluck('id')->all() ?? []))->map(fn($id)=>(string)$id)->all();
    $accessType = $value('geographic_access_type', 'single_country');
    $status = $useOld ? old('status', 'active') : ($item?->is_active === false ? 'inactive' : 'active');
    $creating = $mode === 'createOrganization';
@endphp

<div class="organization-form-sections" data-geographic-form>
    <section class="organization-form-section">
        <header><span>A</span><div><h3>Organisation</h3><p>Identité institutionnelle et coordonnées.</p></div></header>
        <div class="organization-form-grid">
            <div><label>Nom de l’organisation *</label><input name="name" value="{{ $value('name') }}" maxlength="160" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
            <div><label>Code organisation *</label><input name="code" value="{{ $value('code') }}" maxlength="40" required></div>
            <div><label>Statut *</label><select name="status" required><option value="active" @selected($status==='active')>Actif</option><option value="inactive" @selected($status==='inactive')>Inactif</option></select></div>
            <div><label>Email institutionnel</label><input name="email" type="email" value="{{ $value('email') }}" maxlength="190"></div>
            <div><label>Téléphone</label><input name="phone" type="tel" value="{{ $value('phone') }}" maxlength="40"></div>
            <div><label>Langue principale *</label><select name="default_language" required>@foreach($languages as $code=>$label)<option value="{{ $code }}" @selected($value('default_language','fr')===$code)>{{ $label }}</option>@endforeach</select><small class="field-hint">Cette liste est extensible ; la disponibilité d’une langue ne garantit pas encore la traduction complète de l’interface.</small></div>
            <div><label>Logo</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp"></div>
        </div>
    </section>

    <section class="organization-form-section">
        <header><span>B</span><div><h3>Périmètre géographique</h3><p>Sélection normalisée dans le référentiel des pays.</p></div></header>
        <div class="organization-form-grid"><div><label>Type d’accès *</label><select name="geographic_access_type" data-access-type required><option value="single_country" @selected($accessType==='single_country')>Unipays</option><option value="multi_country" @selected($accessType==='multi_country')>Multipays</option></select></div></div>
        <div class="country-selector">
            <label data-country-label>{{ $accessType==='multi_country' ? 'Pays autorisés *' : 'Pays principal *' }}</label>
            <button class="country-picker-trigger" type="button" data-country-picker-trigger><span data-country-picker-label>Sélectionner {{ $accessType==='multi_country' ? 'les pays' : 'un pays' }}</span><span class="material-symbols-outlined">expand_more</span></button>
            <div class="country-picker" data-country-picker hidden>
              <input type="search" data-country-search placeholder="Rechercher un pays…" autocomplete="off">
              <div class="country-picker-options" data-country-options></div>
              <div class="country-picker-footer"><strong data-country-count>0 pays sélectionné</strong><button type="button" data-country-picker-close>Valider</button></div>
            </div>
            <select name="country_ids[]" data-country-select multiple class="country-native-select" tabindex="-1" aria-hidden="true">
                @foreach($countries as $country)<option value="{{ $country->id }}" @selected(in_array((string)$country->id,$selectedCountries,true))>{{ $country->name }} · {{ $country->iso2 }} · {{ $country->iso3 }}</option>@endforeach
            </select>
            <div class="country-chips" data-country-chips></div>
            @error('country_ids')<small class="field-error">{{ $message }}</small>@enderror
            @error('country_ids.*')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </section>

    @if($creating)
    <section class="organization-form-section">
        <header><span>C</span><div><h3>Administrateur principal</h3><p>Premier compte rattaché à l’organisation.</p></div></header>
        <div class="locked-role"><span class="material-symbols-outlined">lock</span><div><small>Rôle attribué automatiquement</small><strong>Admin Coordination</strong></div></div>
        <div class="organization-form-grid">
            <div><label>Prénom *</label><input name="admin_first_name" value="{{ old('admin_first_name') }}" maxlength="80" required></div>
            <div><label>Nom *</label><input name="admin_last_name" value="{{ old('admin_last_name') }}" maxlength="80" required></div>
            <div><label>Email de connexion *</label><input name="admin_email" type="email" value="{{ old('admin_email') }}" maxlength="190" required></div>
            <div><label>Téléphone</label><input name="admin_phone" type="tel" value="{{ old('admin_phone') }}" maxlength="40"></div>
            <div><label>Identifiant *</label><input name="admin_username" value="{{ old('admin_username') }}" maxlength="80" required></div>
            <div><label>Mode d’activation *</label><select name="activation_mode" data-activation-mode required><option value="temporary_password" @selected(old('activation_mode','temporary_password')==='temporary_password')>Mot de passe temporaire</option><option value="invitation" @selected(old('activation_mode')==='invitation')>Invitation</option></select></div>
            <div data-password-field><label>Mot de passe temporaire *</label><input name="admin_password" type="password" minlength="10"></div>
            <div data-password-field><label>Confirmation *</label><input name="admin_password_confirmation" type="password" minlength="10"></div>
            <div><label>Statut *</label><select name="admin_status" required><option value="active" @selected(old('admin_status','active')==='active')>Actif</option><option value="inactive" @selected(old('admin_status')==='inactive')>Inactif</option></select></div>
        </div>
    </section>
    @endif

    <section class="organization-form-section organization-summary-section">
        <header><span>D</span><div><h3>Résumé</h3><p>Vérifiez les informations essentielles avant validation.</p></div></header>
        <div class="organization-live-summary" data-organization-summary>
            <div><small>Organisation</small><strong data-summary-name>À renseigner</strong></div>
            <div><small>Type d’accès</small><strong data-summary-access>Unipays</strong></div>
            <div><small>Pays</small><strong data-summary-countries>À sélectionner</strong></div>
            <div><small>Langue</small><strong data-summary-language>Français</strong></div>
            @if($creating)<div><small>Administrateur principal</small><strong data-summary-admin>À renseigner</strong></div>@endif
        </div>
    </section>

    @if($errors->any())<div class="section-errors"><strong>Vérifiez les champs signalés :</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
</div>
