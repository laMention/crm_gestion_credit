<?php

namespace App\Models;

use App\Models\Agence;
use App\Models\Compte;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Credit;
use App\Models\AuditLog;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;


#[Fillable(['nom', 'prenom', 'password','adresse','telephone','email','agence_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasRoles, HasApiTokens, softDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // relation entre utilisateurs et agences
    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    // relation entre utilisateurs et comptes
    public function comptes()
    {
        return $this->hasMany(Compte::class, 'cree_par_user_id');
    }

    // relation entre utilisateurs et clients
    public function clients()
    {
        return $this->hasMany(Client::class, 'cree_par_user_id');
    }

    // Relation entre utilisateurs et transaction (utilisateur qui valide l'operation)
    public function transaction_valide_par()
    {
        return $this->hasMany(Transaction::class, 'valide_par');
    }

    // Relation entre utilisateurs et transaction (utilisateur qui effectue l'operation)
    public function emise_par()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // Relation entre utilisateurs et credits (utilisateur qui valide le creditvali)
    public function credit_traite_par()
    {
        return $this->hasMany(Credit::class, 'conseiller_client_id');
    }

    // Relation entre utilisateurs et credits (utilisateur qui valide le creditvali)
    public function credit_valide_par()
    {
        return $this->hasMany(Credit::class, 'valide_par');
    }

    // Relation entre utilisateurs et les logs d'audits
    public function audit_logs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
