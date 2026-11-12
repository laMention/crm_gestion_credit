<?php

namespace Database\Seeders;

use App\Models\Credit;
use App\Models\EcheanceCredit;
use Illuminate\Database\Seeder;

class EcheanceCreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Credit::query()->orderBy('id')->get() as $creditIndex => $credit) {
            $montantCapital = (float) $credit->montant_accorde;
            $capitalMensuel = round($montantCapital / $credit->duree_mois, 2);
            $capitalRestant = $montantCapital;
            $capitalPaye = 0;
            $dateDebut = $credit->date_debut_remboursement->copy();

            for ($numero = 1; $numero <= $credit->duree_mois; $numero++) {
                $capital = $numero === $credit->duree_mois
                    ? $capitalRestant
                    : $capitalMensuel;
                $interet = round(
                    $capitalRestant
                        * ((float) $credit->taux_interet_annuel / 100 / 12),
                    2
                );
                $montantEcheance = round($capital + $interet, 2);
                $dateEcheance = $dateDebut->copy()->addMonths($numero - 1);
                $statut = match (true) {
                    $numero <= 2 => 'paye',
                    $numero === 3 && $creditIndex % 2 === 0 => 'en_retard',
                    $numero === 3 => 'partiellement_paye',
                    default => 'a_payer',
                };
                $montantPaye = match ($statut) {
                    'paye' => $montantEcheance,
                    'partiellement_paye' => round($montantEcheance / 2, 2),
                    default => 0,
                };
                $capitalDeCetteEcheancePaye = match ($statut) {
                    'paye' => $capital,
                    'partiellement_paye' => round($capital / 2, 2),
                    default => 0,
                };

                EcheanceCredit::updateOrCreate(
                    [
                        'credit_id' => $credit->id,
                        'numero_echeance' => $numero,
                    ],
                    [
                        'date_echeance' => $dateEcheance,
                        'capital' => $capital,
                        'interet' => $interet,
                        'montant_echeance' => $montantEcheance,
                        'montant_paye' => $montantPaye,
                        'date_paiement' => $montantPaye > 0
                            ? ($statut === 'partiellement_paye' ? today() : $dateEcheance)
                            : null,
                        'statut_echeance_credit' => $statut,
                    ]
                );

                $capitalRestant -= $capital;
                $capitalPaye += $capitalDeCetteEcheancePaye;
            }

            $credit->update([
                'capital_restant_du' => round(
                    $montantCapital - $capitalPaye,
                    2
                ),
            ]);
        }
    }
}
