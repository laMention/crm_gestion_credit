<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Database\Seeder;

class CreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tauxAnnuel = 12.0;
        $dureeMois = 12;

        foreach (
            Compte::query()
                ->where('type_compte', 'credit')
                ->orderBy('numero_compte')
                ->get() as $index => $compte
        ) {
            $conseiller = User::query()
                ->where('agence_id', $compte->agence_id)
                ->where('role', 'conseiller')
                ->orderBy('email')
                ->firstOrFail();
            $validateur = User::query()
                ->where('agence_id', $compte->agence_id)
                ->where('role', 'chef_agence')
                ->orderBy('email')
                ->firstOrFail();

            $montant = 1200000 + ($index * 100000);
            $capitalMensuel = round($montant / $dureeMois, 2);
            $capitalRestant = $montant;
            $totalInterets = 0;

            for ($mois = 0; $mois < $dureeMois; $mois++) {
                $totalInterets += round(
                    $capitalRestant * ($tauxAnnuel / 100 / 12),
                    2
                );
                $capitalRestant -= $capitalMensuel;
            }

            $dateDebutRemboursement = today()
                ->startOfMonth()
                ->subMonths(2);
            $compte->update(['solde' => $montant]);

            Credit::updateOrCreate(
                ['compte_id' => $compte->id],
                [
                    'conseiller_client_id' => $conseiller->id,
                    'valide_par' => $validateur->id,
                    'montant_demande' => $montant,
                    'montant_accorde' => $montant,
                    'taux_interet_annuel' => $tauxAnnuel,
                    'duree_mois' => $dureeMois,
                    'mensualite' => round(
                        $capitalMensuel + ($totalInterets / $dureeMois),
                        2
                    ),
                    'capital_restant_du' => $montant,
                    'montant_total_interets' => $totalInterets,
                    'date_demande' => today()->subMonths(4),
                    'date_approbation' => today()->subMonths(3),
                    'date_deboursement' => today()->subMonths(3),
                    'date_debut_remboursement' => $dateDebutRemboursement,
                    'date_fin_prevue' => $dateDebutRemboursement
                        ->copy()
                        ->addMonths($dureeMois),
                    'statut' => 'en_remboursement',
                    'observations' => 'Credit de demonstration',
                ]
            );
        }
    }
}
