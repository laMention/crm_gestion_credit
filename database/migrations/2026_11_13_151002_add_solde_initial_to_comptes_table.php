<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comptes', function (Blueprint $table) {
            $table->decimal('solde_initial', 15, 2)->default(0)->after('solde');
        });

        DB::table('comptes')
            ->select(['id', 'solde'])
            ->orderBy('id')
            ->chunkById(200, function ($comptes): void {
                foreach ($comptes as $compte) {
                    $mouvements = DB::table('transactions')
                        ->select([
                            'compte_source_id',
                            'compte_destinataire_id',
                            'montant',
                        ])
                        ->where(function ($requete) use ($compte): void {
                            $requete
                                ->where('compte_source_id', $compte->id)
                                ->orWhere('compte_destinataire_id', $compte->id);
                        })
                        ->where('statut_transaction', 'terminee')
                        ->whereNotNull('date_transaction')
                        ->whereNotNull('date_validation')
                        ->whereNull('deleted_at')
                        ->cursor();

                    $mouvementsNetsEnCentimes = 0;

                    foreach ($mouvements as $mouvement) {
                        $montant = (string) $mouvement->montant;

                        if (is_float($mouvement->montant)) {
                            $montant = number_format($mouvement->montant, 2, '.', '');
                        }

                        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $montant)) {
                            throw new UnexpectedValueException(
                                'Un montant comptable est invalide.'
                            );
                        }

                        [$unites, $decimales] = array_pad(
                            explode('.', $montant, 2),
                            2,
                            '0'
                        );
                        $montantEnCentimes = ((int) $unites * 100)
                            + (int) str_pad($decimales, 2, '0');

                        if ((int) $mouvement->compte_destinataire_id === (int) $compte->id) {
                            $mouvementsNetsEnCentimes += $montantEnCentimes;
                        }

                        if ((int) $mouvement->compte_source_id === (int) $compte->id) {
                            $mouvementsNetsEnCentimes -= $montantEnCentimes;
                        }
                    }

                    $soldeCourant = (string) $compte->solde;

                    if (is_float($compte->solde)) {
                        $soldeCourant = number_format($compte->solde, 2, '.', '');
                    }

                    [$unites, $decimales] = array_pad(
                        explode('.', ltrim($soldeCourant, '-'), 2),
                        2,
                        '0'
                    );
                    $soldeEnCentimes = ((int) $unites * 100)
                        + (int) str_pad($decimales, 2, '0');

                    if (str_starts_with($soldeCourant, '-')) {
                        $soldeEnCentimes *= -1;
                    }

                    $soldeInitialEnCentimes = $soldeEnCentimes
                        - $mouvementsNetsEnCentimes;
                    $signe = $soldeInitialEnCentimes < 0 ? '-' : '';
                    $soldeInitialAbsolu = abs($soldeInitialEnCentimes);
                    $soldeInitial = sprintf(
                        '%s%d.%02d',
                        $signe,
                        intdiv($soldeInitialAbsolu, 100),
                        $soldeInitialAbsolu % 100
                    );

                    DB::table('comptes')
                        ->where('id', $compte->id)
                        ->update(['solde_initial' => $soldeInitial]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comptes', function (Blueprint $table) {
            $table->dropColumn('solde_initial');
        });
    }
};
