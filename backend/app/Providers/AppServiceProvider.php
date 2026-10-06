<?php

namespace App\Providers;

use App\Models\HealthFacility;
use App\Models\Project;
use App\Models\User;
use App\Policies\HealthFacilityPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // AM-172 (niveau 3, lot 4) : règles d'accès communes au Web et à l'API.
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(HealthFacility::class, HealthFacilityPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);

        // Base réelle et copie de travail : pas de migrate:fresh / refresh / reset ni db:wipe.
        \App\Support\DatabaseGuard::prohibitDestructiveCommands();
    }
}
