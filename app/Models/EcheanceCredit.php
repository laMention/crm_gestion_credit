<?php

namespace App\Models;

use App\Services\Authorization\AccesAgenceService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'credit_id',
    'numero_echeance',
    'date_echeance',
    'capital',
    'interet',
    'montant_echeance',
    'montant_paye',
    'date_paiement',
    'statut_echeance_credit',
])]
class EcheanceCredit extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'echeance_credits';

    protected function casts(): array
    {
        return [
            'numero_echeance' => 'integer',
            'date_echeance' => 'date',
            'capital' => 'decimal:2',
            'interet' => 'decimal:2',
            'montant_echeance' => 'decimal:2',
            'montant_paye' => 'decimal:2',
            'date_paiement' => 'date',
            'statut_echeance_credit' => 'string',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    #[Scope]
    protected function accessiblePar(Builder $query, User $utilisateur): void
    {
        app(AccesAgenceService::class)->filtrerParAgence(
            $utilisateur,
            $query,
            'agence_id',
            'credit.compte'
        );
    }
}
