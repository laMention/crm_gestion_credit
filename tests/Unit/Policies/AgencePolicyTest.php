<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\User;
use App\Policies\AgencePolicy;
use App\Services\Authorization\AccesAgenceService;
use Illuminate\Auth\Access\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgencePolicyTest extends TestCase
{
    public function test_administrators_and_direction_have_network_read_access(): void
    {
        $policy = new AgencePolicy(new AccesAgenceService);

        $this->assertTrue($policy->consulterTout($this->utilisateur(UserRole::ADMINISTRATEUR)));
        $this->assertTrue($policy->consulterTout($this->utilisateur(UserRole::DIRECTION)));
    }

    public function test_staff_without_an_agency_cannot_list_agencies(): void
    {
        $policy = new AgencePolicy(new AccesAgenceService);

        $this->assertFalse($policy->consulterTout($this->utilisateur(UserRole::CONSEILLER)));
    }

    public function test_users_can_consult_only_agencies_in_their_scope(): void
    {
        $policy = new AgencePolicy(new AccesAgenceService);
        $utilisateur = $this->utilisateur(UserRole::CHEF_AGENCE, 12);
        $agenceLocale = $this->agence(12);
        $agenceAutre = $this->agence(13);

        $this->assertTrue($policy->consulter($utilisateur, $agenceLocale));

        $denegation = $policy->consulter($utilisateur, $agenceAutre);
        $this->assertInstanceOf(Response::class, $denegation);
        $this->assertSame(404, $denegation->status());
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_create_or_modify_agencies(
        UserRole $role
    ): void {
        $policy = new AgencePolicy(new AccesAgenceService);
        $utilisateur = $this->utilisateur($role, 12);
        $agence = $this->agence(12);

        $this->assertFalse($policy->creer($utilisateur));
        $this->assertFalse($policy->modifier($utilisateur, $agence));
    }

    public function test_administrator_can_create_and_modify_agencies(): void
    {
        $policy = new AgencePolicy(new AccesAgenceService);
        $administrateur = $this->utilisateur(UserRole::ADMINISTRATEUR);

        $this->assertTrue($policy->creer($administrateur));
        $this->assertTrue($policy->modifier($administrateur, $this->agence(12)));
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function nonAdministratorRoles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            if ($role !== UserRole::ADMINISTRATEUR) {
                $roles[$role->value] = [$role];
            }
        }

        return $roles;
    }

    private function utilisateur(UserRole $role, ?int $agenceId = null): User
    {
        $utilisateur = new User;
        $utilisateur->forceFill([
            'role' => $role,
            'agence_id' => $agenceId,
        ]);

        return $utilisateur;
    }

    private function agence(int $id): Agence
    {
        $agence = new Agence;
        $agence->setAttribute('id', $id);

        return $agence;
    }
}
