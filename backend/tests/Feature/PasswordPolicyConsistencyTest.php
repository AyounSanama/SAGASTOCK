<?php

namespace Tests\Feature;

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyConsistencyTest extends TestCase
{
    public function test_five_characters_are_rejected_and_six_secure_characters_are_accepted(): void
    {
        $rules = ['password' => ['required', 'confirmed', PasswordPolicy::rule()]];

        $this->assertTrue(Validator::make([
            'password' => 'Aa1!a',
            'password_confirmation' => 'Aa1!a',
        ], $rules)->fails());

        $this->assertFalse(Validator::make([
            'password' => 'Aa1!aa',
            'password_confirmation' => 'Aa1!aa',
        ], $rules)->fails());
    }
}
