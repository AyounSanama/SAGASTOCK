@php
    $useOld = old('_form_mode') === $mode
        || (old('_form_mode') === null && $mode === 'createOrganization' && session()->getOldInput() !== []);
    $value = fn(string $key, mixed $fallback = null) => $useOld ? old($key) : data_get($item, $key, $fallback);
    $status = $useOld ? old('status', 'active') : ($item?->is_active === false ? 'inactive' : 'active');
@endphp
<div class="organization-form-grid">
    <div><label>Nom de l’organisation *</label><input name="name" value="{{ $value('name') }}" maxlength="160" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Type d’organisation *</label><select name="organization_type" required><option value="">Sélectionner</option>@foreach($organizationTypes as $key=>$label)<option value="{{ $key }}" @selected($value('organization_type')===$key)>{{ $label }}</option>@endforeach</select>@error('organization_type')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Code *</label><input name="code" value="{{ $value('code') }}" maxlength="40" placeholder="Ex. ONG-CM" required>@error('code')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Pays principal *</label><select name="country_code" required><option value="">Sélectionner un pays</option>@foreach($countries as $country)<option value="{{ $country->iso2 }}" @selected($value('country_code')===$country->iso2)>{{ $country->name }}</option>@endforeach</select>@error('country_code')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="full"><label>Logo</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">@if($item?->logo_path)<small>Un logo est actuellement enregistré. Choisissez un fichier uniquement pour le remplacer.</small>@endif @error('logo')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="full"><label>Adresse</label><textarea name="address" maxlength="1000">{{ $value('address') }}</textarea>@error('address')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Téléphone</label><input name="phone" type="tel" value="{{ $value('phone') }}" maxlength="40">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>E-mail</label><input name="email" type="email" value="{{ $value('email') }}" maxlength="190">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Langue par défaut *</label><select name="default_language" required><option value="fr" @selected($value('default_language','fr')==='fr')>Français</option><option value="en" @selected($value('default_language')==='en')>English</option></select></div>
    <div><label>Statut *</label><select name="status" required><option value="active" @selected($status==='active')>Actif</option><option value="inactive" @selected($status==='inactive')>Inactif</option></select></div>
    <div><label>Responsable *</label><input name="manager_name" value="{{ $value('manager_name') }}" maxlength="160" required>@error('manager_name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label>Fonction du responsable *</label><input name="manager_title" value="{{ $value('manager_title') }}" maxlength="160" required>@error('manager_title')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="full"><label>Description</label><textarea name="description" maxlength="3000">{{ $value('description') }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</div>
</div>
