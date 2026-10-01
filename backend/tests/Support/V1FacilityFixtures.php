<?php

namespace Tests\Support;

use App\Models\CatalogReference;
use App\Models\HealthFacility;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * AM-162 — Une FOSA déclarée par l'Admin Projet exige la configuration médicale
 * du projet (Coordination) puis la validation de la Coordination avant ses comptes.
 */
trait V1FacilityFixtures
{
    /**
     * Configure le projet comme le ferait la Coordination et renvoie les champs V1
     * à joindre à la déclaration de la FOSA. À appeler avant d'agir en Admin Projet.
     *
     * @return array<string, mixed>
     */
    private function configureV1Project(Project $project): array
    {
        $coordination = User::factory()->create(['organization_id' => $project->organization_id, 'is_active' => true, 'must_change_password' => false]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $project->mission_id]);
        $level = CatalogReference::whereNull('organization_id')->where('code', 'SSP')->value('id');
        $adults = CatalogReference::whereNull('organization_id')->where('code', 'POP-ADULT')->value('id');
        $pathology = CatalogReference::create(['organization_id' => $project->organization_id, 'reference_type' => 'pathology', 'code' => 'PALU-'.$project->code, 'name' => 'Paludisme simple']);

        Sanctum::actingAs($coordination);
        $this->putJson("/api/v1/projects/{$project->id}/medical-configuration", [
            'care_level_ids' => [$level],
            'target_population_ids' => [$adults],
            'pathologies' => [['pathology_id' => $pathology->id, 'target_population_ids' => [$adults]]],
        ])->assertOk();

        return [
            'care_level_id' => $level,
            'facility_category_id' => CatalogReference::whereNull('organization_id')->where('code', 'CAT-CSI')->value('id'),
            'target_population_ids' => [$adults],
            'pathology_ids' => [$pathology->id],
        ];
    }

    /** Validation par la Coordination (écran livré au lot c1-bis). */
    private function validateFacility(string $facilityId): void
    {
        HealthFacility::whereKey($facilityId)->update(['validation_status' => HealthFacility::STATUS_VALIDATED, 'validated_at' => now()]);
    }
}
