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
    'reference_transaction',
    'agence_id',
    'client_id',
    'user_id',
    'type_transaction',
    'montant',
    'devise',
    'date_transaction',
    'compte_source_id',
    'compte_destinataire_id',
    'statut_transaction',
    'valide_par',
    'date_validation',
    'cle_idempotence',
    'motif',
    'metadata',
])]
class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_transaction' => 'datetime',
            'date_validation' => 'datetime',
            'metadata' => 'array',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function compteSource(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_source_id');
    }

    public function compteDestinataire(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_destinataire_id');
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
