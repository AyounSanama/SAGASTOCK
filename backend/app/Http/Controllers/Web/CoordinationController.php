<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CoordinationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** AM-172 — Actions de « Ma Coordination » (Web). Mêmes règles que l'API. */
class CoordinationController extends Controller
{
    public function __construct(private readonly CoordinationService $coordination) {}

    public function validateFacility(Request $request, string $facility): RedirectResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->validate($request, $model);

        return back()->with('status', "FOSA « {$model->name} » validée. L’Admin Projet peut maintenant créer ses comptes.");
    }

    public function refuseFacility(Request $request, string $facility): RedirectResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->refuse($request, $model, $this->reason($request));

        return back()->with('status', "FOSA « {$model->name} » refusée. Le motif est visible par l’Admin Projet.");
    }

    public function suspendFacility(Request $request, string $facility): RedirectResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->suspend($request, $model, $this->reason($request));

        return back()->with('status', "FOSA « {$model->name} » suspendue. Ses comptes n’ont plus accès à PharmaCare.");
    }

    public function reactivateFacility(Request $request, string $facility): RedirectResponse
    {
        $model = $this->coordination->facility($request->user(), $facility);
        $this->coordination->reactivate($request, $model);

        return back()->with('status', "FOSA « {$model->name} » réactivée.");
    }

    public function updateAccount(Request $request, User $user): RedirectResponse
    {
        $user = $this->coordination->updateAccount($request, $user);

        return back()->with('status', "Compte de {$user->name} mis à jour.");
    }

    public function suspendAccount(Request $request, User $user): RedirectResponse
    {
        $this->coordination->setAccountActive($request, $user, false);

        return back()->with('status', "Compte {$user->username} suspendu.");
    }

    public function reactivateAccount(Request $request, User $user): RedirectResponse
    {
        $this->coordination->setAccountActive($request, $user, true);

        return back()->with('status', "Compte {$user->username} réactivé.");
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [
            'reason.required' => 'Le motif est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 5 caractères.',
        ])['reason'];
    }
}
