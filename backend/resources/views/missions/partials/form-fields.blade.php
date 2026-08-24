@php($editing = isset($mission) && $mission)
<div class="app-form-grid">
    <label>Nom *<input name="name" value="{{ old('name',$editing ? $mission->name : '') }}" required maxlength="160"></label>
    <label>Code *<input name="code" value="{{ old('code',$editing ? $mission->code : '') }}" required maxlength="40"></label>
    <label class="full">Pays autorisé *<select name="country_id" required><option value="">Sélectionner</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected(old('country_id',$editing ? $mission->country_id : null)===$country->id)>{{ $country->name }} ({{ $country->iso2 }})</option>@endforeach</select></label>
    <label>Date de début<input type="date" name="starts_on" value="{{ old('starts_on',$editing ? $mission->starts_on?->format('Y-m-d') : '') }}"></label>
    <label>Date de fin<input type="date" name="ends_on" value="{{ old('ends_on',$editing ? $mission->ends_on?->format('Y-m-d') : '') }}"></label>
    <label>Responsable<input name="manager_name" value="{{ old('manager_name',$editing ? $mission->manager_name : '') }}"></label>
    <label>Téléphone<input name="phone" value="{{ old('phone',$editing ? $mission->phone : '') }}"></label>
    <label>E-mail<input type="email" name="email" value="{{ old('email',$editing ? $mission->email : '') }}"></label>
    <label>Adresse<input name="address" value="{{ old('address',$editing ? $mission->address : '') }}"></label>
    <label class="full">Description<textarea name="description" rows="4">{{ old('description',$editing ? $mission->description : '') }}</textarea></label>
    <label class="full check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$editing ? $mission->is_active : true))> Mission active</label>
</div>
