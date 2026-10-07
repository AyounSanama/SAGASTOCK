<?php

namespace App\Support;

/**
 * Texte toujours en UTF-8 : les accents (é, è, à, ç, ô…) restent corrects
 * quelle que soit l'origine du texte (formulaire, application mobile, fichier
 * CSV enregistré par Excel sous Windows en Windows-1252 / ISO-8859-1).
 */
final class Utf8
{
    private const BOM = "\xEF\xBB\xBF";

    /** Texte en UTF-8 valide, sans marque d'ordre d'octets (BOM). */
    public static function clean(string $value): string
    {
        if (str_starts_with($value, self::BOM)) {
            $value = substr($value, 3);
        }
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        // Encodage Windows (Excel français, Bloc-notes ancien) : conversion.
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        return mb_check_encoding($converted, 'UTF-8') ? $converted : mb_scrub($value, 'UTF-8');
    }

    /** Applique clean() à toutes les chaînes d'un tableau (récursif). */
    public static function cleanArray(array $values): array
    {
        array_walk_recursive($values, function (&$value): void {
            if (is_string($value)) {
                $value = self::clean($value);
            }
        });

        return $values;
    }
}
