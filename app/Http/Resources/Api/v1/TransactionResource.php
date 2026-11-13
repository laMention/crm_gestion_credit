<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'reference_transaction' => $this->reference_transaction,
            'type_transaction' => $this->type_transaction,
            'montant' => $this->montant,
            'devise' => $this->devise,
            'date_transaction' => $this->date_transaction,
            'statut_transaction' => $this->statut_transaction,
            'motif' => $this->motif,
            'date_validation' => $this->date_validation,
            'agence' => $this->whenLoaded('agence', fn (): array => [
                'uuid' => $this->agence->uuid,
                'code' => $this->agence->code_agence,
                'nom' => $this->agence->nom_agence,
            ]),
            'client' => $this->whenLoaded('client', fn (): array => [
                'uuid' => $this->client->uuid,
                'code_client' => $this->client->code_client,
                'nom_client' => $this->client->nom_client,
                'prenom_client' => $this->client->prenom_client,
            ]),
            'utilisateur' => $this->whenLoaded('user', fn (): ?array => $this->user
                ? [
                    'uuid' => $this->user->uuid,
                    'nom' => $this->user->nom,
                    'prenom' => $this->user->prenom,
                ]
                : null),
            'validateur' => $this->whenLoaded('validateur', fn (): ?array => $this->validateur
                ? [
                    'uuid' => $this->validateur->uuid,
                    'nom' => $this->validateur->nom,
                    'prenom' => $this->validateur->prenom,
                ]
                : null),
            'compte_source' => $this->whenLoaded('compteSource', fn (): ?array => $this->compteSource
                ? [
                    'uuid' => $this->compteSource->uuid,
                    'numero_compte' => $this->compteSource->numero_compte,
                    'type_compte' => $this->compteSource->type_compte,
                ]
                : null),
            'compte_destinataire' => $this->whenLoaded(
                'compteDestinataire',
                fn (): ?array => $this->compteDestinataire
                    ? [
                        'uuid' => $this->compteDestinataire->uuid,
                        'numero_compte' => $this->compteDestinataire->numero_compte,
                        'type_compte' => $this->compteDestinataire->type_compte,
                    ]
                    : null
            ),
        ];
    }
}
