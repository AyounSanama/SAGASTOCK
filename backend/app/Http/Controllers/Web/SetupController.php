<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SetupProgress;
use App\Services\GovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupController extends Controller
{
    public const STEPS = [
        1 => ['Organisation', 'Créer l’organisation coordinatrice.', 'organizations.index'],
        2 => ['Mission', 'Définir le pays et la mission.', 'organizations.index'],
        3 => ['Projet', 'Créer le premier projet.', 'organizations.index'],
        4 => ['Bailleurs', 'Enregistrer les partenaires financiers.', 'organizations.index'],
        5 => ['Programmes', 'Associer les programmes au projet.', 'organizations.index'],
        6 => ['Modules', 'Activer les modules nécessaires.', 'organizations.index'],
        7 => ['Fonctionnalités', 'Vérifier les fonctions autorisées.', 'security.index'],
        8 => ['Import des listes', 'Importer les référentiels disponibles.', 'organizations.index'],
        9 => ['Formations sanitaires', 'Créer les établissements de santé.', 'organizations.index'],
        10 => ['Sites', 'Créer les sites de stockage et de dispensation.', 'organizations.index'],
        11 => ['Utilisateurs', 'Créer les administrateurs du niveau inférieur.', 'users.index'],
        12 => ['Validation', 'Contrôler puis activer la configuration.', 'setup.index'],
    ];

    public function index(Request $request): View
    {
        abort_unless($this->canConfigure($request), 403);
        return view('setup.index', [
            'steps' => self::STEPS,
            'progress' => SetupProgress::current(),
        ]);
    }

    public function completeStep(Request $request, int $step): RedirectResponse
    {
        abort_unless($this->canConfigure($request), 403);
        abort_unless(isset(self::STEPS[$step]) && $step < 12, 404);
        $progress = SetupProgress::current();
        $completed = collect($progress->completed_steps ?? [])
            ->push($step)->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();
        $progress->update(['completed_steps' => $completed]);

        return back()->with('message', 'Étape enregistrée.');
    }

    public function finish(Request $request): RedirectResponse
    {
        abort_unless($this->canConfigure($request), 403);
        $progress = SetupProgress::current();
        $required = range(1, 11);
        $completed = array_map('intval', $progress->completed_steps ?? []);
        if (array_diff($required, $completed)) {
            return back()->withErrors([
                'setup' => 'Toutes les étapes précédentes doivent être validées.',
            ]);
        }
        $progress->update([
            'completed_steps' => range(1, 12),
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return redirect()->route('dashboard')->with('message', 'Configuration initiale terminée.');
    }

    private function canConfigure(Request $request): bool
    {
        return app(GovernanceService::class)->roleCode($request->user())
            === GovernanceService::OWNER;
    }
}
