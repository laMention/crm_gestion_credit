<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompteResource extends JsonResource
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
            'numero_compte' => $this->numero_compte,
            'type_compte' => $this->type_compte,
            'solde' => $this->solde,
            'solde_initial' => $this->solde_initial,
            'devise' => $this->devise,
            'date_ouverture' => $this->date_ouverture?->toDateString(),
            'date_cloture' => $this->date_cloture?->toDateString(),
            'statut_compte' => $this->statut_compte,
            'client' => $this->whenLoaded('client', fn (): array => [
                'uuid' => $this->client->uuid,
                'code_client' => $this->client->code_client,
                'nom_client' => $this->client->nom_client,
                'prenom_client' => $this->client->prenom_client,
            ]),
            'agence' => $this->whenLoaded('agence', fn (): array => [
                'uuid' => $this->agence->uuid,
                'code' => $this->agence->code_agence,
                'nom' => $this->agence->nom_agence,
            ]),
            'cree_le' => $this->created_at,
        ];
    }
}
