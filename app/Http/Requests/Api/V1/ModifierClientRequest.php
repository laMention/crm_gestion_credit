<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Client::class) ?? false;
    }

    public function rules(): array
    {
        $client = Client::query()
            ->accessiblePar($this->user())
            ->where('uuid', $this->route('uuid'))
            ->firstOrFail();

        return [
            'agence_id' => ['prohibited'],
            'code_client' => ['prohibited'],
            'statut' => ['prohibited'],
            'cree_par_user_id' => ['prohibited'],
            'nom_client' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom_client' => ['sometimes', 'required', 'string', 'max:255'],
            'adresse_client' => ['sometimes', 'required', 'string', 'max:255'],
            'telephone_client' => ['sometimes', 'required', 'string', 'max:255'],
            'ville_client' => ['sometimes', 'required', 'string', 'max:255'],
            'email_client' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('clients', 'email_client')->ignore($client),
            ],
            'date_naissance' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sexe' => ['sometimes', 'nullable', 'string', 'max:10'],
            'type_piece_identite' => ['sometimes', 'nullable', 'string', 'max:50'],
            'numero_piece_identite' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_expiration_piece' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'revenu_mensuel' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
