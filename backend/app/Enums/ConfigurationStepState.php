<?php

namespace App\Enums;

enum ConfigurationStepState: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Valid = 'valid';
    case NeedsCorrection = 'needs_correction';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Non commencée',
            self::InProgress => 'En cours',
            self::Valid => 'Validée',
            self::NeedsCorrection => 'À corriger',
        };
    }
}
