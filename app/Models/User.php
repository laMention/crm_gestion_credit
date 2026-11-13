<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\Authorization\AccesAgenceService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'agence_id',
    'nom',
    'prenom',
    'adresse',
    'telephone',
    'ville',
    'email',
    'password',
    'role',
    'actif',
    'derniere_connexion_le',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'derniere_connexion_le' => 'datetime',
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

    public function comptes(): HasMany
    {
        return $this->hasMany(Compte::class, 'cree_par_user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'cree_par_user_id');
    }

    public function transactionsValidees(): HasMany
    {
        return $this->hasMany(Transaction::class, 'valide_par');
    }

    public function transactionsEmises(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function creditsTraites(): HasMany
    {
        return $this->hasMany(Credit::class, 'conseiller_client_id');
    }

    public function creditsValides(): HasMany
    {
        return $this->hasMany(Credit::class, 'valide_par');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
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
