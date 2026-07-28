<?php

namespace App\Services;

use App\Models\ModuleActivation;

class ModuleActivationService
{
    public function isEnabled(
        string $moduleCode,
        string $organizationId,
        ?string $projectId = null,
        ?string $facilityId = null,
    ): bool {
        $targets = collect([
            $facilityId ? ['type' => 'facility', 'id' => $facilityId] : null,
            $projectId ? ['type' => 'project', 'id' => $projectId] : null,
            ['type' => 'organization', 'id' => $organizationId],
        ])->filter();

        foreach ($targets as $target) {
            $activation = ModuleActivation::where('target_type', $target['type'])
                ->where('target_id', $target['id'])
                ->where('module_code', $moduleCode)
                ->first();
            if ($activation) {
                return $activation->is_enabled;
            }
        }

        return true;
    }
}
