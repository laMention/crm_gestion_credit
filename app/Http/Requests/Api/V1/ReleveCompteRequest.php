<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Compte;
use Illuminate\Foundation\Http\FormRequest;

class ReleveCompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Compte::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'date_debut' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:date_fin',
            ],
            'date_fin' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:date_debut',
            ],
            'operations_par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_debut.date_format' => 'La date de début doit respecter le format AAAA-MM-JJ.',
            'date_debut.before_or_equal' => 'La date de début doit être antérieure ou égale à la date de fin.',
            'date_fin.date_format' => 'La date de fin doit respecter le format AAAA-MM-JJ.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
