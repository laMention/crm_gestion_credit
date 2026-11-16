<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Agence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListerAgencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Agence::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recherche' => ['sometimes', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
