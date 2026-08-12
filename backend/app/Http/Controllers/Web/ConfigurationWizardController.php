<?php

namespace App\Http\Controllers\Web;

use App\Enums\ConfigurationFlowType;
use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\ModuleActivation;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\Site;
use App\Models\StandardList;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ConfigurationWorkflowService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ConfigurationWizardController extends Controller
{
    public const STEPS = [
        'projects' => 3, 'donors' => 4, 'programs' => 5, 'modules' => 6,
        'features' => 7, 'standard-lists' => 8, 'facilities' => 9,
        'sites' => 10, 'users-access' => 11, 'summary' => 12,
    ];

    public const MODULES = [
        'references' => 'Référentiels et produits',
        'stocks' => 'Stocks et mouvements',
        'receptions' => 'Réceptions',
        'dispensing' => 'Dispensation',
        'inventories' => 'Inventaires',
        'orders' => 'Commandes et approbations',
        'alerts' => 'Alertes',
        'reports' => 'Rapports',
    ];

    public const FEATURES = [
        'batch_tracking' => 'Suivi des lots',
        'expiry_alerts' => 'Alertes de péremption',
        'fefo' => 'Sortie FEFO',
        'approval_workflow' => 'Circuit d’approbation',
        'offline_sync' => 'Synchronisation hors connexion',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly UserScopeService $scopes,
        private readonly GovernanceService $governance,
        private readonly ConfigurationWorkflowService $workflows,
    ) {}

    public function start(Request $request, string $flowType): RedirectResponse
    {
        $type = ConfigurationFlowType::from($flowType);
        if ($type === ConfigurationFlowType::NewOrganization) {
            abort_unless($request->user()?->hasPermission('organizations.manage'), 403);
        }
        $progress = $this->workflows->start($request, $type);
        if (! in_array($type, [
            ConfigurationFlowType::InitialConfiguration,
            ConfigurationFlowType::NewOrganization,
        ], true)) {
            $organization = $request->filled('organization')
                ? $this->scopes->organizations($request->user())
                    ->whereKey($request->input('organization'))->firstOrFail()
                : $this->organization($request);
            $version = ((int) SetupProgress::query()
                ->where('scope_type', 'organization')
                ->where('scope_id', $organization->id)
                ->whereKeyNot($progress->id)
                ->max('version')) + 1;
            $progress->update([
                'scope_type' => 'organization',
                'scope_id' => $organization->id,
                'version' => $version,
            ]);
        }
        $flow = [ConfigurationWorkflowService::REQUEST_KEY => $progress->workflow_id];

        return match ($type) {
            ConfigurationFlowType::InitialConfiguration => redirect()->route(
                'configuration.organization',
                $flow,
            ),
            ConfigurationFlowType::NewOrganization => redirect()->route(
                'configuration.organization',
                [...$flow, 'create' => 1],
            ),
            ConfigurationFlowType::NewMission => redirect()->route(
                'configuration.mission',
                [...$flow, 'new' => 1],
            ),
            ConfigurationFlowType::NewProject => redirect()->route(
                'configuration.step',
                ['step' => 'projects', ...$flow],
            ),
            ConfigurationFlowType::NewSite => redirect()->route(
                'configuration.step',
                ['step' => 'sites', ...$flow],
            ),
            ConfigurationFlowType::NewUser => redirect()->route(
                'configuration.step',
                ['step' => 'users-access', ...$flow],
            ),
        };
    }

    public function saveDraft(Request $request, string $workflow): JsonResponse
    {
        $request->merge([ConfigurationWorkflowService::REQUEST_KEY => $workflow]);
        $progress = $this->workflows->resolve($request);
        abort_unless($progress->workflow_id === $workflow, 404);
        $validated = $request->validate([
            'step' => ['required', 'integer', 'between:1,12'],
            'payload' => ['required', 'array'],
        ]);
        $this->workflows->saveDraft(
            $progress,
            (int) $validated['step'],
            $validated['payload'],
        );

        return response()->json([
            'saved' => true,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, string $step): View
    {
        $number = self::STEPS[$step] ?? abort(404);
        $progress = $this->workflows->resolve($request);
        $completed = collect($progress->completed_steps ?? [])->map(fn ($v) => (int) $v);
        abort_unless($completed->contains($number - 1), 403, 'Validez d’abord l’étape précédente.');
        $organization = $this->organization($request);
        $this->authorizeStep($request, $number);
        $this->workflows->restoreDraft($request, $progress, $number);

        return view('configuration.index', [
            'activeModule' => 'configuration',
            'activeStep' => $number,
            'completedSteps' => $completed,
            'stepStates' => $this->workflows->states($progress),
            'workflow' => $progress,
            ...$this->data($request, $organization, $progress),
        ]);
    }

    public function save(Request $request, string $step): RedirectResponse
    {
        $number = self::STEPS[$step] ?? abort(404);
        $progress = $this->workflows->resolve($request);
        $completed = collect($progress->completed_steps ?? [])->map(fn ($v) => (int) $v);
        abort_unless($completed->contains($number - 1), 403);
        $organization = $this->organization($request);
        $this->authorizeStep($request, $number);

        DB::transaction(function () use ($request, $step, $number, $organization, $progress) {
            $subject = match ($step) {
                'projects' => $this->saveProject($request, $organization, $progress),
                'donors' => $this->saveDonor($request, $organization, $progress),
                'programs' => $this->saveProgram($request, $organization, $progress),
                'modules' => $this->saveActivations($request, $organization, self::MODULES, ''),
                'features' => $this->saveActivations($request, $organization, self::FEATURES, 'feature:'),
                'standard-lists' => $this->saveStandardList($request, $organization, $progress),
                'facilities' => $this->saveFacility($request, $organization, $progress),
                'sites' => $this->saveSite($request, $organization, $progress),
                'users-access' => $this->saveUser($request, $organization, $progress),
                'summary' => $this->finalize($request, $progress),
            };
            if ($number < 12) $this->workflows->complete($progress, $number);
            $this->audit->record($request, "configuration.{$step}.validated", $subject);
        });

        if ($number === 12) {
            return redirect()->route('control-center')
                ->with('success', 'Configuration validée avec succès.');
        }

        $next = array_search($number + 1, self::STEPS, true);
        return redirect()->route(
            'configuration.step',
            $this->workflows->requestParameters($request, $progress, ['step' => $next]),
        )
            ->with('success', 'Étape enregistrée avec succès.');
    }

    public function storeUserAccount(Request $request): RedirectResponse
    {
        $progress = $this->workflows->resolve($request);
        abort_unless(collect($progress->completed_steps ?? [])->map(fn ($v) => (int) $v)->contains(10), 403);
        $organization = $this->organization($request);
        $this->authorizeStep($request, 11);
        $data = $request->validate([
            'first_name'=>['required','string','max:80'], 'last_name'=>['required','string','max:80'],
            'username'=>['required','alpha_dash','max:80','unique:users,username'],
            'email'=>['required','email','unique:users,email'], 'phone'=>['nullable','string','max:40'],
            'role_id'=>['required','integer','exists:roles,id'],
            'password'=>['required','confirmed',Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $role = $this->scopes->assignableRoles($request->user())->findOrFail($data['role_id']);
        [$scopeType,$scopeId] = $this->scopeForRole($role, $organization);
        abort_unless($this->governance->canAssign($request->user(), $role, $scopeType, $scopeId), 403);
        $user = User::create([
            'organization_id'=>$organization->id, 'first_name'=>$data['first_name'], 'last_name'=>$data['last_name'],
            'name'=>trim($data['first_name'].' '.$data['last_name']), 'username'=>strtolower($data['username']),
            'email'=>strtolower($data['email']), 'phone'=>$data['phone'] ?? null, 'password'=>$data['password'],
            'is_active'=>true, 'must_change_password'=>true,
        ]);
        $user->roles()->attach($role, ['scope_type'=>$scopeType,'scope_id'=>$scopeId]);
        $this->audit->record($request, 'configuration.user.created', $user);
        return redirect()->route('configuration.step', $this->workflows->requestParameters($request, $progress, ['step'=>'users-access']))
            ->with('success', 'Utilisateur créé avec succès');
    }

    public function loadUserAccount(Request $request, User $user): RedirectResponse
    {
        $progress = $this->workflows->resolve($request);
        abort_unless(collect($progress->completed_steps ?? [])->map(fn ($v) => (int) $v)->contains(10), 403);
        $organization = $this->organization($request);
        $this->authorizeStep($request, 11);
        $user = $this->scopes->users($request->user(), User::with('roles'))
            ->where('organization_id', $organization->id)->findOrFail($user->id);
        abort_unless($user->is_active && $user->roles->isNotEmpty(), 422, 'Le compte doit être actif et posséder un rôle.');
        $role = $user->roles->first();
        $scopeType = $role->pivot->scope_type;
        $scopeId = $role->pivot->scope_id;
        $validScope = match ($scopeType) {
            'organization' => $scopeId === $organization->id,
            'project' => $organization->projects()->whereKey($scopeId)->exists(),
            'site' => Site::whereKey($scopeId)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->exists(),
            default => false,
        };
        abort_unless($validScope, 422, 'Le périmètre du compte est incomplet ou invalide.');
        $this->workflows->remember($progress, 'user_id', $user->id);
        $this->workflows->complete($progress, 11);
        $this->audit->record($request, 'configuration.user.loaded', $user, [], [
            'workflow_id'=>$progress->workflow_id, 'scope_type'=>$scopeType, 'scope_id'=>$scopeId,
        ]);

        return redirect()->route('configuration.step', $this->workflows->requestParameters($request, $progress, ['step'=>'summary']))
            ->with('success', 'Compte chargé et étape Utilisateurs et accès validée.');
    }

    public function archive(Request $request, string $step): RedirectResponse
    {
        $number = self::STEPS[$step] ?? abort(404);
        abort_unless(in_array($number, [3, 4, 5, 8, 9, 10, 11], true), 404);
        $organization = $this->organization($request);
        $this->authorizeStep($request, $number);
        $model = $this->entity($organization, $number);
        abort_unless($model, 404);
        if ($model instanceof User) {
            abort_unless($this->scopes->canAccess($request->user(), $model), 404);
            abort_if($model->is($request->user()), 422, 'Vous ne pouvez pas archiver votre propre compte.');
            $model->update(['is_active' => false]);
        } elseif (isset($model->is_active)) {
            $model->update(['is_active' => false]);
        }
        $model->delete();
        $progress = $this->workflows->resolve($request);
        $this->workflows->invalidateFrom($progress, $number);
        $this->audit->record($request, "configuration.{$step}.archived", $model);

        return redirect()->route(
            'configuration.step',
            $this->workflows->requestParameters($request, $progress, ['step' => $step]),
        )
            ->with('success', 'Élément archivé. Vous pouvez le restaurer à tout moment.');
    }

    public function restore(Request $request, string $step, string $id): RedirectResponse
    {
        $number = self::STEPS[$step] ?? abort(404);
        abort_unless(in_array($number, [3, 4, 5, 8, 9, 10, 11], true), 404);
        $organization = $this->organization($request);
        $this->authorizeStep($request, $number);
        $model = $this->archivedEntity($organization, $number, $id);
        $model->restore();
        if (array_key_exists('is_active', $model->getAttributes())) {
            $model->update(['is_active' => true]);
        }
        $this->audit->record($request, "configuration.{$step}.restored", $model);

        $progress = $this->workflows->resolve($request);

        return redirect()->route(
            'configuration.step',
            $this->workflows->requestParameters($request, $progress, ['step' => $step]),
        )
            ->with('success', 'Élément restauré. Vérifiez les informations puis validez de nouveau l’étape.');
    }

    private function saveProject(Request $r, Organization $o, SetupProgress $progress): Project
    {
        $model = $progress->flow_type === ConfigurationFlowType::NewProject
            ? null
            : ($progress->context['project_id'] ?? null
                ? $o->projects()->find($progress->context['project_id'])
                : $o->projects()->oldest()->first());
        $d = $r->validate([
            'mission_id' => ['required', 'uuid', Rule::exists('missions', 'id')->where('organization_id', $o->id)->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('projects')->where('organization_id', $o->id)->ignore($model?->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active'])],
        ]);
        $d['is_active'] = true; unset($d['status']);
        $project = $model ? tap($model)->update($d) : $o->projects()->create($d);
        $this->workflows->remember($progress, 'project_id', $project->id);

        return $project;
    }

    private function saveDonor(Request $r, Organization $o, SetupProgress $progress): Donor
    {
        $project = $this->workflowProject($o, $progress);
        $model = $o->donors()->oldest()->first();
        $d = $r->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('donors')->where('organization_id', $o->id)->ignore($model?->id)],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'funding_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::in(['XAF', 'EUR', 'USD', 'GBP'])],
            'agreement_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $pivot = collect($d)->only(['funding_amount', 'currency', 'agreement_reference'])->all();
        $base = collect($d)->except(array_keys($pivot))->all() + ['is_active' => true];
        $donor = $model ? tap($model)->update($base) : $o->donors()->create($base);
        $project->donors()->syncWithoutDetaching([$donor->id => $pivot]);
        return $donor;
    }

    private function saveProgram(Request $r, Organization $o, SetupProgress $progress): Program
    {
        $project = $this->workflowProject($o, $progress);
        $model = $o->programs()->oldest()->first();
        $d = $r->validate([
            'donor_id' => ['nullable', 'uuid', Rule::exists('donors', 'id')->where('organization_id', $o->id)->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('programs')->where('organization_id', $o->id)->ignore($model?->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $d['is_active'] = true;
        $program = $model ? tap($model)->update($d) : $o->programs()->create($d);
        $project->programs()->syncWithoutDetaching([$program->id]);
        return $program;
    }

    private function saveActivations(Request $r, Organization $o, array $allowed, string $prefix): ModuleActivation
    {
        $d = $r->validate(['choices' => ['required', 'array', 'min:1'], 'choices.*' => [Rule::in(array_keys($allowed))]]);
        foreach ($allowed as $code => $_) {
            ModuleActivation::updateOrCreate(
                ['target_type' => 'organization', 'target_id' => $o->id, 'module_code' => $prefix.$code],
                ['is_enabled' => in_array($code, $d['choices'], true), 'updated_by' => $r->user()->id],
            );
        }
        return ModuleActivation::where('target_id', $o->id)->where('module_code', $prefix.$d['choices'][0])->firstOrFail();
    }

    private function saveStandardList(Request $r, Organization $o, SetupProgress $progress): StandardList
    {
        $project = $this->workflowProject($o, $progress);
        $model = $o->standardLists()->oldest()->first();
        $d = $r->validate([
            'name' => ['required', 'string', 'max:190'],
            'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('standard_lists')->where('organization_id', $o->id)->ignore($model?->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'allow_outside_list' => ['required', 'boolean'],
        ]);
        $base = $d + ['scope_type' => 'project', 'scope_id' => $project->id, 'is_active' => true];
        $list = $model ? tap($model)->update($base) : $o->standardLists()->create($base);
        $list->versions()->updateOrCreate(['version_number' => 1], [
            'status' => 'published', 'effective_from' => now()->toDateString(),
            'published_by' => $r->user()->id, 'published_at' => now(),
        ]);
        return $list;
    }

    private function saveFacility(Request $r, Organization $o, SetupProgress $progress): HealthFacility
    {
        $project = $this->workflowProject($o, $progress);
        $model = $o->healthFacilities()->oldest()->first();
        $d = $r->validate([
            'mission_id' => ['required', 'uuid', Rule::exists('missions', 'id')->where('organization_id', $o->id)],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('health_facilities')->where('organization_id', $o->id)->ignore($model?->id)],
            'facility_type' => ['required', Rule::in(['hospital', 'health_center', 'clinic', 'warehouse', 'community', 'other'])],
            'care_level' => ['required', Rule::in(['primary', 'secondary', 'tertiary', 'national'])],
            'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $d['is_active'] = true;
        $facility = $model ? tap($model)->update($d) : $o->healthFacilities()->create($d);
        $facility->projects()->syncWithoutDetaching([$project->id]);
        return $facility;
    }

    private function saveSite(Request $r, Organization $o, SetupProgress $progress)
    {
        $facility = $o->healthFacilities()
            ->whereKey($r->input('health_facility_id'))
            ->where('is_active', true)
            ->firstOrFail();
        $model = $progress->flow_type === ConfigurationFlowType::NewSite
            ? null
            : ($progress->context['site_id'] ?? null
                ? $facility->sites()->find($progress->context['site_id'])
                : $facility->sites()->oldest()->first());
        $d = $r->validate([
            'health_facility_id' => ['required', 'uuid', Rule::exists('health_facilities', 'id')->where('organization_id', $o->id)],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sites')->where('health_facility_id', $facility->id)->ignore($model?->id)],
            'site_type' => ['required', Rule::in(['stock', 'dispensing', 'stock_and_dispensing', 'quarantine', 'other'])],
            'location' => ['nullable', 'string', 'max:190'],
        ]);
        unset($d['health_facility_id']); $d['is_active'] = true; $d['organization_id'] = $o->id;
        $site = $model ? tap($model)->update($d) : $facility->sites()->create($d);
        $this->workflows->remember($progress, 'site_id', $site->id);

        return $site;
    }

    private function saveUser(Request $r, Organization $o, SetupProgress $progress): User
    {
        if ($progress->flow_type !== ConfigurationFlowType::NewUser
            && ($existing = $this->configuredUser($o))) return $existing;
        $role = Role::findOrFail($r->input('role_id'));
        $scope = $this->scopeForRole($role, $o);
        abort_unless($this->governance->canAssign($r->user(), $role, $scope[0], $scope[1]), 403);
        $d = $r->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'], 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);
        unset($d['role_id']); $d += ['organization_id' => $o->id, 'is_active' => true, 'must_change_password' => true];
        $user = User::create($d);
        $user->roles()->attach($role, ['scope_type' => $scope[0], 'scope_id' => $scope[1]]);
        $this->workflows->remember($progress, 'user_id', $user->id);
        return $user;
    }

    private function finalize(Request $r, SetupProgress $progress): SetupProgress
    {
        $states = $this->workflows->states($progress);
        abort_unless(
            collect(range(1, 11))->every(
                fn (int $step) => ($states[(string) $step] ?? null) === 'valid',
            ),
            422,
            'Toutes les étapes doivent être valides avant la confirmation.',
        );
        $r->validate(['confirmation' => ['accepted']]);
        $states['12'] = 'valid';
        $progress->update([
            'step_states' => $states,
            'completed_steps' => collect($progress->completed_steps ?? [])
                ->push(12)
                ->map(fn ($step) => (int) $step)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'current_step' => 12,
            'completed_at' => now(),
            'completed_by' => $r->user()->id,
            'workflow_status' => 'completed',
        ]);
        return $progress;
    }

    private function data(Request $r, Organization $o, SetupProgress $progress): array
    {
        $project = isset($progress->context['project_id'])
            ? $o->projects()->find($progress->context['project_id'])
            : ($progress->flow_type === ConfigurationFlowType::NewProject
                ? null
                : $o->projects()->oldest()->first());
        $facility = $o->healthFacilities()->oldest()->first();
        $site = isset($progress->context['site_id'])
            ? Site::whereKey($progress->context['site_id'])
                ->whereHas('healthFacility', fn ($query) => $query->where('organization_id', $o->id))
                ->first()
            : ($progress->flow_type === ConfigurationFlowType::NewSite
                ? null
                : $facility?->sites()->oldest()->first());
        $configuredUser = isset($progress->context['user_id'])
            ? User::find($progress->context['user_id'])
            : ($progress->flow_type === ConfigurationFlowType::NewUser
                ? null
                : $this->configuredUser($o));
        return [
            'organization' => $o, 'mission' => $o->missions()->where('is_active', true)->first(),
            'missions' => $o->missions()->where('is_active', true)->get(),
            'project' => $project, 'projects' => $o->projects()->where('is_active', true)->get(),
            'donor' => $o->donors()->oldest()->first(), 'donors' => $o->donors()->where('is_active', true)->get(),
            'program' => $o->programs()->oldest()->first(), 'programs' => $o->programs()->where('is_active', true)->get(),
            'modules' => self::MODULES, 'features' => self::FEATURES,
            'enabledModules' => ModuleActivation::where('target_id', $o->id)->where('is_enabled', true)->pluck('module_code'),
            'standardList' => $o->standardLists()->oldest()->first(),
            'facility' => $facility, 'facilities' => $o->healthFacilities()->where('is_active', true)->get(),
            'site' => $site,
            'sites' => $o->healthFacilities()->with('sites')->get()->pluck('sites')->flatten(),
            'roles' => $this->scopes->assignableRoles($r->user())->orderBy('name')->get(),
            'configuredUser' => $configuredUser,
            'configuredUsers' => $this->scopes->users($r->user(), User::with(['roles', 'organization']))
                ->where('organization_id', $o->id)->orderBy('name')->get(),
            'archivedEntities' => collect([
                3 => $o->projects()->onlyTrashed()->latest('deleted_at')->first(),
                4 => $o->donors()->onlyTrashed()->latest('deleted_at')->first(),
                5 => $o->programs()->onlyTrashed()->latest('deleted_at')->first(),
                8 => $o->standardLists()->onlyTrashed()->latest('deleted_at')->first(),
                9 => $o->healthFacilities()->onlyTrashed()->latest('deleted_at')->first(),
                10 => Site::onlyTrashed()->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $o->id))->latest('deleted_at')->first(),
                11 => $this->archivedUser($o),
            ]),
        ];
    }

    private function entity(Organization $organization, int $step)
    {
        return match ($step) {
            3 => $organization->projects()->oldest()->first(),
            4 => $organization->donors()->oldest()->first(),
            5 => $organization->programs()->oldest()->first(),
            8 => $organization->standardLists()->oldest()->first(),
            9 => $organization->healthFacilities()->oldest()->first(),
            10 => $organization->healthFacilities()->first()?->sites()->oldest()->first(),
            11 => $this->configuredUser($organization),
        };
    }

    private function archivedEntity(Organization $organization, int $step, string $id)
    {
        return match ($step) {
            3 => $organization->projects()->onlyTrashed()->findOrFail($id),
            4 => $organization->donors()->onlyTrashed()->findOrFail($id),
            5 => $organization->programs()->onlyTrashed()->findOrFail($id),
            8 => $organization->standardLists()->onlyTrashed()->findOrFail($id),
            9 => $organization->healthFacilities()->onlyTrashed()->findOrFail($id),
            10 => Site::onlyTrashed()->whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail(),
            11 => User::onlyTrashed()->whereKey($id)->whereHas('roles', fn ($q) => $q->whereIn('role_user.scope_id', $this->scopeIds($organization)))->firstOrFail(),
        };
    }

    private function organization(Request $r): Organization
    {
        $progress = $this->workflows->resolve($r);
        $query = $this->scopes->organizations($r->user());

        return $progress->scope_type === 'organization' && $progress->scope_id
            ? $query->whereKey($progress->scope_id)->firstOrFail()
            : $query->oldest()->firstOrFail();
    }

    private function workflowProject(Organization $organization, SetupProgress $progress): Project
    {
        return isset($progress->context['project_id'])
            ? $organization->projects()->whereKey($progress->context['project_id'])->firstOrFail()
            : $organization->projects()->where('is_active', true)->firstOrFail();
    }

    private function authorizeStep(Request $r, int $step): void
    {
        $permission = match ($step) {
            3 => 'projects.manage', 4, 5 => 'funding.manage', 6, 7 => 'modules.manage',
            8 => 'catalog.manage', 9, 10 => 'structures.manage', 11 => 'users.manage',
            12 => 'organizations.manage',
        };
        abort_unless($r->user()?->hasPermission($permission), 403);
    }

    private function scopeForRole(Role $role, Organization $o): array
    {
        return match ($this->governance->canonicalCode($role->code)) {
            GovernanceService::COORDINATION_ADMIN => ['organization', $o->id],
            GovernanceService::PROJECT_ADMIN => ['project', $o->projects()->where('is_active', true)->firstOrFail()->id],
            GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER => ['site', $o->healthFacilities()->firstOrFail()->sites()->where('is_active', true)->firstOrFail()->id],
            default => abort(422, 'Rôle non attribuable.'),
        };
    }

    private function configuredUser(Organization $o): ?User
    {
        $scopeIds = collect([$o->id, $o->projects()->value('id'), $o->healthFacilities()->first()?->sites()->value('id')])->filter();
        return User::whereHas('roles', fn ($q) => $q->whereIn('role_user.scope_id', $scopeIds))->oldest()->first();
    }

    private function archivedUser(Organization $organization): ?User
    {
        return User::onlyTrashed()
            ->whereHas('roles', fn ($q) => $q->whereIn('role_user.scope_id', $this->scopeIds($organization)))
            ->latest('deleted_at')->first();
    }

    private function scopeIds(Organization $organization): array
    {
        return collect([
            $organization->id,
            $organization->projects()->withTrashed()->value('id'),
            $organization->healthFacilities()->withTrashed()->first()?->sites()->withTrashed()->value('id'),
        ])->filter()->values()->all();
    }
}
