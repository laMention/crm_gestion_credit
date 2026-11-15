<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Reporting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrerReportingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Reporting::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_debut',
            ],
            'agence_id' => [
                'sometimes',
                'integer',
                Rule::exists('agences', 'id'),
                Rule::prohibitedIf(
                    ! $this->user()?->role->roleEchelleDuReseau()
                ),
            ],
            'type_transaction' => [
                'sometimes',
                'string',
                Rule::in([
                    'depot',
                    'retrait',
                    'virement',
                    'paiement_facture',
                    'deboursement',
                    'remboursement',
                ]),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
