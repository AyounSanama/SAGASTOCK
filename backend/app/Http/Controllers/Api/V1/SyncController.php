<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SyncSupervisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    public function __construct(private readonly SyncSupervisionService $supervision) {}

    /** Le téléphone déclare son état après chaque synchronisation. */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'uuid'],
            'last_success_at' => ['nullable', 'date', 'before_or_equal:now'],
            'pending' => ['nullable', 'array'],
            'pending.*' => ['integer', 'min:0', 'max:100000'],
            'issues' => ['nullable', 'array', 'max:100'],
            'issues.*.id' => ['required', 'uuid'],
            'issues.*.kind' => ['required', Rule::in(['refused', 'conflict'])],
            'issues.*.module' => ['required', Rule::in(array_keys(SyncSupervisionService::MODULES))],
            'issues.*.reference' => ['nullable', 'string', 'max:80'],
            'issues.*.reason' => ['nullable', 'string', 'max:300'],
            'issues.*.occurred_at' => ['nullable', 'date'],
            'issues.*.reported' => ['nullable', 'boolean'],
        ]);
        $state = $this->supervision->report($request->user(), $data);

        return response()->json(['reported_at' => $state->reported_at->toIso8601String()]);
    }

    /** Supervision en lecture seule, dans le périmètre de l'utilisateur. */
    public function supervision(Request $request): JsonResponse
    {
        return response()->json($this->supervision->overview(
            $request->user(),
            $request->only(['project_id', 'filter', 'search', 'device']),
        ));
    }
}
