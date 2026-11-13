<?php

namespace App\Http\Requests\Api\V1;

class EnregistrerRemboursementRequest extends RequeteFinanciereIdempotente
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
            ...$this->reglesIdempotence(),
            'compte_uuid' => ['required', 'uuid'],
            'montant' => [
                'required',
                'numeric',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
                'gt:0',
            ],
            'motif' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'date_paiement' => ['prohibited'],
            'credit_id' => ['prohibited'],
            'agence_id' => ['prohibited'],
            'client_id' => ['prohibited'],
            'statut_transaction' => ['prohibited'],
        ];
    }
}
