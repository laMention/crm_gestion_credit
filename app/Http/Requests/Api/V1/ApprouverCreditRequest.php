<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApprouverCreditRequest extends FormRequest
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
            'montant_accorde' => [
                'sometimes',
                'nullable',
                'numeric',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
                'gt:0',
            ],
            'motif_rejet' => ['prohibited'],
            'compte_id' => ['prohibited'],
            'agence_id' => ['prohibited'],
            'statut' => ['prohibited'],
        ];
    }
}
