<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierUtilisateurRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $utilisateur = $this->recupererUtilisateurCible();

        if (! $utilisateur instanceof User
            || ! ($this->user()?->can('modifier', $utilisateur) ?? false)
        ) {
            return false;
        }

        $demandeur = $this->user();

        if ($demandeur instanceof User && $demandeur->is($utilisateur)) {
            $roleChange = $this->exists('role')
                && $this->input('role') !== $utilisateur->role->value;
            $agenceChange = $this->exists('agence_id')
                && (string) $this->input('agence_id') !== (string) $utilisateur->agence_id;

            if ($roleChange || $agenceChange) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $utilisateur = $this->recupererUtilisateurCible();
        $role = $this->roleUtilisateurCible();
        $regleAgence = Rule::exists('agences', 'id')->where('actif', true);

        if (
            $utilisateur instanceof User
            && (! $role?->estPersonnelAgence() || ! $this->roleChange($utilisateur))
            && $this->input('agence_id') !== null
            && (string) $this->input('agence_id') === (string) $utilisateur->agence_id
        ) {
            $regleAgence = Rule::exists('agences', 'id');
        }

        return [
            'agence_id' => [
                'nullable',
                'integer',
                $regleAgence,
                Rule::requiredIf(fn (): bool => $role?->estPersonnelAgence()
                    && (
                        $this->roleChange($utilisateur)
                        || ($this->exists('agence_id') && $this->input('agence_id') === null)
                        || ($utilisateur instanceof User && $utilisateur->agence_id === null)
                    )),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom' => ['sometimes', 'required', 'string', 'max:255'],
            'adresse' => ['sometimes', 'required', 'string', 'max:255'],
            'telephone' => ['sometimes', 'required', 'string', 'max:255'],
            'ville' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(
                    $utilisateur instanceof User ? $utilisateur : null
                ),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::in(array_map(
                    fn (UserRole $role): string => $role->value,
                    UserRole::cases()
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agence_id.required' => 'Une agence est obligatoire pour ce rôle.',
            'agence_id.exists' => 'L\'agence sélectionnée n\'existe pas ou est inactive.',
            'role.in' => 'Le rôle sélectionné est invalide.',
        ];
    }

    private function roleUtilisateurCible(): ?UserRole
    {
        $utilisateur = $this->recupererUtilisateurCible();
        $valeurRole = $this->input(
            'role',
            $utilisateur instanceof User ? $utilisateur->role->value : ''
        );

        return UserRole::tryFrom((string) $valeurRole);
    }

    private function roleChange(?User $utilisateur): bool
    {
        return $utilisateur instanceof User
            && $this->exists('role')
            && $this->input('role') !== $utilisateur->role->value;
    }

    private function recupererUtilisateurCible(): ?User
    {
        $utilisateur = $this->route('uuid', $this->route('utilisateur'));

        if ($utilisateur instanceof User) {
            return $utilisateur;
        }

        $demandeur = $this->user();

        if (! $demandeur instanceof User || ! is_string($utilisateur)) {
            return null;
        }

        return User::query()
            ->accessiblePar($demandeur)
            ->where('uuid', $utilisateur)
            ->firstOrFail();
    }
}
