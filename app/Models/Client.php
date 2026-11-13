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
    'agence_id',
    'code_client',
    'nom_client',
    'prenom_client',
    'adresse_client',
    'telephone_client',
    'ville_client',
    'email_client',
    'date_naissance',
    'lieu_naissance',
    'sexe',
    'type_piece_identite',
    'numero_piece_identite',
    'date_expiration_piece',
    'profession',
    'revenu_mensuel',
    'statut',
    'cree_par_user_id',
])]

class Client extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_expiration_piece' => 'date',
            'revenu_mensuel' => 'decimal:2',
            'deleted_at' => 'datetime',
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

    public function creerParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_user_id');
    }

    public function comptes(): HasMany
    {
        return $this->hasMany(Compte::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    #[Scope]
    protected function accessiblePar(Builder $query, User $utilisateur): void
    {
        app(AccesAgenceService::class)->filtrerParAgence(
            $utilisateur,
            $query
        );
    }
}
