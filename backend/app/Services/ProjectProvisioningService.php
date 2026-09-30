<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectProvisioningService
{
    public const SUPPLY_FIELDS = ['order_period_months', 'delivery_lead_time_months', 'safety_stock_months'];

    /** AM-114 — Historique append-only des paramètres d'approvisionnement. */
    private function recordSupplySettings(Project $project, ?int $actorId): void
    {
        if (collect(self::SUPPLY_FIELDS)->every(fn (string $field) => $project->{$field} === null)) {
            return;
        }
        DB::table('project_supply_settings_history')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            ...$project->only(self::SUPPLY_FIELDS),
            'changed_by' => $actorId,
            'effective_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** @return array{project: Project, admin: ?User} */
    public function create(Organization $organization, array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($organization, $data, $actorId): array {
            $project = $organization->projects()->create(Arr::only($data, [
                'mission_id', 'code', 'name', 'implementing_partner',
                'donor_reference_code', 'moh_program_code', 'responsible_name',
                'responsible_contact', 'description', 'starts_on', 'ends_on',
                'order_period_months', 'delivery_lead_time_months',
                'safety_stock_months', 'status', 'is_active',
            ]));

            $project->donors()->sync($data['donor_ids'] ?? []);
            $project->programs()->sync($data['program_ids'] ?? []);
            $this->recordSupplySettings($project, $actorId);

            $admin = null;
            if (! empty($data['admin'])) {
                $adminData = $data['admin'];
                $admin = User::create([
                    'organization_id' => $organization->id,
                    'name' => trim($adminData['first_name'].' '.$adminData['last_name']),
                    'first_name' => $adminData['first_name'],
                    'last_name' => $adminData['last_name'],
                    'username' => $adminData['username'] ?? null,
                    'email' => $adminData['email'],
                    'phone' => $adminData['phone'] ?? null,
                    'password' => $adminData['password'],
                    'is_active' => true,
                    'must_change_password' => true,
                ]);

                $role = Role::where('code', GovernanceService::PROJECT_ADMIN)
                    ->where('is_active', true)->firstOrFail();
                $admin->roles()->attach($role->id, [
                    'scope_type' => 'project',
                    'scope_id' => $project->id,
                ]);
            }

            return [
                'project' => $project->load(['mission.country:id,iso2,name', 'donors:id,code,name', 'programs:id,code,name']),
                'admin' => $admin?->load('roles:id,code,name'),
            ];
        });
    }

    public function update(Project $project, array $data, ?int $actorId = null): Project
    {
        return DB::transaction(function () use ($project, $data, $actorId): Project {
            $project->fill(Arr::only($data, [
                'mission_id', 'code', 'name', 'implementing_partner',
                'donor_reference_code', 'moh_program_code', 'responsible_name',
                'responsible_contact', 'description', 'starts_on', 'ends_on',
                'order_period_months', 'delivery_lead_time_months',
                'safety_stock_months', 'status', 'is_active',
            ]));
            $supplyChanged = $project->isDirty(self::SUPPLY_FIELDS);
            $project->save();

            $project->donors()->sync($data['donor_ids'] ?? []);
            $project->programs()->sync($data['program_ids'] ?? []);
            if ($supplyChanged) {
                $this->recordSupplySettings($project, $actorId);
            }

            return $project->load([
                'organization:id,code,name',
                'mission.country:id,iso2,name',
                'donors:id,code,name',
                'programs:id,code,name',
            ]);
        });
    }
}
