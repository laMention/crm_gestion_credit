<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Models\User;

class CatalogueRolesPermissionsService
{
    /**
     * Roles and permissions are deliberately declared in code, not user-editable data.
     *
     * @return array<string, array{libelle: string, description: string, permissions: array<string, list<string>>}>
     */
    public function rolesDisponibles(): array
    {
        return [
            UserRole::ADMINISTRATEUR->value => [
                'libelle' => UserRole::ADMINISTRATEUR->label(),
                'description' => 'Gère les agences et les utilisateurs et consulte les données du réseau, sans opération financière.',
                'permissions' => [
                    'agences' => ['consulterTout', 'consulter', 'creer', 'modifier'],
                    'utilisateurs' => ['consulterTout', 'consulter', 'creer', 'modifier', 'activer', 'desactiver'],
                    'clients' => ['consulterTout', 'consulter'],
                    'comptes' => ['consulterTout', 'consulter'],
                    'transactions' => ['consulterTout', 'consulter'],
                    'credits' => ['consulterTout', 'consulter'],
                    'echeances' => ['consulterTout', 'consulter'],
                    'reportings' => ['consulterTout', 'consulter'],
                    'journaux_audit' => ['consulterTout', 'consulter'],
                ],
            ],
            UserRole::DIRECTION->value => [
                'libelle' => UserRole::DIRECTION->label(),
                'description' => 'Consulte les données du réseau en lecture seule et exporte les reportings.',
                'permissions' => [
                    'agences' => ['consulterTout', 'consulter'],
                    'utilisateurs' => ['consulterTout', 'consulter'],
                    'clients' => ['consulterTout', 'consulter'],
                    'comptes' => ['consulterTout', 'consulter'],
                    'transactions' => ['consulterTout', 'consulter'],
                    'credits' => ['consulterTout', 'consulter'],
                    'echeances' => ['consulterTout', 'consulter'],
                    'reportings' => ['consulterTout', 'consulter', 'exporterTout', 'exporter'],
                    'journaux_audit' => ['consulterTout', 'consulter'],
                ],
            ],
            UserRole::CHEF_AGENCE->value => [
                'libelle' => UserRole::CHEF_AGENCE->label(),
                'description' => 'Gère les validations et consulte les données de son agence.',
                'permissions' => [
                    'agences' => ['consulterTout', 'consulter'],
                    'utilisateurs' => ['consulterTout', 'consulter'],
                    'clients' => ['consulterTout', 'consulter', 'bloquer', 'debloquer'],
                    'comptes' => ['consulterTout', 'consulter', 'bloquer', 'debloquer', 'autoriserCloture'],
                    'transactions' => [
                        'consulterTout',
                        'consulter',
                        'consulterRetraitsEnAttente',
                        'validerRetrait',
                        'validerCorrection',
                    ],
                    'credits' => ['consulterTout', 'consulter', 'approuver', 'rejeter'],
                    'echeances' => ['consulterTout', 'consulter'],
                    'reportings' => ['consulterTout', 'consulter', 'exporterTout', 'exporter'],
                    'journaux_audit' => ['consulterTout', 'consulter'],
                ],
            ],
            UserRole::CONSEILLER->value => [
                'libelle' => UserRole::CONSEILLER->label(),
                'description' => 'Gère les dossiers clients, ouvre les comptes et prépare les demandes de crédit de son agence.',
                'permissions' => [
                    'agences' => ['consulterTout', 'consulter'],
                    'clients' => ['consulterTout', 'consulter', 'creer', 'modifier'],
                    'comptes' => ['consulterTout', 'consulter', 'ouvrir'],
                    'credits' => ['consulterTout', 'consulter', 'preparerDemande', 'modifierDemande', 'soumettreDemande'],
                    'echeances' => ['consulterTout', 'consulter'],
                ],
            ],
            UserRole::CAISSIER->value => [
                'libelle' => UserRole::CAISSIER->label(),
                'description' => 'Effectue les opérations de caisse autorisées dans son agence.',
                'permissions' => [
                    'agences' => ['consulterTout', 'consulter'],
                    'clients' => ['consulterTout', 'consulter'],
                    'comptes' => ['consulterTout', 'consulter', 'effectuerDepot', 'effectuerRetrait', 'effectuerVirementAssiste'],
                    'transactions' => ['consulterTout', 'consulter', 'soumettreCorrection'],
                    'credits' => ['consulterTout', 'consulter', 'enregistrerRemboursement'],
                    'echeances' => ['consulterTout', 'consulter'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, identifiant: string, libelle: string, description: string, module: string}>
     */
    public function permissionsDisponibles(): array
    {
        $catalogue = [
            'agences' => [
                'libelle' => 'Agences',
                'permissions' => [
                    'consulterTout' => 'Consulter toutes les agences',
                    'consulter' => 'Consulter une agence',
                    'creer' => 'Créer une agence',
                    'modifier' => 'Modifier une agence',
                ],
            ],
            'utilisateurs' => [
                'libelle' => 'Utilisateurs',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des utilisateurs',
                    'consulter' => 'Consulter un utilisateur',
                    'creer' => 'Créer un utilisateur',
                    'modifier' => 'Modifier un utilisateur',
                    'activer' => 'Activer un utilisateur',
                    'desactiver' => 'Désactiver un utilisateur',
                ],
            ],
            'clients' => [
                'libelle' => 'Clients',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des clients',
                    'consulter' => 'Consulter un client',
                    'creer' => 'Créer un client',
                    'modifier' => 'Modifier un client',
                    'bloquer' => 'Bloquer un client',
                    'debloquer' => 'Débloquer un client',
                ],
            ],
            'comptes' => [
                'libelle' => 'Comptes',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des comptes',
                    'consulter' => 'Consulter un compte',
                    'ouvrir' => 'Ouvrir un compte',
                    'effectuerDepot' => 'Effectuer un dépôt',
                    'effectuerRetrait' => 'Effectuer un retrait',
                    'effectuerVirementAssiste' => 'Effectuer un virement assisté',
                    'bloquer' => 'Bloquer un compte',
                    'debloquer' => 'Débloquer un compte',
                    'autoriserCloture' => 'Autoriser la clôture d\'un compte',
                ],
            ],
            'transactions' => [
                'libelle' => 'Transactions',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des transactions',
                    'consulter' => 'Consulter une transaction',
                    'consulterRetraitsEnAttente' => 'Consulter les retraits en attente de validation',
                    'validerRetrait' => 'Valider un retrait soumis à approbation',
                    'soumettreCorrection' => 'Soumettre une demande de correction',
                    'validerCorrection' => 'Valider une demande de correction',
                ],
            ],
            'credits' => [
                'libelle' => 'Crédits',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des crédits',
                    'consulter' => 'Consulter un crédit',
                    'preparerDemande' => 'Préparer une demande de crédit',
                    'modifierDemande' => 'Modifier une demande de crédit en instruction',
                    'soumettreDemande' => 'Soumettre une demande de crédit',
                    'approuver' => 'Approuver un crédit',
                    'rejeter' => 'Rejeter un crédit',
                    'enregistrerRemboursement' => 'Enregistrer un remboursement',
                ],
            ],
            'echeances' => [
                'libelle' => 'Échéances',
                'permissions' => [
                    'consulterTout' => 'Consulter la liste des échéances',
                    'consulter' => 'Consulter une échéance',
                ],
            ],
            'reportings' => [
                'libelle' => 'Reportings',
                'permissions' => [
                    'consulterTout' => 'Consulter les reportings',
                    'consulter' => 'Consulter un reporting',
                    'exporterTout' => 'Exporter les reportings',
                    'exporter' => 'Exporter un reporting',
                ],
            ],
            'journaux_audit' => [
                'libelle' => 'Journaux d\'audit',
                'permissions' => [
                    'consulterTout' => 'Consulter les journaux d\'audit',
                    'consulter' => 'Consulter une entrée du journal d\'audit',
                ],
            ],
        ];

        $permissions = [];

        foreach ($catalogue as $module => $donneesModule) {
            foreach ($donneesModule['permissions'] as $identifiant => $libelle) {
                $permissions[] = [
                    'id' => $module.'.'.$this->identifiantPermission($identifiant),
                    'identifiant' => $identifiant,
                    'libelle' => $libelle,
                    'description' => $this->descriptionPermission($identifiant),
                    'module' => $module,
                    'module_libelle' => $donneesModule['libelle'],
                ];
            }
        }

        return $permissions;
    }

    /**
     * @return array{
     *     id: string,
     *     identifiant: string,
     *     libelle: string,
     *     description: string,
     *     permissions: list<array{id: string, identifiant: string, libelle: string, description: string, module: string, module_libelle: string}>
     * }
     */
    public function roleDefini(UserRole $role): array
    {
        $donneesRole = $this->rolesDisponibles()[$role->value];

        return [
            'id' => $role->value,
            'identifiant' => $role->value,
            'libelle' => $donneesRole['libelle'],
            'description' => $donneesRole['description'],
            'permissions' => $this->permissionsDuRole($role),
        ];
    }

    /**
     * @return list<array{id: string, identifiant: string, libelle: string, description: string, module: string, module_libelle: string}>
     */
    public function permissionsDuRole(UserRole $role): array
    {
        $permissionsRole = $this->rolesDisponibles()[$role->value]['permissions'];

        return array_values(array_filter(
            $this->permissionsDisponibles(),
            fn (array $permission): bool => in_array(
                $permission['identifiant'],
                $permissionsRole[$permission['module']] ?? [],
                true
            )
        ));
    }

    /**
     * @return list<array{id: string, identifiant: string, libelle: string, description: string, module: string, module_libelle: string}>
     */
    public function permissionsDeUtilisateur(User $user): array
    {
        return $this->permissionsDuRole($user->role);
    }

    /**
     * @return array{id: string, identifiant: string, libelle: string, description: string, module: string, module_libelle: string}|null
     */
    public function permissionDefinie(string $id): ?array
    {
        foreach ($this->permissionsDisponibles() as $permission) {
            if ($permission['id'] === $id) {
                return $permission;
            }
        }

        return null;
    }

    private function identifiantPermission(string $identifiant): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $identifiant));
    }

    private function descriptionPermission(string $identifiant): string
    {
        return match ($identifiant) {
            'consulterTout' => 'Autorise la consultation de la collection dans le périmètre agence prévu pour le rôle.',
            'consulter' => 'Autorise la consultation d\'une ressource après vérification de son agence.',
            'creer' => 'Autorise la création de la ressource selon les règles de rattachement.',
            'modifier' => 'Autorise la modification de la ressource dans le périmètre autorisé.',
            'activer' => 'Autorise l\'activation d\'un compte utilisateur.',
            'desactiver' => 'Autorise la désactivation d\'un compte utilisateur.',
            'validerRetrait' => 'Autorise la validation d\'un retrait éligible hors de ses propres opérations.',
            'soumettreCorrection' => 'Autorise la soumission d\'une demande de correction d\'une transaction.',
            'validerCorrection' => 'Autorise la validation d\'une demande de correction hors de ses propres opérations.',
            'preparerDemande' => 'Autorise la préparation d\'une demande de crédit pour un compte de son agence.',
            'modifierDemande' => 'Autorise la modification d\'une demande de crédit en instruction créée par le conseiller.',
            'soumettreDemande' => 'Autorise la soumission d\'une demande de crédit créée par le conseiller.',
            'approuver' => 'Autorise l\'approbation d\'un crédit de son agence, sauf une demande personnelle.',
            'rejeter' => 'Autorise le rejet d\'un crédit de son agence, sauf une demande personnelle.',
            'exporterTout' => 'Autorise l\'export des reportings dans le périmètre attribué au rôle.',
            default => 'Autorise l\'action « '.$identifiant.' » dans le périmètre défini pour le rôle.',
        };
    }
}
