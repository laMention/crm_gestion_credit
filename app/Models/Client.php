<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Agence;
use App\Models\User;
#[Fillable(['agence_id', 'code_client', 'nom_client', 'prenom_client', 'adresse_client', 'telephone_client', 'ville_client', 'email_client', 'date_naissance',
    'lieu_naissance', 'sexe', 'type_piece_identite', 'numero_piece_identite', 'date_expiration_piece', 'profession', 'revenu_mensuel', 'statut', 'cree_par_user_id'])]

class Client extends Model
{
    use HasUuids, softDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relation entre clients et agences
    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    // Relation entre clients et utilisateurs (users) qui ont créé le client
    public function creer_par_user()
    {
        return $this->belongsTo(User::class,'cree_par_user_id');
    }
}


           
