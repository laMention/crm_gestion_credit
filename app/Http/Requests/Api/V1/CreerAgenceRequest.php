<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Agence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreerAgenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creer', Agence::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code_agence' => [
                'required',
                'string',
                'max:10',
                Rule::unique('agences', 'code_agence'),
            ],
            'nom_agence' => ['required', 'string', 'max:255'],
            'adresse_agence' => ['required', 'string', 'max:255'],
            'ville_agence' => ['required', 'string', 'max:255'],
            'telephone_agence' => ['required', 'string', 'max:255'],
            'email_agence' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('agences', 'email_agence'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code_agence.unique' => 'Ce code agence est déjà utilisé.',
            'email_agence.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ];
    }
}
