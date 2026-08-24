<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
    public function test_fundamental_components_render_accessible_markup(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-app-button icon="add">Ajouter</x-app-button>
            <x-app-icon-button icon="edit" label="Modifier" />
            <x-app-input name="email" label="Adresse e-mail" icon="mail" required />
            <x-app-card title="Organisation" description="Informations essentielles">Contenu</x-app-card>
            <x-app-badge variant="success">Actif</x-app-badge>
            <x-app-breadcrumb :items="[['label' => 'Tableau de bord', 'url' => '/dashboard'], ['label' => 'Organisations']]" />
        BLADE);

        $this->assertStringContainsString('app-button--primary', $html);
        $this->assertStringContainsString('aria-label="Modifier"', $html);
        $this->assertStringContainsString('app-field__input', $html);
        $this->assertStringContainsString('app-card__title', $html);
        $this->assertStringContainsString('app-badge--success', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_loading_and_disabled_buttons_expose_their_state(): void
    {
        $html = Blade::render('<x-app-button loading>Enregistrer</x-app-button><x-app-button disabled>Supprimer</x-app-button>');

        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertSame(2, substr_count($html, 'disabled'));
    }

    public function test_form_sheet_uses_the_canonical_accessible_structure(): void
    {
        $html = Blade::render('<x-form-sheet id="test-sheet" title="Ajouter" description="Description"><form><div class="form-sheet-actions">Actions</div></form></x-form-sheet>');

        $this->assertStringContainsString('class="form-sheet"', $html);
        $this->assertStringContainsString('aria-labelledby="test-sheet-title"', $html);
        $this->assertStringContainsString('aria-describedby="test-sheet-description"', $html);
        $this->assertStringContainsString('material-symbols-outlined', $html);
        $this->assertStringContainsString('aria-label="Fermer"', $html);
    }

    public function test_data_discovery_components_render_accessible_markup(): void
    {
        $filters = Blade::render('<x-app-filter-bar><x-app-search-input name="q" /><x-slot:actions><x-app-button>Filtrer</x-app-button></x-slot:actions></x-app-filter-bar>');
        $table = Blade::render('<x-app-data-table label="Utilisateurs"><thead><tr><th>Nom</th></tr></thead><tbody><tr><td>Sayan</td></tr></tbody></x-app-data-table>');
        $empty = Blade::render('<x-app-empty-state title="Aucun résultat" description="Modifiez votre recherche." />');

        $this->assertStringContainsString('class="app-filter-bar', $filters);
        $this->assertStringContainsString('material-symbols-outlined', $filters);
        $this->assertStringContainsString('aria-label="Utilisateurs"', $table);
        $this->assertStringContainsString('class="app-data-table__scroll"', $table);
        $this->assertStringContainsString('Aucun résultat', $empty);
    }

    public function test_dashboard_components_share_the_canonical_cards(): void
    {
        $kpi = Blade::render('<x-app-kpi-card label="Utilisateurs actifs" value="24" icon="group" href="/users" tone="green" />');
        $panel = Blade::render('<x-app-dashboard-panel title="Activités" description="Actions récentes" icon="history">Contenu</x-app-dashboard-panel>');

        $this->assertStringContainsString('class="app-kpi-card app-kpi-card--green"', $kpi);
        $this->assertStringContainsString('aria-label="Utilisateurs actifs : 24"', $kpi);
        $this->assertStringContainsString('class="app-dashboard-panel"', $panel);
        $this->assertStringContainsString('Actions récentes', $panel);
    }

    public function test_global_page_structure_renders_one_header_and_layout(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-app-page-layout>
                <x-app-page-header title="Organisations" description="Gérez les organisations.">
                    <x-slot:actions><x-app-button icon="add">Ajouter</x-app-button></x-slot:actions>
                </x-app-page-header>
            </x-app-page-layout>
        BLADE);

        $this->assertSame(1, substr_count($html, 'app-page-layout'));
        $this->assertSame(1, substr_count($html, 'app-page-header__title'));
        $this->assertStringContainsString('Gérez les organisations.', $html);
    }
}
