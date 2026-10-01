{{-- AM-162 (lot c1) — Champs de la FOSA pour l'Admin Projet : choix limités à la
     configuration validée par la Coordination. Mise en page définitive au lot c2. --}}
@php
    $current = $facility ?? null;
    $selectedLevel = old('care_level_id', $current?->care_level_id);
    $selectedCategory = old('facility_category_id', $current?->facility_category_id);
    $selectedPopulations = collect(old('target_population_ids', $current?->targetPopulations->pluck('id')->all() ?? []));
    $selectedPathologies = collect(old('pathology_ids', $current?->pathologies->pluck('id')->all() ?? []));
    $defaults = $facilityOptions['supply_defaults'];
    $value = fn (string $field) => old($field, $current?->{$field} ?? ($defaults[$field] ?? null));
    $date = fn (string $field) => old($field, $current?->{$field}?->format('Y-m-d'));
@endphp
<label class="field">Niveau de soins *<select name="care_level_id" required><option value="">Sélectionner</option>@foreach($facilityOptions['care_levels'] as $level)<option value="{{ $level->id }}" @selected($selectedLevel === $level->id)>{{ str_repeat('— ', max(0, $level->depth - 1)) }}{{ $level->name }}</option>@endforeach</select></label>
<label class="field">Catégorie de FOSA *<select name="facility_category_id" required><option value="">Sélectionner</option>@foreach($facilityOptions['facility_categories'] as $category)<option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
<fieldset class="field full"><legend>Population cible *</legend>@forelse($facilityOptions['target_populations'] as $population)<label class="check"><input type="checkbox" name="target_population_ids[]" value="{{ $population['id'] }}" @checked($selectedPopulations->contains($population['id']))> {{ $population['name'] }}</label>@empty<p class="muted">Aucune population configurée pour ce projet : la Coordination doit d’abord compléter la configuration médicale.</p>@endforelse</fieldset>
<fieldset class="field full"><legend>Pathologies / activités *</legend>@forelse($facilityOptions['pathologies'] as $pathology)<label class="check"><input type="checkbox" name="pathology_ids[]" value="{{ $pathology['id'] }}" @checked($selectedPathologies->contains($pathology['id']))> {{ $pathology['name'] }}</label>@empty<p class="muted">Aucune pathologie configurée pour ce projet.</p>@endforelse</fieldset>
<fieldset class="field full"><legend>Paramètres d’approvisionnement (Pharmacie du projet → FOSA)</legend><p class="muted">Préremplis avec les valeurs du projet, modifiables pour cette FOSA (historisés).</p>
<div class="form-grid">
<label class="field">Périodicité de commande<select name="order_period_months"><option value="">Non renseigné</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('order_period_months') === $month)>{{ $month }} mois</option>@endforeach</select></label>
<label class="field">Délai de livraison (DL)<select name="delivery_lead_time_months"><option value="">Non renseigné</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('delivery_lead_time_months') === $month)>{{ $month }} mois</option>@endforeach</select></label>
<label class="field">Stock de sécurité<select name="safety_stock_months"><option value="">Non renseigné</option>@foreach(\App\Models\HealthFacility::SAFETY_STOCK_OPTIONS as $months)<option value="{{ $months }}" @selected((string) (float) $value('safety_stock_months') === (string) (float) $months && $value('safety_stock_months') !== null)>{{ str_replace('.', ',', (string) $months) }} mois</option>@endforeach</select></label>
<label class="field">Date d’inventaire<input type="date" name="inventory_date" value="{{ $date('inventory_date') }}"></label>
<label class="field">Date de soumission de commande<input type="date" name="order_submission_date" value="{{ $date('order_submission_date') }}"></label>
<label class="field">Date de réception de commande<input type="date" name="order_receipt_date" value="{{ $date('order_receipt_date') }}"></label>
</div></fieldset>
