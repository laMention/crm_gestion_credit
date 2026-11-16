<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'code_agence' => $this->code_agence,
            'nom_agence' => $this->nom_agence,
            'adresse_agence' => $this->adresse_agence,
            'ville_agence' => $this->ville_agence,
            'telephone_agence' => $this->telephone_agence,
            'email_agence' => $this->email_agence,
            'actif' => $this->actif,
            'cree_le' => $this->created_at,
            'modifie_le' => $this->updated_at,
        ];
    }
}
