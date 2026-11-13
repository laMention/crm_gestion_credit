<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Compte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerComptesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Compte::class) ?? false;
    }

    public function rules(): array
    {
        $utilisateur = $this->user();

        return [
            'numero_compte' => ['sometimes', 'string', 'max:30'],
            'client_uuid' => ['sometimes', 'uuid'],
            'type_compte' => [
                'sometimes',
                'string',
                Rule::in(['courant', 'epargne', 'credit']),
            ],
            'statut_compte' => [
                'sometimes',
                'string',
                Rule::in(['actif', 'bloque', 'cloture']),
            ],
            'agence_id' => [
                'sometimes',
                'integer',
                Rule::exists('agences', 'id'),
                Rule::prohibitedIf(! $utilisateur?->role->roleEchelleDuReseau()),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
