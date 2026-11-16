<?php

namespace Database\Seeders;

use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\RemboursementEcheance;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemboursementEcheanceSeeder extends Seeder
{
    public function run(): void
    {
        $credits = Credit::query()
            ->where('observations', 'Credit de demonstration')
            ->with(['compte.client', 'echeances'])
            ->orderBy('id')
            ->get();

        foreach ($credits as $credit) {
            if (! $credit->compte?->client) {
                throw new RuntimeException(
                    "Le compte ou le client manque pour le credit de demonstration {$credit->uuid}."
                );
            }

            DB::transaction(function () use ($credit): void {
                $client = $credit->compte->client;
                $prefixe = sprintf('TRX-%s-RMB', $client->code_client);
                $clePrefixe = 'DEMO-'.$prefixe.'%';

                RemboursementEcheance::query()
                    ->whereHas(
                        'transaction',
                        fn ($requete) => $requete->where(
                            'cle_idempotence',
                            'like',
                            $clePrefixe
                        )
                    )
                    ->delete();

                $echeances = $credit->echeances->keyBy('numero_echeance');
                $cumuls = [];
                foreach ($echeances as $echeance) {
                    $cumuls[$echeance->id] = [
                        'montant' => 0,
                        'capital' => 0,
                        'interet' => 0,
                        'date' => null,
                    ];
                }

                $transactions = Transaction::query()
                    ->where('client_id', $client->id)
                    ->where('type_transaction', 'remboursement')
                    ->where('statut_transaction', 'terminee')
                    ->where('cle_idempotence', 'like', $clePrefixe)
                    ->orderBy('date_transaction')
                    ->orderBy('id')
                    ->get();

                foreach ($transactions as $transaction) {
                    if (! preg_match('/-RMB(\d+)[A-Z]?$/', $transaction->reference_transaction, $match)) {
                        throw new RuntimeException(
                            "La reference de remboursement {$transaction->reference_transaction} ne designe pas une echeance."
                        );
                    }

                    $echeance = $echeances->get((int) $match[1]);
                    if (! $echeance instanceof EcheanceCredit) {
                        throw new RuntimeException(
                            "Aucune echeance ne correspond a la transaction {$transaction->reference_transaction}."
                        );
                    }

                    $montant = $this->centimes((string) $transaction->montant);
                    $montantExigible = $this->centimes((string) $echeance->montant_echeance);
                    $reste = $montantExigible - $cumuls[$echeance->id]['montant'];
                    if ($montant <= 0 || $montant > $reste) {
                        throw new RuntimeException(
                            "Les remboursements depassent le montant de l'echeance {$echeance->numero_echeance}."
                        );
                    }

                    $interetRestant = $this->centimes((string) $echeance->interet)
                        - $cumuls[$echeance->id]['interet'];
                    $interet = min($montant, max(0, $interetRestant));
                    $capital = $montant - $interet;

                    RemboursementEcheance::query()->create([
                        'transaction_id' => $transaction->id,
                        'echeance_credit_id' => $echeance->id,
                        'montant' => $this->formater($montant),
                        'capital' => $this->formater($capital),
                        'interet' => $this->formater($interet),
                    ]);

                    $cumuls[$echeance->id]['montant'] += $montant;
                    $cumuls[$echeance->id]['capital'] += $capital;
                    $cumuls[$echeance->id]['interet'] += $interet;
                    $cumuls[$echeance->id]['date'] = $transaction->date_transaction?->toDateString();
                }

                $capitalRembourse = 0;
                $toutesSoldees = true;
                $enRetard = false;
                foreach ($echeances as $echeance) {
                    $cumul = $cumuls[$echeance->id];
                    if ($cumul['montant'] !== $this->centimes((string) $echeance->montant_paye)) {
                        throw new RuntimeException(
                            "Le total des transactions ne correspond pas au montant paye de l'echeance {$echeance->numero_echeance}."
                        );
                    }

                    $exigible = $this->centimes((string) $echeance->montant_echeance);
                    $soldee = $cumul['montant'] === $exigible;
                    $estEnRetard = ! $soldee && $echeance->date_echeance->isBefore(today());
                    $statut = match (true) {
                        $soldee => 'paye',
                        $estEnRetard => 'en_retard',
                        $cumul['montant'] > 0 => 'partiellement_paye',
                        default => 'a_payer',
                    };

                    $echeance->forceFill([
                        'montant_paye' => $this->formater($cumul['montant']),
                        'date_paiement' => $cumul['date'],
                        'statut_echeance_credit' => $statut,
                    ])->save();

                    $capitalRembourse += $cumul['capital'];
                    $toutesSoldees = $toutesSoldees && $soldee;
                    $enRetard = $enRetard || $estEnRetard;
                }

                $capitalInitial = $this->centimes((string) $credit->montant_accorde);
                if ($capitalRembourse > $capitalInitial) {
                    throw new RuntimeException(
                        "Le capital remboursé depasse le montant accordé du credit {$credit->uuid}."
                    );
                }

                $credit->forceFill([
                    'capital_restant_du' => $this->formater($capitalInitial - $capitalRembourse),
                    'statut' => $toutesSoldees
                        ? 'solde'
                        : ($enRetard ? 'en_retard' : 'en_remboursement'),
                ])->save();
            });
        }
    }

    private function centimes(string $montant): int
    {
        [$unites, $decimales] = array_pad(explode('.', $montant, 2), 2, '0');

        return ((int) $unites * 100) + (int) str_pad($decimales, 2, '0');
    }

    private function formater(int $centimes): string
    {
        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}
