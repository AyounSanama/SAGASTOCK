<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Niveau 4 — Contrôle automatique des couleurs dans les deux modes : les
 * jetons sont lus dans resources/css/design-system.css (bloc :root et bloc
 * :root[data-theme="dark"]) et chaque couple doit atteindre le minimum WCAG
 * (4,5:1 pour le texte, 3:1 pour la bordure d'un champ).
 */
class ThemeContrastTest extends TestCase
{
    /** [libellé, texte, fond, minimum] — jetons sans le préfixe « --pc- ». */
    private const PAIRS = [
        ['Texte / fond de page', 'color-text', 'color-background', 4.5],
        ['Texte / carte', 'color-text', 'color-surface', 4.5],
        ['Texte secondaire / carte', 'color-text-muted', 'color-surface', 4.5],
        ['Texte secondaire / fond de page', 'color-text-muted', 'color-background', 4.5],
        ['Lien / carte', 'color-link', 'color-surface', 4.5],
        ['Bouton : blanc / orange', '#FFFFFF', 'color-primary-strong', 4.5],
        ['Sélection : texte / fond', 'color-primary-soft-text', 'color-primary-soft', 4.5],
        ['Menu : texte / fond', 'color-sidebar-text', 'color-sidebar', 4.5],
        ['Menu : élément actif / fond', 'color-primary', 'color-sidebar', 4.5],
        ['Succès', 'status-success-text', 'status-success-bg', 4.5],
        ['Information', 'status-info-text', 'status-info-bg', 4.5],
        ['Erreur', 'status-danger-text', 'status-danger-bg', 4.5],
        ['Neutre', 'status-neutral-text', 'status-neutral-bg', 4.5],
        ['Péremption', 'status-expiry-text', 'status-expiry-bg', 4.5],
        ['Bordure de champ / carte', 'color-control-border', 'color-surface', 3.0],
    ];

    public static function themes(): array
    {
        return ['clair' => ['light'], 'sombre' => ['dark']];
    }

    #[DataProvider('themes')]
    public function test_every_pair_meets_the_minimum_contrast(string $theme): void
    {
        $tokens = self::tokens($theme);
        foreach (self::PAIRS as [$label, $foreground, $background, $minimum]) {
            $ratio = self::ratio(self::color($tokens, $foreground), self::color($tokens, $background));
            $this->assertGreaterThanOrEqual($minimum, round($ratio, 2), sprintf('%s (%s) : %.2f:1 < %.1f:1', $label, $theme, $ratio, $minimum));
        }
    }

    public function test_states_never_use_orange(): void
    {
        foreach (['light', 'dark'] as $theme) {
            $tokens = self::tokens($theme);
            foreach (['success', 'info', 'danger', 'neutral', 'expiry'] as $state) {
                [$r, $g, $b] = self::rgb(self::color($tokens, "status-$state-text"));
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $hue = $max === $min ? 0 : match ($max) {
                    $r => fmod(60 * (($g - $b) / ($max - $min)) + 360, 360),
                    $g => 60 * (($b - $r) / ($max - $min)) + 120,
                    default => 60 * (($r - $g) / ($max - $min)) + 240,
                };
                $saturated = $max > 0 && ($max - $min) / $max > 0.35;
                $this->assertFalse($saturated && $hue >= 18 && $hue <= 45, "État « $state » orangé en mode $theme (charte : jamais d’orange pour un état).");
            }
        }
    }

    /** @return array<string, string> jetons « --pc-* » résolus pour le thème. */
    private static function tokens(string $theme): array
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/design-system.css');
        preg_match('/(?<![\]\w-]):root\s*\{(.*?)\}/s', $css, $light);
        $tokens = self::declarations($light[1]);
        if ($theme === 'dark') {
            preg_match('/:root\[data-theme="dark"\]\s*\{(.*?)\}/s', $css, $dark);
            $tokens = [...$tokens, ...self::declarations($dark[1])];
        }

        return $tokens;
    }

    private static function declarations(string $block): array
    {
        preg_match_all('/--pc-([\w-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn ($match) => [$match[1] => trim($match[2])])->all();
    }

    private static function color(array $tokens, string $name, int $depth = 0): string
    {
        if (str_starts_with($name, '#')) {
            return $name;
        }
        $value = $tokens[$name] ?? throw new \RuntimeException("Jeton --pc-$name absent.");
        if (preg_match('/^var\(--pc-([\w-]+)\)$/', $value, $reference) && $depth < 5) {
            return self::color($tokens, $reference[1], $depth + 1);
        }

        return $value;
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
    }

    private static function ratio(string $a, string $b): float
    {
        $luminance = fn (string $hex) => array_sum(array_map(
            fn (float $channel, float $weight) => $weight * ($channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4),
            self::rgb($hex), [0.2126, 0.7152, 0.0722],
        ));
        [$high, $low] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return ($high + 0.05) / ($low + 0.05);
    }
}
