<?php

namespace App\Services;

use App\Models\PlatformStandard;
use App\Models\PlatformStandardVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformStandardVersionService
{
    public function createInitial(PlatformStandard $standard, User $actor, ?string $notes = null): PlatformStandardVersion
    {
        return $standard->versions()->create([
            'version_number' => 1, 'status' => 'draft', 'snapshot' => $this->snapshot($standard),
            'change_notes' => $notes, 'created_by' => $actor->id,
        ]);
    }

    public function captureDraft(PlatformStandard $standard, User $actor, ?string $notes = null): PlatformStandardVersion
    {
        $latest = $standard->versions()->latest('version_number')->first();
        if ($latest?->status === 'draft') {
            $latest->update(['snapshot' => $this->snapshot($standard), 'change_notes' => $notes ?: $latest->change_notes]);
            return $latest->fresh();
        }

        return $standard->versions()->create([
            'version_number' => ((int) $standard->versions()->max('version_number')) + 1,
            'status' => 'draft', 'snapshot' => $this->snapshot($standard),
            'change_notes' => $notes, 'created_by' => $actor->id,
        ]);
    }

    public function publish(PlatformStandard $standard, PlatformStandardVersion $version, User $actor): void
    {
        if ($version->platform_standard_id !== $standard->id || $version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Seule une version brouillon de ce standard peut être publiée.']);
        }

        DB::transaction(function () use ($standard, $version, $actor): void {
            $standard->versions()->where('status', 'published')->update(['status' => 'archived', 'archived_at' => now()]);
            $version->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now(), 'archived_at' => null]);
            $standard->update(['status' => 'published', 'updated_by' => $actor->id]);
        });
    }

    public function rollback(PlatformStandard $standard, PlatformStandardVersion $source, User $actor, ?string $notes = null): PlatformStandardVersion
    {
        if ($source->platform_standard_id !== $standard->id || $source->status !== 'archived') {
            throw ValidationException::withMessages(['version' => 'Sélectionnez une ancienne version archivée.']);
        }

        return DB::transaction(function () use ($standard, $source, $actor, $notes): PlatformStandardVersion {
            $standard->versions()->where('status', 'published')->update(['status' => 'archived', 'archived_at' => now()]);
            $version = $standard->versions()->create([
                'version_number' => ((int) $standard->versions()->max('version_number')) + 1,
                'status' => 'published', 'snapshot' => $source->snapshot,
                'change_notes' => $notes ?: "Restauration depuis {$source->label}",
                'created_by' => $actor->id, 'published_by' => $actor->id, 'published_at' => now(),
            ]);
            $standard->update([...$source->snapshot, 'status' => 'published', 'updated_by' => $actor->id]);
            return $version;
        });
    }

    private function snapshot(PlatformStandard $standard): array
    {
        return $standard->only(['category', 'code', 'name', 'description', 'definition', 'is_active']);
    }
}
