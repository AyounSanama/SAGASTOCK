{{-- Niveau 2 — Étape 4 : paramètres Entrepôt central → Pharmacie du projet, puis projet « Actif ». --}}
@php
    $stepNumber = 4;
    $stepSubtitle = 'paramètres de l’entrepôt central vers la pharmacie du projet.';
    $active = $project->status === 'active';
    $months = fn ($value) => $value === null ? '—' : str_replace('.', ',', (string) $value).' mois';
    $value = fn (string $field) => old($field, $project->{$field} instanceof \DateTimeInterface ? $project->{$field}->format('Y-m-d') : $project->{$field});
    $fieldError = fn (string $field) => $errors->has($field) ? 'invalid' : '';
@endphp
@extends('projects.wizard.layout')
@section('wizard')
<div class="wz-grid">
    <form id="wizard-form" method="post" action="{{ route('projects.wizard.supply.update', $project) }}" class="wz-card" novalidate>
        @csrf @method('PUT')
        <h2>Paramètres d’approvisionnement</h2>
        <p class="wz-sub">Ils seront proposés par défaut à chaque FOSA du projet, puis modifiables par FOSA.</p>
        <div class="wz-fields three">
            <label class="wz-field {{ $fieldError('order_period_months') }}">Périodicité de commande *
                <select name="order_period_months" required>
                    <option value="">Sélectionner</option>
                    @foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('order_period_months') === $month)>{{ $month }} mois</option>@endforeach
                </select>
                <small>de 1 à 12 mois</small>
                @error('order_period_months')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
            <label class="wz-field {{ $fieldError('delivery_lead_time_months') }}">Délai de livraison *
                <select name="delivery_lead_time_months" required>
                    <option value="">Sélectionner</option>
                    @foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected((int) $value('delivery_lead_time_months') === $month)>{{ $month }} mois</option>@endforeach
                </select>
                <small>en mois</small>
                @error('delivery_lead_time_months')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
            <label class="wz-field {{ $fieldError('safety_stock_months') }}">Stock de sécurité *
                <select name="safety_stock_months" required>
                    <option value="">Sélectionner</option>
                    @foreach(\App\Models\HealthFacility::SAFETY_STOCK_OPTIONS as $option)<option value="{{ $option }}" @selected($value('safety_stock_months') !== null && (float) $value('safety_stock_months') === (float) $option)>{{ str_replace('.', ',', (string) $option) }} mois</option>@endforeach
                </select>
                <small>0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois</small>
                @error('safety_stock_months')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
            <label class="wz-field {{ $fieldError('inventory_date') }}">Date d’inventaire
                <input type="date" name="inventory_date" value="{{ $value('inventory_date') }}">
                @error('inventory_date')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
            <label class="wz-field {{ $fieldError('order_submission_date') }}">Date de soumission de commande
                <input type="date" name="order_submission_date" value="{{ $value('order_submission_date') }}">
                @error('order_submission_date')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
            <label class="wz-field {{ $fieldError('order_receipt_date') }}">Date de réception de commande
                <input type="date" name="order_receipt_date" value="{{ $value('order_receipt_date') }}">
                @error('order_receipt_date')<span class="wz-error">{{ $message }}</span>@enderror
            </label>
        </div>
        <p class="wz-info">
            @if($active)
                Les nouvelles valeurs sont proposées aux FOSA déclarées ensuite ; chaque modification est historisée.
            @else
                Après création, le projet passe « Actif ». Vous pourrez ensuite créer le compte de l’Admin Projet dans Ma Coordination &gt; Comptes de la coordination.
            @endif
        </p>
        <div class="wz-foot">
            <a class="wz-btn" href="{{ route('projects.wizard.show', [$project, 'standard-list']) }}">Précédent</a>
            <div class="wz-end"><button class="wz-btn primary" type="submit" name="intent" value="create"><span class="material-symbols-outlined" aria-hidden="true">check</span>{{ $active ? 'Enregistrer' : 'Créer le projet' }}</button></div>
        </div>
    </form>

    @if($active && $project->healthFacilities()->exists())
        {{-- DEC-08 : action explicite, confirmée ; une modification du projet n'écrase jamais les FOSA. --}}
        <form method="post" action="{{ route('projects.supply.apply', $project) }}" class="wz-card" style="grid-column:1/2" onsubmit="return confirm('Appliquer les paramètres enregistrés du projet à toutes ses FOSA ? Les valeurs propres à chaque FOSA seront remplacées (historisées).')">
            @csrf <input type="hidden" name="confirm" value="1">
            <h2>Appliquer à toutes les FOSA</h2>
            <p class="wz-sub" style="margin-bottom:12px">Recopie les paramètres enregistrés du projet (périodicité, DL, stock de sécurité, dates) dans les {{ $project->healthFacilities()->count() }} FOSA du projet. Enregistrez d’abord les nouvelles valeurs ci-dessus.</p>
            <button class="wz-btn" type="submit">Appliquer à toutes les FOSA</button>
        </form>
    @endif
    <aside class="wz-recap" aria-label="Récapitulatif">
        <h2>Récapitulatif</h2>
        <dl>
            <dt>Type</dt><dd>{{ $summary['type'] }}</dd>
            <dt>Mission</dt><dd>{{ $summary['mission'] ?? '—' }}</dd>
            <dt>Bailleur</dt><dd>{{ $summary['donor'] ?? '—' }}</dd>
            <dt>Code</dt><dd>{{ $summary['code'] }}</dd>
            <dt>Niveaux de soins</dt><dd title="{{ implode(', ', $summary['care_levels']) }}">{{ $summary['care_levels'] ? implode(', ', $summary['care_levels']) : '—' }}</dd>
            <dt>Populations</dt><dd>{{ $summary['populations'] }} {{ $summary['populations'] > 1 ? 'sélectionnées' : 'sélectionnée' }}</dd>
            <dt>Pathologies</dt><dd>{{ $summary['pathologies'] }} {{ $summary['pathologies'] > 1 ? 'sélectionnées' : 'sélectionnée' }}</dd>
            <dt>Liste Standard</dt><dd>{{ $summary['retained'] === null ? 'Non configurée' : $summary['retained'].' produits retenus' }}</dd>
            <dt>Périodicité</dt><dd>{{ $months($summary['order_period_months']) }}</dd>
            <dt>Délai / Stock de sécurité</dt><dd>{{ $months($summary['delivery_lead_time_months']) }} / {{ $months($summary['safety_stock_months']) }}</dd>
        </dl>
    </aside>
</div>
@endsection
