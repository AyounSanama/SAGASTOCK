<?php

namespace Tests\Feature;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsoCountryReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_iso_3166_reference_is_complete_and_contains_expected_codes(): void
    {
        $this->assertSame(249, Country::count());
        $this->assertDatabaseHas('countries', ['iso2' => 'CM', 'iso3' => 'CMR', 'name' => 'Cameroun']);
        $this->assertDatabaseHas('countries', ['iso2' => 'TD', 'iso3' => 'TCD', 'name' => 'Tchad']);
        $this->assertDatabaseHas('countries', ['iso2' => 'CF', 'iso3' => 'CAF', 'name' => 'République centrafricaine']);
        $this->assertDatabaseHas('countries', ['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France']);
        $this->assertDatabaseHas('countries', ['iso2' => 'US', 'iso3' => 'USA', 'name' => 'États-Unis']);
    }
}
