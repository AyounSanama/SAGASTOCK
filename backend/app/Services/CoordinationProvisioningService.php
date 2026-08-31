<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoordinationProvisioningService
{
    /** @return Collection<string, Mission> */
    public function provision(Organization $organization, array $countryIds): Collection
    {
        $countries = Country::query()->whereIn('id', $countryIds)->get()->keyBy('id');

        return collect($countryIds)->mapWithKeys(function (string $countryId) use ($organization, $countries): array {
            $country = $countries->get($countryId);
            abort_unless($country, 422, 'Pays de coordination introuvable.');
            $code = Str::upper(Str::limit($organization->code.'-'.$country->iso2, 40, ''));
            $mission = Mission::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'country_id' => $country->id],
                [
                    'code' => $code,
                    'name' => 'Coordination '.$country->name,
                    'is_active' => true,
                ],
            );

            return [$countryId => $mission];
        });
    }
}
