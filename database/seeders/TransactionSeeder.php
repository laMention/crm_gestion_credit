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

            $referencesRemboursementsAttendues = [];
            foreach (
                $credit->echeances()
                    ->where('montant_paye', '>', 0)
                    ->orderBy('numero_echeance')
                    ->get() as $echeance
            ) {
                $datePaiement = $echeance->date_paiement
                    ->copy()
                    ->setTime(14, 0);
                $montantCentimes = (int) round((float) $echeance->montant_paye * 100);
                $estPartiel = $montantCentimes < (int) round(
                    (float) $echeance->montant_echeance * 100
                );

                if ($estPartiel) {
                    $premierPaiement = intdiv($montantCentimes, 2);
                    $paiements = [
                        [sprintf('RMB%02dA', $echeance->numero_echeance), $premierPaiement, $datePaiement->copy()->subDays(3)],
                        [sprintf('RMB%02dB', $echeance->numero_echeance), $montantCentimes - $premierPaiement, $datePaiement],
                    ];
                } else {
                    $paiements = [[
                        sprintf('RMB%02d', $echeance->numero_echeance),
                        $montantCentimes,
                        $datePaiement,
                    ]];
                }

                foreach ($paiements as [$suffixe, $montant, $date]) {
                    $reference = sprintf('TRX-%s-%s', $client->code_client, $suffixe);
                    $referencesRemboursementsAttendues[] = $reference;
                    $creerTransaction(
                        $suffixe,
                        'remboursement',
                        $montant / 100,
                        $compteCourant->id,
                        null,
                        $date->copy()->setTime(14, 0)
                    );
                }
            }

            $prefixeRemboursement = sprintf('TRX-%s-RMB', $client->code_client);
            $transactionsObsoletes = Transaction::query()
                ->where('client_id', $client->id)
                ->where('compte_source_id', $compteCourant->id)
                ->where('type_transaction', 'remboursement')
                ->where('cle_idempotence', 'like', 'DEMO-'.$prefixeRemboursement.'%')
                ->when(
                    $referencesRemboursementsAttendues !== [],
                    fn ($requete) => $requete->whereNotIn(
                        'reference_transaction',
                        $referencesRemboursementsAttendues
                    )
                )
                ->when(
                    $referencesRemboursementsAttendues === [],
                    fn ($requete) => $requete->whereNotNull('id')
                )
                ->get();

            foreach ($transactionsObsoletes as $transactionObsolete) {
                $transactionObsolete->remboursementsEcheances()->delete();
                $transactionObsolete->forceDelete();
            }
        }
    }
}
