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
            $profil = $creditIndex % 6;
            $montantCapital = (float) $credit->montant_accorde;
            $capitalMensuel = round($montantCapital / $credit->duree_mois, 2);
            $capitalRestant = $montantCapital;
            $capitalPaye = 0;
            $echeanceEnRetard = false;
            $echeancesSoldees = 0;
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
                $montantPaye = match (true) {
                    $profil === 0 => 0,
                    $profil === 1 => $montantEcheance,
                    $profil === 2 && $numero <= 2 => $montantEcheance,
                    $profil === 2 && $numero === 3 => round($montantEcheance / 2, 2),
                    $profil === 3 && $numero <= 2 => $montantEcheance,
                    $profil === 4 && $numero <= 4 => $montantEcheance,
                    $profil === 5 && $numero <= 2 => $montantEcheance,
                    $profil === 5 && $numero === 3 => round($montantEcheance / 2, 2),
                    default => 0,
                };
                $interetPaye = min($montantPaye, $interet);
                $capitalDeCetteEcheancePaye = max(0, $montantPaye - $interetPaye);
                $statut = match (true) {
                    $montantPaye >= $montantEcheance => 'paye',
                    $montantPaye > 0 && $dateEcheance->isBefore(today()) => 'en_retard',
                    $montantPaye > 0 => 'partiellement_paye',
                    $dateEcheance->isBefore(today()) => 'en_retard',
                    default => 'a_payer',
                };
                $echeanceEnRetard = $echeanceEnRetard
                    || ($statut === 'en_retard' && $montantPaye < $montantEcheance);
                $echeancesSoldees += $statut === 'paye' ? 1 : 0;

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
                            ? ($statut === 'paye' ? $dateEcheance : today())
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
                'statut' => $echeancesSoldees === $credit->duree_mois
                    ? 'solde'
                    : ($echeanceEnRetard ? 'en_retard' : 'en_remboursement'),
            ]);
        }
    }
}
