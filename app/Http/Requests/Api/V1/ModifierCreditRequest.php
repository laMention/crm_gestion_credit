<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ModifierCreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'montant_demande' => [
                'sometimes',
                'numeric',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
                'gt:0',
            ],
            'taux_interet_annuel' => [
                'sometimes',
                'numeric',
                'regex:/^\d{1,4}(?:\.\d{1,4})?$/',
                'min:0',
            ],
            'duree_mois' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'compte_id' => ['prohibited'],
            'agence_id' => ['prohibited'],
            'client_id' => ['prohibited'],
            'conseiller_client_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'valide_par' => ['prohibited'],
            'statut' => ['prohibited'],
            'montant_accorde' => ['prohibited'],
            'date_demande' => ['prohibited'],
        ];
    }
}
