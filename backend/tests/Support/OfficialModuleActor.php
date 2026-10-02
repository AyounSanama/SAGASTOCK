<?php

namespace Tests\Support;

use App\Http\Middleware\EnforceV1ModuleAvailability;
use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * S-07 (02/10) : plus aucun rôle non officiel à la plateforme. Les tests des
 * modules conservés mais masqués en V1 (stock multi-sites, transferts,
 * quarantaine…) passent par un rôle officiel : l'Admin Projet du projet lié à
 * la FOSA, qui voit tous ses sites. Les permissions de ces modules lui sont
 * données ici uniquement (aucun changement de la matrice réelle) et le
 * masquage V1 est désactivé pour vérifier la logique conservée.
 */
trait OfficialModuleActor
{
    /**
     * @param  list<string>  $permissionCodes
     * @param  list<HealthFacility>  $facilities
     */
    private function officialProjectAdmin(Organization $organization, array $permissionCodes, array $facilities): User
    {
        $this->withoutMiddleware(EnforceV1ModuleAvailability::class);
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun']);
        $mission = Mission::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'M-'.$organization->code],
            ['country_id' => $country->id, 'name' => 'Mission', 'is_active' => true],
        );
        $project = Project::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'P-'.$organization->code],
            ['mission_id' => $mission->id, 'name' => 'Projet'],
        );
        foreach ($facilities as $facility) {
            $facility->projects()->syncWithoutDetaching([$project->id]);
        }

        $role = Role::firstOrCreate(['code' => 'project_admin'], ['name' => 'Admin Projet', 'is_system' => true, 'is_active' => true]);
        $role->permissions()->syncWithoutDetaching(
            collect($permissionCodes)->map(fn (string $code) => Permission::firstOrCreate(['code' => $code], ['name' => $code])->id),
        );
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->roles()->attach($role, ['scope_type' => 'project', 'scope_id' => $project->id]);

        return $user;
    }
}
