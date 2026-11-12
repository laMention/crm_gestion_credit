<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agence_id',
    'date_reporting',
    'total_depots',
    'total_retraits',
    'total_virements',
    'total_deboursements',
    'total_remboursements',
    'nombre_depots',
    'nombre_retraits',
    'nombre_virements',
    'nombre_credits_debourses',
    'nombre_remboursements',
    'nombre_nouveaux_clients',
    'nombre_credits_en_cours',
    'encours_credits',
    'genere_le',
    'statut_generation',
])]
class Reporting extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'date_reporting' => 'date',
            'total_depots' => 'decimal:2',
            'total_retraits' => 'decimal:2',
            'total_virements' => 'decimal:2',
            'total_deboursements' => 'decimal:2',
            'total_remboursements' => 'decimal:2',
            'nombre_depots' => 'integer',
            'nombre_retraits' => 'integer',
            'nombre_virements' => 'integer',
            'nombre_credits_debourses' => 'integer',
            'nombre_remboursements' => 'integer',
            'nombre_nouveaux_clients' => 'integer',
            'nombre_credits_en_cours' => 'integer',
            'encours_credits' => 'decimal:2',
            'genere_le' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }
}
