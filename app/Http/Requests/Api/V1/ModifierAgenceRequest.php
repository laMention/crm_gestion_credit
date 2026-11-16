<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierAgenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agence = $this->route('agence');
        $utilisateur = $this->user();

        if (! $agence instanceof Agence || ! $utilisateur instanceof User) {
            return false;
        }

        Agence::query()
            ->accessiblePar($utilisateur)
            ->whereKey($agence->getKey())
            ->firstOrFail();

        return $utilisateur->can('modifier', $agence);
    }

    public function rules(): array
    {
        $agence = $this->route('agence');
        $identifiant = $agence instanceof Agence ? $agence->getKey() : null;

        return [
            'code_agence' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('agences', 'code_agence')->ignore($identifiant),
            ],
            'nom_agence' => ['sometimes', 'required', 'string', 'max:255'],
            'adresse_agence' => ['sometimes', 'required', 'string', 'max:255'],
            'ville_agence' => ['sometimes', 'required', 'string', 'max:255'],
            'telephone_agence' => ['sometimes', 'required', 'string', 'max:255'],
            'email_agence' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('agences', 'email_agence')->ignore($identifiant),
            ],
            'actif' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code_agence.unique' => 'Ce code agence est déjà utilisé.',
            'email_agence.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ];
    }
}
