<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectProvisioningService
{
    /** @return array{project: Project, admin: ?User} */
    public function create(Organization $organization, array $data): array
    {
        return DB::transaction(function () use ($organization, $data): array {
            $project = $organization->projects()->create(Arr::only($data, [
                'mission_id', 'code', 'name', 'description', 'starts_on',
                'ends_on', 'order_period_months', 'delivery_lead_time_months',
                'safety_stock_months', 'is_active',
            ]));

            $project->donors()->sync($data['donor_ids'] ?? []);
            $project->programs()->sync($data['program_ids'] ?? []);

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

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            $project->update(Arr::only($data, [
                'mission_id', 'code', 'name', 'description', 'starts_on',
                'ends_on', 'order_period_months', 'delivery_lead_time_months',
                'safety_stock_months', 'is_active',
            ]));

            $project->donors()->sync($data['donor_ids'] ?? []);
            $project->programs()->sync($data['program_ids'] ?? []);

            return $project->load([
                'organization:id,code,name',
                'mission.country:id,iso2,name',
                'donors:id,code,name',
                'programs:id,code,name',
            ]);
        });
    }
}
