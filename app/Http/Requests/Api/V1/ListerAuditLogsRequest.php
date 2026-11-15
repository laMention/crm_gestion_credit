<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Models\AuditLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerAuditLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', AuditLog::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_uuid' => ['sometimes', 'uuid'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'agence_id' => [
                'sometimes',
                'integer',
                Rule::exists('agences', 'id'),
                Rule::prohibitedIf(
                    ! $this->user()?->role->roleEchelleDuReseau()
                ),
            ],
            'module' => [
                'sometimes',
                'string',
                Rule::in([
                    'authentification',
                    'utilisateurs',
                    'clients',
                    'comptes',
                    'transactions',
                    'credits',
                    'reportings',
                ]),
            ],
            'action' => ['sometimes', 'string', 'max:100'],
            'type_ressource' => ['sometimes', 'string', 'max:100'],
            'enregistrement_id' => ['sometimes', 'integer', 'min:1'],
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:date_debut',
            ],
            'resultat' => ['sometimes', Rule::in(['succes', 'echec'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
