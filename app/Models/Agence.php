<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Compte;
use App\Models\User;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Reporting;

#[Fillable(['code_agence', 'nom_agence', 'adresse_agence', 'ville_agence', 'telephone_agence', 'email_agence', 'actif'])]
class Agence extends Model
{
    use HasUuids,softDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
    
    // Relation entre agences et comptes
    public function comptes()
    {
        return $this->hasMany(Compte::class);
    }

    // Relation entre agence et utilisateurs (users)
    public function users(){        
        return $this->hasMany(User::class);
    }

    // Relation entre agence et clients
    public function clients(){
        return $this->hasMany(Client::class);
    }

    // Relation entre agence et transactions (credits)
    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    // Relation entre agence et reportings
    public function reportings(){
        return $this->hasMany(Reporting::class);
    }


    // Filtrage pour recuperer uniquement les agences actives
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }


}
