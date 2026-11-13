<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RejeterCreditRequest extends FormRequest
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
            'motif_rejet' => ['required', 'string', 'min:3', 'max:1000'],
            'montant_accorde' => ['prohibited'],
            'compte_id' => ['prohibited'],
            'agence_id' => ['prohibited'],
            'statut' => ['prohibited'],
        ];
    }
}
