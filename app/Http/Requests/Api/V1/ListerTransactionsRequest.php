<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Transaction::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $utilisateur = $this->user();

        return [
            'reference_transaction' => ['sometimes', 'string', 'max:30'],
            'numero_compte' => ['sometimes', 'string', 'max:30'],
            'client_uuid' => ['sometimes', 'uuid'],
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
            'statut_transaction' => [
                'sometimes',
                'string',
                Rule::in(['en_attente', 'terminee', 'annulee']),
            ],
            'date_debut' => [
                'sometimes',
                'date_format:Y-m-d',
                'before_or_equal:date_fin',
            ],
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
                    ! $utilisateur?->role->roleEchelleDuReseau()
                ),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
