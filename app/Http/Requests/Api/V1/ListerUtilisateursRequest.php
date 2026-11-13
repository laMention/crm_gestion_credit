<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerUtilisateursRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recherche' => ['sometimes', 'string', 'max:255'],
            'role' => [
                'sometimes',
                'string',
                Rule::in(array_map(
                    fn (UserRole $role): string => $role->value,
                    UserRole::cases()
                )),
            ],
            'agence_id' => ['sometimes', 'integer', Rule::exists('agences', 'id')],
            'actif' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
