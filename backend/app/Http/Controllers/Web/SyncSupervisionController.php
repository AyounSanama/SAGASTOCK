<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SyncSupervisionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SyncSupervisionController extends Controller
{
    public function __construct(private readonly SyncSupervisionService $supervision) {}

    /** Même tableau que l'écran mobile (API /sync/supervision). */
    public function index(Request $request): View
    {
        return view('synchronization.supervision', [
            'board' => $this->supervision->overview(
                $request->user(),
                $request->only(['project_id', 'filter', 'search', 'device']),
            ),
        ]);
    }
}
