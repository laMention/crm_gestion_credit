<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EcheanceCredit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerEcheancesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', EcheanceCredit::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $utilisateur = $this->user();

        return [
            'client_uuid' => ['sometimes', 'uuid'],
            'credit_uuid' => ['sometimes', 'uuid'],
            'statut_echeance_credit' => [
                'sometimes',
                'string',
                Rule::in(['a_payer', 'partiellement_paye', 'paye', 'en_retard', 'annule']),
            ],
            'date_debut' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:date_fin'],
            'date_fin' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'impayees' => ['sometimes', 'boolean'],
            'en_retard' => ['sometimes', 'boolean'],
            'agence_id' => [
                'sometimes',
                'integer',
                Rule::exists('agences', 'id'),
                Rule::prohibitedIf(
                    ! $utilisateur?->role->roleEchelleDuReseau()
                ),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
