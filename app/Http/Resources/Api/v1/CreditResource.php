<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statut = $this->statut;
        if (
            (bool) $this->getAttribute('a_des_echeances_en_retard')
            && in_array($statut, ['debourse', 'en_remboursement', 'en_retard'], true)
        ) {
            $statut = 'en_retard';
        }

        return [
            'uuid' => $this->uuid,
            'statut' => $statut,
            'montant_demande' => $this->montant_demande,
            'montant_accorde' => $this->montant_accorde,
            'taux_interet_annuel' => $this->taux_interet_annuel,
            'duree_mois' => $this->duree_mois,
            'mensualite' => $this->mensualite,
            'capital_restant_du' => $this->capital_restant_du,
            'montant_total_interets' => $this->montant_total_interets,
            'date_demande' => $this->date_demande?->toDateString(),
            'date_approbation' => $this->date_approbation?->toDateString(),
            'date_deboursement' => $this->date_deboursement?->toDateString(),
            'date_debut_remboursement' => $this->date_debut_remboursement?->toDateString(),
            'date_fin_prevue' => $this->date_fin_prevue?->toDateString(),
            'motif_rejet' => $this->motif_rejet,
            'observations' => $this->observations,
            'compte' => $this->whenLoaded('compte', fn (): array => [
                'uuid' => $this->compte->uuid,
                'numero_compte' => $this->compte->numero_compte,
                'type_compte' => $this->compte->type_compte,
                'devise' => $this->compte->devise,
                'client' => $this->compte->client ? [
                    'uuid' => $this->compte->client->uuid,
                    'code_client' => $this->compte->client->code_client,
                    'nom_client' => $this->compte->client->nom_client,
                    'prenom_client' => $this->compte->client->prenom_client,
                ] : null,
            ]),
            'conseiller' => $this->whenLoaded('conseillerClient', fn (): ?array => $this->conseillerClient
                ? [
                    'uuid' => $this->conseillerClient->uuid,
                    'nom' => $this->conseillerClient->nom,
                    'prenom' => $this->conseillerClient->prenom,
                ]
                : null),
            'validateur' => $this->whenLoaded('validateur', fn (): ?array => $this->validateur
                ? [
                    'uuid' => $this->validateur->uuid,
                    'nom' => $this->validateur->nom,
                    'prenom' => $this->validateur->prenom,
                ]
                : null),
            'echeances_count' => $this->whenCounted('echeances'),
        ];
    }
}
