<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'agence_id',
    'client_id',
    'numero_compte',
    'type_compte',
    'solde',
    'devise',
    'date_ouverture',
    'date_cloture',
    'statut_compte',
    'cree_par_user_id',
])]
class Compte extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'solde' => 'decimal:2',
            'date_ouverture' => 'date',
            'date_cloture' => 'date',
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

    public function creeParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_user_id');
    }

    public function credit(): HasOne
    {
        return $this->hasOne(Credit::class);
    }

    public function transactionsSource(): HasMany
    {
        return $this->hasMany(Transaction::class, 'compte_source_id');
    }

    public function transactionsDestinataire(): HasMany
    {
        return $this->hasMany(Transaction::class, 'compte_destinataire_id');
    }
}
