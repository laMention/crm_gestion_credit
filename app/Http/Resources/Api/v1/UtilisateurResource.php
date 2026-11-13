<?php

namespace App\Http\Resources\Api\v1;

use App\Models\Agence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $agence = $this->whenLoaded('agence');

        return [
            'uuid' => $this->uuid,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'adresse' => $this->adresse,
            'telephone' => $this->telephone,
            'ville' => $this->ville,
            'email' => $this->email,
            'role' => $this->role->value,
            'actif' => $this->actif,
            'derniere_connexion_le' => $this->derniere_connexion_le,
            'agence' => $agence instanceof Agence ? [
                'uuid' => $agence->uuid,
                'code' => $agence->code_agence,
                'nom' => $agence->nom_agence,
                'ville' => $agence->ville_agence,
            ] : null,
            'cree_le' => $this->created_at,
            'modifie_le' => $this->updated_at,
        ];
    }
}
