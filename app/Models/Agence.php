<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code_agence',
    'nom_agence',
    'adresse_agence',
    'ville_agence',
    'telephone_agence',
    'email_agence',
    'actif',
])]
class Agence extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
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

    public function comptes(): HasMany
    {
        return $this->hasMany(Compte::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reportings(): HasMany
    {
        return $this->hasMany(Reporting::class);
    }

    public function credits(): HasManyThrough
    {
        return $this->hasManyThrough(Credit::class, Compte::class);
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
