<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Les erreurs de saisie s'affichent en français, jamais sous forme de clé brute (« validation.email »). */
class FrenchValidationMessagesTest extends TestCase
{
    public function test_validation_messages_are_translated_in_french(): void
    {
        app()->setLocale('fr');
        $messages = Validator::make(
            ['admin_email' => 'contact@ong.org.', 'admin_username' => 'a b'],
            ['admin_email' => 'email', 'admin_username' => 'alpha_dash', 'country_ids' => 'required'],
        )->errors()->all();

        $this->assertSame([
            'Le champ e-mail de connexion doit être une adresse e-mail valide (ex. nom@domaine.org).',
            'Le champ identifiant de l’administrateur ne doit contenir que des lettres, chiffres, tirets et tirets bas (sans espace).',
            'Le champ pays est obligatoire.',
        ], $messages);
        foreach ($messages as $message) {
            $this->assertStringNotContainsString('validation.', $message);
        }
    }
}
