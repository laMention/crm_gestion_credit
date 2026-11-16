<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GestionAgencesService
{
    private const CHAMPS_AUDIT = [
        'code_agence',
        'nom_agence',
        'adresse_agence',
        'ville_agence',
        'telephone_agence',
        'email_agence',
        'actif',
    ];

    public function __construct(
        private readonly AuditLogService $journalAudit
    ) {}

    /**
     * @param  array{recherche?: string, actif?: bool|string, per_page?: int}  $filtres
     */
    public function lister(User $demandeur, array $filtres): LengthAwarePaginator
    {
        return Agence::query()
            ->accessiblePar($demandeur)
            ->when(
                isset($filtres['recherche']) && $filtres['recherche'] !== '',
                function (Builder $requete) use ($filtres): void {
                    $terme = '%'.$filtres['recherche'].'%';
                    $requete->where(function (Builder $recherche) use ($terme): void {
                        $recherche
                            ->where('code_agence', 'like', $terme)
                            ->orWhere('nom_agence', 'like', $terme)
                            ->orWhere('ville_agence', 'like', $terme)
                            ->orWhere('telephone_agence', 'like', $terme)
                            ->orWhere('email_agence', 'like', $terme);
                    });
                }
            )
            ->when(
                array_key_exists('actif', $filtres),
                fn (Builder $requete): Builder => $requete->where(
                    'actif',
                    $filtres['actif']
                )
            )
            ->orderBy('nom_agence')
            ->orderBy('code_agence')
            ->orderBy('id')
            ->paginate($filtres['per_page'] ?? 10);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function creer(User $acteur, array $donnees): Agence
    {
        return DB::transaction(function () use ($acteur, $donnees): Agence {
            $agence = Agence::query()->create([
                ...$donnees,
                'actif' => true,
            ]);
            $this->journalAudit->enregistrer(
                $acteur,
                'agence.cree',
                'agences',
                'agence',
                $agence->id,
                [],
                $agence->only(self::CHAMPS_AUDIT),
                agenceId: $agence->id
            );

            return $agence;
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function modifier(User $acteur, Agence $agence, array $donnees): Agence
    {
        return DB::transaction(function () use ($acteur, $agence, $donnees): Agence {
            $champsModifies = array_keys($donnees);
            $anciennesValeurs = $agence->only($champsModifies);
            $agence->fill($donnees)->save();

            $this->journalAudit->enregistrer(
                $acteur,
                'agence.modifiee',
                'agences',
                'agence',
                $agence->id,
                $anciennesValeurs,
                $agence->only($champsModifies),
                agenceId: $agence->id
            );

            return $agence->refresh();
        });
    }
}
