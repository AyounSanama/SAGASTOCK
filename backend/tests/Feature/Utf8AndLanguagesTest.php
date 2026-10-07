<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUtf8Input;
use App\Support\Utf8;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Accents toujours en UTF-8 et liste complète des langues (ISO 639-1). */
class Utf8AndLanguagesTest extends TestCase
{
    public function test_windows_encoded_text_is_converted_to_utf8(): void
    {
        $windows = mb_convert_encoding('Hôpital de district d’Éfoulan, pédiatrie', 'Windows-1252', 'UTF-8');
        $this->assertFalse(mb_check_encoding($windows, 'UTF-8'));
        $this->assertSame('Hôpital de district d’Éfoulan, pédiatrie', Utf8::clean($windows));
        $this->assertSame('Paracétamol', Utf8::clean("\xEF\xBB\xBFParacétamol"), 'BOM retiré, UTF-8 conservé');
        $this->assertSame('Ça marche', Utf8::clean('Ça marche'));
    }

    public function test_every_request_input_reaches_the_application_in_utf8(): void
    {
        $request = Request::create('/test', 'POST', ['name' => mb_convert_encoding('Clinique Sainte-Thérèse', 'Windows-1252', 'UTF-8'), 'items' => ['Bébé']]);
        $seen = null;
        (new EnsureUtf8Input)->handle($request, function (Request $request) use (&$seen) {
            $seen = $request->all();

            return response('ok');
        });
        $this->assertSame(['name' => 'Clinique Sainte-Thérèse', 'items' => ['Bébé']], $seen);
    }

    public function test_language_catalog_lists_all_iso_languages(): void
    {
        $catalog = config('pharmacare_languages.catalog');
        $this->assertGreaterThanOrEqual(180, count($catalog));
        $this->assertSame(['fr', 'en'], array_slice(array_keys($catalog), 0, 2), 'Français et anglais en premier');
        $this->assertSame('Deutsch (allemand)', $catalog['de']);
        $this->assertSame('Wollof (wolof)', $catalog['wo']);
        $this->assertSame('Lingála (lingala)', $catalog['ln']);
        $this->assertSame('Kiswahili (swahili)', $catalog['sw']);
        $this->assertSame('العربية (arabe)', $catalog['ar']);
        foreach ($catalog as $code => $label) {
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $code);
            $this->assertTrue(mb_check_encoding($label, 'UTF-8'));
        }
    }
}
