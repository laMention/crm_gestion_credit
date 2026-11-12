<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Client::query()->with('comptes')->orderBy('code_client')->get() as $client) {
            $compteCourant = $client->comptes->firstWhere('type_compte', 'courant');
            $compteEpargne = $client->comptes->firstWhere('type_compte', 'epargne');
            $compteCredit = $client->comptes->firstWhere('type_compte', 'credit');

            if (! $compteCourant || ! $compteEpargne || ! $compteCredit) {
                throw new \RuntimeException(
                    "Les comptes de demonstration manquent pour le client {$client->code_client}."
                );
            }

            $caissier = User::query()
                ->where('agence_id', $client->agence_id)
                ->where('role', 'caissier')
                ->orderBy('email')
                ->firstOrFail();
            $validateur = User::query()
                ->where('agence_id', $client->agence_id)
                ->where('role', 'chef_agence')
                ->orderBy('email')
                ->firstOrFail();

            $creerTransaction = function (
                string $suffixe,
                string $type,
                float $montant,
                ?int $compteSourceId,
                ?int $compteDestinataireId,
                $date
            ) use ($client, $caissier, $validateur): void {
                $reference = sprintf(
                    'TRX-%s-%s',
                    $client->code_client,
                    $suffixe
                );

                Transaction::updateOrCreate(
                    ['reference_transaction' => $reference],
                    [
                        'agence_id' => $client->agence_id,
                        'client_id' => $client->id,
                        'user_id' => $caissier->id,
                        'type_transaction' => $type,
                        'montant' => $montant,
                        'devise' => 'XOF',
                        'date_transaction' => $date,
                        'compte_source_id' => $compteSourceId,
                        'compte_destinataire_id' => $compteDestinataireId,
                        'statut_transaction' => 'terminee',
                        'valide_par' => $validateur->id,
                        'date_validation' => $date->copy()->addMinutes(5),
                        'cle_idempotence' => 'DEMO-'.$reference,
                        'motif' => 'Operation de demonstration',
                        'metadata' => ['source' => 'demo'],
                    ]
                );
            };

            $creerTransaction(
                'DEP',
                'depot',
                200000,
                null,
                $compteCourant->id,
                today()->setTime(9, 0)
            );
            $creerTransaction(
                'RET',
                'retrait',
                50000,
                $compteCourant->id,
                null,
                today()->setTime(10, 0)
            );
            $creerTransaction(
                'VIR',
                'virement',
                75000,
                $compteCourant->id,
                $compteEpargne->id,
                today()->setTime(11, 0)
            );
            $creerTransaction(
                'DEB',
                'deboursement',
                (float) $compteCredit->solde,
                null,
                $compteCredit->id,
                today()->subMonths(3)->setTime(12, 0)
            );

            $credit = $compteCredit->credit()->firstOrFail();

            foreach (
                $credit->echeances()
                    ->whereIn('statut_echeance_credit', ['paye', 'partiellement_paye'])
                    ->orderBy('numero_echeance')
                    ->get() as $echeance
            ) {
                $datePaiement = $echeance->date_paiement
                    ->copy()
                    ->setTime(14, 0);

                $creerTransaction(
                    sprintf('RMB%02d', $echeance->numero_echeance),
                    'remboursement',
                    (float) $echeance->montant_paye,
                    $compteCourant->id,
                    $compteCredit->id,
                    $datePaiement
                );
            }
        }
    }
}
