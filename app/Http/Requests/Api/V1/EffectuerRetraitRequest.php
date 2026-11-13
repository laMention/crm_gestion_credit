<?php

namespace App\Http\Requests\Api\V1;

class EffectuerRetraitRequest extends RequeteFinanciereIdempotente
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...$this->reglesIdempotence(),
            'montant' => [
                'required',
                'numeric',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
                'gt:0',
            ],
            'motif' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'devise' => ['prohibited'],
            'agence_id' => ['prohibited'],
            'client_id' => ['prohibited'],
            'compte_id' => ['prohibited'],
            'statut_transaction' => ['prohibited'],
        ];
    }
}
