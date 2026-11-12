<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Credit;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class ReportingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $typesTransaction = [
            'depot' => 'total_depots',
            'retrait' => 'total_retraits',
            'virement' => 'total_virements',
            'deboursement' => 'total_deboursements',
            'remboursement' => 'total_remboursements',
        ];

        foreach (Agence::query()->orderBy('code_agence')->get() as $agence) {
            $transactions = Transaction::query()
                ->where('agence_id', $agence->id)
                ->whereDate('date_transaction', today())
                ->get();
            $reporting = [
                'nombre_depots' => 0,
                'nombre_retraits' => 0,
                'nombre_virements' => 0,
                'nombre_credits_debourses' => 0,
                'nombre_remboursements' => 0,
                'total_depots' => 0,
                'total_retraits' => 0,
                'total_virements' => 0,
                'total_deboursements' => 0,
                'total_remboursements' => 0,
            ];

            foreach ($typesTransaction as $type => $totalField) {
                $typedTransactions = $transactions->where('type_transaction', $type);
                $reporting[$totalField] = $typedTransactions->sum('montant');

                $countField = match ($type) {
                    'depot' => 'nombre_depots',
                    'retrait' => 'nombre_retraits',
                    'virement' => 'nombre_virements',
                    'deboursement' => 'nombre_credits_debourses',
                    'remboursement' => 'nombre_remboursements',
                };
                $reporting[$countField] = $typedTransactions->count();
            }

            $creditsEnCours = Credit::query()
                ->whereHas('compte', fn ($query) => $query->where('agence_id', $agence->id))
                ->whereIn('statut', ['debourse', 'en_remboursement', 'en_retard'])
                ->get();

            $reporting['agence_id'] = $agence->id;
            $reporting['date_reporting'] = today();
            $reporting['nombre_nouveaux_clients'] = $agence->clients()
                ->whereDate('created_at', today())
                ->count();
            $reporting['nombre_credits_en_cours'] = $creditsEnCours->count();
            $reporting['encours_credits'] = $creditsEnCours->sum('capital_restant_du');
            $reporting['genere_le'] = now();
            $reporting['statut_generation'] = 'termine';

            $agence->reportings()->updateOrCreate(
                ['date_reporting' => today()],
                $reporting
            );
        }
    }
}
