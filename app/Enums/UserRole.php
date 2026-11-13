<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMINISTRATEUR = 'administrateur';
    case DIRECTION = 'direction';
    case CHEF_AGENCE = 'chef_agence';
    case CONSEILLER = 'conseiller';
    case CAISSIER = 'caissier';

    public function roleEchelleDuReseau(): bool
    {
        return in_array($this, [
            self::ADMINISTRATEUR,
            self::DIRECTION,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATEUR => 'Administrateur',
            self::DIRECTION => 'Direction',
            self::CHEF_AGENCE => 'Chef d\'agence',
            self::CONSEILLER => 'Conseiller',
            self::CAISSIER => 'Caissier',
        };
    }

    public function estPersonnelAgence(): bool
    {
        return in_array($this, [
            self::CHEF_AGENCE,
            self::CONSEILLER,
            self::CAISSIER,
        ], true);
    }
}
