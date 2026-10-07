<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\User;
use App\Services\CoordinationService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** AM-172 — « Ma Coordination » (API, pour le mobile). Mêmes règles que le Web. */
class CoordinationController extends Controller
{
    public function __construct(private readonly CoordinationService $coordination, private readonly UserScopeService $scopes) {}

    public function overview(Request $request): JsonResponse
    {
        $this->coordination->assertCoordinator($request->user());
        $missionIds = $this->scopes->coordinationMissionIds($request->user());
        $mission = Mission::with('country')->findOrFail($request->query('mission_id', $missionIds->first()));
        abort_unless($missionIds->contains($mission->id), 404);
        $data = $this->coordination->overview($request->user(), $mission);
        $facilities = $data['facilities']->load(['targetPopulations:id,name', 'pathologies:id,name']);
        $sites = \App\Models\Site::whereIn('health_facility_id', $facilities->pluck('id'))->pluck('health_facility_id', 'id');
        $accountsByFacility = $data['facility_accounts']->groupBy(fn (User $user) => $sites[$user->roles->first()?->pivot?->scope_id] ?? null);
        $declarers = User::whereIn('id', $facilities->pluck('declared_by')->filter()->unique())->pluck('name', 'id');
        $management = app(\App\Services\HealthFacilityManagementService::class);
        $projectCodes = $data['projects']->pluck('code', 'id');

        return response()->json([
            'mission' => $mission->only(['id', 'code', 'name', 'default_language', 'additional_languages']) + ['country' => $mission->country?->name],
            'can_act' => ! $request->user()->read_only,
            'stats' => $data['stats'],
            'projects' => $data['projects']->map(fn ($project) => $project->only(['id', 'code', 'name', 'type', 'type_label', 'status', 'status_label',
                'donor_name', 'admin_name', 'validated_facilities_count', 'pending_facilities_count']))->values(),
            'facilities' => $facilities->map(fn ($facility) => [
                ...$facility->only(['id', 'code', 'name', 'validation_status', 'validation_status_label', 'refusal_reason', 'suspension_reason', 'created_at',
                    'order_period_months', 'delivery_lead_time_months', 'safety_stock_months', 'region', 'district', 'locality']),
                'category' => $facility->facilityCategory?->name,
                'care_level' => $facility->careLevel?->name,
                'declared_by' => $declarers[$facility->declared_by] ?? null,
                'target_populations' => $facility->targetPopulations->pluck('name')->values(),
                'pathologies' => $facility->pathologies->pluck('name')->values(),
                // Coût de génération limité aux FOSA en attente (écran « À valider »).
                'standard_list_count' => $facility->validation_status === \App\Models\HealthFacility::STATUS_PENDING ? $management->standardList($facility)->count() : null,
                'projects' => $facility->projects->map->only(['id', 'code', 'name'])->values(),
                'accounts' => ($accountsByFacility[$facility->id] ?? collect())->map(fn (User $user) => [
                    ...$user->only(['id', 'name', 'username', 'is_active']),
                    'role' => $user->roles->first()?->code === 'site_admin' ? 'Admin Site' : 'Utilisateur Site',
                    'status' => CoordinationService::accountStatus($user)['label'],
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ])->values(),
            ])->values(),
            'accounts' => $data['coordination_accounts']->map(function (User $user) use ($projectCodes) {
                $projectRole = $user->roles->firstWhere('code', 'project_admin');

                return [
                    ...$user->only(['id', 'name', 'first_name', 'last_name', 'phone', 'username', 'email', 'is_active', 'read_only']),
                    'project_id' => $projectRole?->pivot->scope_id,
                    'role' => $projectRole ? 'Admin Projet, '.($projectCodes[$projectRole->pivot->scope_id] ?? '—') : 'Coordination (lecture seule)',
                    'status' => CoordinationService::accountStatus($user)['label'],
                ];
            })->values(),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->coordination->assertCoordinator($request->user());
        $missionIds = $this->scopes->coordinationMissionIds($request->user());
        $mission = Mission::with('country')->findOrFail($request->query('mission_id', $missionIds->first()));
        abort_unless($missionIds->contains($mission->id), 404);

        return response()->json([
            'mission' => $mission->only(['id', 'code', 'name']) + ['country' => $mission->country?->name],
            ...$this->coordination->dashboard($request->user(), $mission, array_filter([
                'donor_id' => $request->string('donor_id')->toString() ?: null,
                'project_id' => $request->string('project_id')->toString() ?: null,
            ])),
        ]);
    }

    public function journal(Request $request): JsonResponse
    {
        $this->coordination->assertCoordinator($request->user());

        return response()->json(['data' => $this->coordination->journal($request->user(), $request->integer('days') ?: null)]);
    }

    public function validateFacility(Request $request, string $facility): JsonResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->validate($request, $model);

        return response()->json(['facility' => $model->fresh()]);
    }

    public function refuseFacility(Request $request, string $facility): JsonResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->refuse($request, $model, $this->reason($request));

        return response()->json(['facility' => $model->fresh()]);
    }

    public function suspendFacility(Request $request, string $facility): JsonResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->suspend($request, $model, $this->reason($request));

        return response()->json(['facility' => $model->fresh()]);
    }

    public function reactivateFacility(Request $request, string $facility): JsonResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->reactivate($request, $model);

        return response()->json(['facility' => $model->fresh()]);
    }

    /** Langues de la Coordination, choisies par elle-même. */
    public function updateLanguages(Request $request, Mission $mission): JsonResponse
    {
        $mission = $this->coordination->updateLanguages($request, $mission);

        return response()->json(['mission' => $mission->only(['id', 'default_language', 'additional_languages'])]);
    }

    public function updateAccount(Request $request, User $user): JsonResponse
    {
        $user = $this->coordination->updateAccount($request, $user);

        return response()->json(['user' => $user->only(['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'username', 'is_active'])]);
    }

    public function suspendAccount(Request $request, User $user): JsonResponse
    {
        $this->coordination->setAccountActive($request, $user, false);

        return response()->json(['user' => $user->fresh()->only(['id', 'username', 'is_active'])]);
    }

    public function reactivateAccount(Request $request, User $user): JsonResponse
    {
        $this->coordination->setAccountActive($request, $user, true);

        return response()->json(['user' => $user->fresh()->only(['id', 'username', 'is_active'])]);
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [
            'reason.required' => 'Le motif est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 5 caractères.',
        ])['reason'];
    }
}
