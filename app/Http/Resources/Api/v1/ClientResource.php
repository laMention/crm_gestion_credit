<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code_client' => $this->code_client,
            'nom_client' => $this->nom_client,
            'prenom_client' => $this->prenom_client,
            'adresse_client' => $this->adresse_client,
            'telephone_client' => $this->telephone_client,
            'ville_client' => $this->ville_client,
            'email_client' => $this->email_client,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            'sexe' => $this->sexe,
            'profession' => $this->profession,
            'statut' => $this->statut,
            'agence' => $this->whenLoaded('agence', fn (): array => [
                'uuid' => $this->agence->uuid,
                'code' => $this->agence->code_agence,
                'nom' => $this->agence->nom_agence,
            ]),
            'comptes_count' => $this->whenCounted('comptes'),
            'comptes' => $this->whenLoaded(
                'comptes',
                fn () => CompteResource::collection($this->comptes)
            ),
            'cree_le' => $this->created_at,
            'modifie_le' => $this->updated_at,
        ];
    }
}
