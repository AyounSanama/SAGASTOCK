@php($withAdmin = $withAdmin ?? false)
<div class="sheet-grid">
    <div class="field"><label>Code unique</label><input name="code" value="{{ old('code', $organization?->code) }}" placeholder="Ex. ONG_CM" required maxlength="80"></div>
    <div class="field"><label>Nom usuel</label><input name="name" value="{{ old('name', $organization?->name) }}" placeholder="Nom de l'organisation" required maxlength="180"></div>
    <div class="field"><label>Type d'organisation</label><select name="organization_type" required><option value="">Sélectionner</option>@foreach(['ngo'=>'ONG','ministry'=>'Ministère de la Santé','national_program'=>'Programme national','united_nations'=>'Organisation des Nations unies','international_agency'=>'Agence internationale','other'=>'Autre'] as $value=>$label)<option value="{{ $value }}" @selected(old('organization_type', $organization?->organization_type)===$value)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label>Adresse e-mail</label><input name="email" type="email" value="{{ old('email', $organization?->email) }}" placeholder="contact@organisation.org"></div>
    <div class="field"><label>Téléphone</label><input name="phone" value="{{ old('phone', $organization?->phone) }}" placeholder="+237..."></div>
    @if($withAdmin)
        <div class="field"><label>Périmètre géographique</label><select name="geographic_access_type" id="geographic-access-type" required><option value="single_country" @selected(old('geographic_access_type')==='single_country')>Unipays</option><option value="multi_country" @selected(old('geographic_access_type')==='multi_country')>Multipays</option></select></div>
        <div class="field full"><label>Pays autorisé(s)</label><select name="country_ids[]" id="organization-countries" required>@foreach($countries as $country)<option value="{{ $country->id }}" @selected(in_array($country->id, old('country_ids', [])))>{{ $country->name }} ({{ $country->iso2 }})</option>@endforeach</select><small class="meta">Sélectionnez le pays autorisé pour cette organisation.</small></div>
    @else
        <div class="field"><label>Périmètre géographique</label><input value="{{ $organization?->geographic_access_type === 'multi_country' ? 'Multipays' : 'Unipays' }}" readonly><small class="meta">Le périmètre est défini lors de la création afin de préserver les coordinations existantes.</small></div>
        <div class="field full"><label>Pays autorisé(s)</label><input value="{{ $organization?->countries?->pluck('name')->join(', ') ?: ($organization?->country_code ?: 'Non renseigné') }}" readonly></div>
    @endif
    <div class="field full"><label>Adresse</label><textarea name="address" placeholder="Adresse complète">{{ old('address', $organization?->address) }}</textarea></div>
    <label class="switch-field full"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization?->is_active ?? true))><span><strong>Organisation active</strong><br><small class="meta">Autorise son utilisation dans les opérations courantes.</small></span></label>
    @if($withAdmin)
        <div class="field full"><h3>Administrateur de coordination principal</h3></div>
        <div class="field"><label>Prénom</label><input name="admin_first_name" value="{{ old('admin_first_name') }}" required></div>
        <div class="field"><label>Nom</label><input name="admin_last_name" value="{{ old('admin_last_name') }}" required></div>
        <div class="field"><label>Adresse e-mail</label><input type="email" name="admin_email" value="{{ old('admin_email') }}" required></div>
        <div class="field"><label>Téléphone</label><input name="admin_phone" value="{{ old('admin_phone') }}"></div>
        <div class="field full"><label>Identifiant</label><input name="admin_username" value="{{ old('admin_username') }}" required></div>
    @endif
</div>
@if($withAdmin)
<script>document.addEventListener('DOMContentLoaded',()=>{const t=document.getElementById('geographic-access-type'),c=document.getElementById('organization-countries');const sync=()=>{c.multiple=t.value==='multi_country';c.size=c.multiple?Math.min(7,Math.max(3,c.options.length)):1};t?.addEventListener('change',sync);sync()})</script>
@endif
