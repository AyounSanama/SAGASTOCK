{{-- AM-110 — Champs d'identité communs aux formulaires de création et de modification.
     En modification, les valeurs du projet priment : old() ne sert qu'à la création,
     car la page contient un formulaire de modification par projet. --}}
@php($current = $project ?? null)
<label>Organisation / programme de mise en œuvre<input name="implementing_partner" value="{{ ($current ? $current->implementing_partner : old('implementing_partner')) }}" maxlength="190" placeholder="Ex. Ministère de la Santé, programme national"></label>
<label>Statut *<select name="status" required>@foreach(\App\Models\Project::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($current ? $current->status : old('status', 'active')) === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Code bailleur<input name="donor_reference_code" value="{{ ($current ? $current->donor_reference_code : old('donor_reference_code')) }}" maxlength="120"></label>
<label>Code programme MoH<input name="moh_program_code" value="{{ ($current ? $current->moh_program_code : old('moh_program_code')) }}" maxlength="120"></label>
<label>Responsable du projet<input name="responsible_name" value="{{ ($current ? $current->responsible_name : old('responsible_name')) }}" maxlength="160"></label>
<label>Contact du responsable<input name="responsible_contact" value="{{ ($current ? $current->responsible_contact : old('responsible_contact')) }}" maxlength="190" placeholder="E-mail ou téléphone"></label>
