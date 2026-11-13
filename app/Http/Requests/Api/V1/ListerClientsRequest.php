<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Client::class) ?? false;
    }

    public function rules(): array
    {
        $utilisateur = $this->user();

        return [
            'recherche' => ['sometimes', 'string', 'max:255'],
            'statut' => ['sometimes', 'string', 'max:20'],
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
