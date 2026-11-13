<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcheanceCreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reste = $this->montantRestant();
        $statut = $this->statut_echeance_credit;

        if (
            $reste !== '0.00'
            && $statut !== 'annule'
            && $this->date_echeance?->isBefore(today())
        ) {
            $statut = 'en_retard';
        }

        return [
            'uuid' => $this->uuid,
            'numero_echeance' => $this->numero_echeance,
            'date_echeance' => $this->date_echeance?->toDateString(),
            'capital' => $this->capital,
            'interet' => $this->interet,
            'montant_echeance' => $this->montant_echeance,
            'montant_paye' => $this->montant_paye,
            'reste_a_payer' => $reste,
            'date_paiement' => $this->date_paiement?->toDateString(),
            'statut_echeance_credit' => $statut,
            'credit' => $this->whenLoaded('credit', fn (): array => [
                'uuid' => $this->credit->uuid,
                'statut' => $this->credit->statut,
                'compte' => $this->credit->compte ? [
                    'uuid' => $this->credit->compte->uuid,
                    'numero_compte' => $this->credit->compte->numero_compte,
                ] : null,
            ]),
        ];
    }

    private function montantRestant(): string
    {
        $montantEcheance = $this->centimes((string) $this->montant_echeance);
        $montantPaye = $this->centimes((string) $this->montant_paye);
        $reste = max(0, $montantEcheance - $montantPaye);

        return sprintf('%d.%02d', intdiv($reste, 100), $reste % 100);
    }

    private function centimes(string $montant): int
    {
        [$unites, $decimales] = array_pad(explode('.', $montant, 2), 2, '0');

        return ((int) $unites * 100) + (int) str_pad($decimales, 2, '0');
    }
}
