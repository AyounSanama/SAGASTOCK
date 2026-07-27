<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_the_versioned_api_exposes_its_health_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'sagastock-api',
                'status' => 'ok',
                'version' => 'v1',
            ]);
    }
}
