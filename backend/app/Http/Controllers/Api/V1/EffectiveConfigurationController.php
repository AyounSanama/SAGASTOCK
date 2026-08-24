<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\OrganizationConfigurationSync;
use App\Models\Device;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EffectiveConfigurationController extends Controller
{
    public function __construct(private readonly UserScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        if (app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN) abort(403);
        $ids = $this->scopes->organizationIds($request->user());
        $organizationId = $request->input('organization_id') ?: $request->user()->organization_id ?: ($ids->count() === 1 ? $ids->first() : null);
        abort_unless($organizationId && $ids->contains($organizationId), 403);
        $configs = OrganizationEffectiveConfiguration::with('standard:id,code,name,category')
            ->where('organization_id', $organizationId)->where('status', 'active')->orderBy('platform_standard_id')->get();
        return response()->json([
            'organization_id' => $organizationId, 'generated_at' => now()->toIso8601String(),
            'configurations' => $configs->map(fn ($item) => [
                'id' => $item->id, 'standard' => $item->standard,
                'configuration_version' => $item->configuration_version,
                'standard_version' => 'v'.$item->standard_version_number.'.0',
                'checksum' => $item->checksum, 'effective_at' => $item->effective_at?->toIso8601String(),
                'synchronization_status' => $item->synchronization_status,
                'configuration' => $item->configuration,
            ])->values(),
        ]);
    }

    public function acknowledge(Request $request): JsonResponse
    {
        if (app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN) abort(403);
        $data = $request->validate([
            'organization_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
            'configurations' => ['required', 'array', 'max:100'],
            'configurations.*.id' => ['required', 'uuid'],
            'configurations.*.checksum' => ['required', 'string', 'size:64'],
        ]);
        abort_unless($this->scopes->organizationIds($request->user())->contains($data['organization_id']), 403);
        $device = Device::where('fingerprint', $data['device_id'])->where('user_id', $request->user()->id)->whereNull('revoked_at')->firstOrFail();

        DB::transaction(function () use ($data, $request, $device): void {
            foreach ($data['configurations'] as $configuration) {
                $effective = OrganizationEffectiveConfiguration::where('id', $configuration['id'])
                    ->where('organization_id', $data['organization_id'])->where('status', 'active')->firstOrFail();
                abort_unless(hash_equals($effective->checksum, $configuration['checksum']), 422);
                OrganizationConfigurationSync::updateOrCreate([
                    'organization_effective_configuration_id' => $effective->id,
                    'device_id' => $device->id,
                ], [
                    'organization_id' => $data['organization_id'], 'user_id' => $request->user()->id,
                    'checksum' => $effective->checksum, 'status' => 'synced', 'synced_at' => now(),
                ]);
                $effective->update(['synchronization_status' => 'synced']);
            }
        });

        return response()->json(['message' => 'Configuration synchronisée et acquittée.', 'synced_at' => now()->toIso8601String()]);
    }
}
