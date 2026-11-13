<?php

namespace App\Models;

use App\Services\Authorization\AccesAgenceService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'compte_id',
    'conseiller_client_id',
    'valide_par',
    'montant_demande',
    'montant_accorde',
    'taux_interet_annuel',
    'duree_mois',
    'mensualite',
    'capital_restant_du',
    'montant_total_interets',
    'date_demande',
    'date_approbation',
    'date_deboursement',
    'date_debut_remboursement',
    'date_fin_prevue',
    'statut',
    'motif_rejet',
    'observations',
])]
class Credit extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'montant_demande' => 'decimal:2',
            'montant_accorde' => 'decimal:2',
            'taux_interet_annuel' => 'decimal:4',
            'mensualite' => 'decimal:2',
            'capital_restant_du' => 'decimal:2',
            'montant_total_interets' => 'decimal:2',
            'date_demande' => 'date',
            'date_approbation' => 'date',
            'date_deboursement' => 'date',
            'date_debut_remboursement' => 'date',
            'date_fin_prevue' => 'date',
            'duree_mois' => 'integer',
            'statut' => 'string',
            'deleted_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class);
    }

    public function conseillerClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conseiller_client_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(EcheanceCredit::class);
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
            'compte'
        );
    }
}
