<?php

namespace App\Enums;

enum ConfigurationFlowType: string
{
    case InitialConfiguration = 'initial-configuration';
    case NewOrganization = 'new-organization';
    case NewMission = 'new-mission';
    case NewProject = 'new-project';
    case NewSite = 'new-site';
    case NewUser = 'new-user';

    public function startStep(): int
    {
        return match ($this) {
            self::InitialConfiguration => 1,
            self::NewOrganization => 1,
            self::NewMission => 2,
            self::NewProject => 3,
            self::NewSite => 10,
            self::NewUser => 11,
        };
    }

    public function initiallyCompletedSteps(): array
    {
        return $this->startStep() === 1
            ? []
            : range(1, $this->startStep() - 1);
    }

    public function label(): string
    {
        return match ($this) {
            self::InitialConfiguration => 'Configuration initiale',
            self::NewOrganization => 'Ajouter une organisation',
            self::NewMission => 'Ajouter une mission',
            self::NewProject => 'Ajouter un projet',
            self::NewSite => 'Ajouter un site',
            self::NewUser => 'Ajouter un utilisateur',
        };
    }
}
