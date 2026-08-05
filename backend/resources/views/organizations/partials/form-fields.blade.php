<div class="sheet-grid">
    <div class="field"><label>Code unique</label><input name="code" value="{{ old('code', $organization?->code) }}" placeholder="Ex. ONG_CM" required maxlength="80"></div>
    <div class="field"><label>Nom usuel</label><input name="name" value="{{ old('name', $organization?->name) }}" placeholder="Nom de l’organisation" required maxlength="180"></div>
    <div class="field full"><label>Raison sociale</label><input name="legal_name" value="{{ old('legal_name', $organization?->legal_name) }}" placeholder="Dénomination juridique complète"></div>
    <div class="field"><label>Adresse e-mail</label><input name="email" type="email" value="{{ old('email', $organization?->email) }}" placeholder="contact@organisation.org"></div>
    <div class="field"><label>Téléphone</label><input name="phone" value="{{ old('phone', $organization?->phone) }}" placeholder="+237 …"></div>
    <div class="field"><label>Code pays</label><input name="country_code" maxlength="2" value="{{ old('country_code', $organization?->country_code) }}" placeholder="CM"></div>
    <div class="field full"><label>Adresse</label><textarea name="address" placeholder="Adresse complète">{{ old('address', $organization?->address) }}</textarea></div>
    <label class="switch-field full"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization?->is_active ?? true))><span><strong>Organisation active</strong><br><small class="meta">Autorise son utilisation dans les opérations courantes.</small></span></label>
</div>
