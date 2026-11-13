<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OuvrirCompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creer', Client::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'agence_id' => ['prohibited'],
            'client_id' => ['prohibited'],
            'numero_compte' => ['prohibited'],
            'solde' => ['prohibited'],
            'solde_initial' => ['prohibited'],
            'devise' => ['prohibited'],
            'date_ouverture' => ['prohibited'],
            'statut_compte' => ['prohibited'],
            'cree_par_user_id' => ['prohibited'],
            'type_compte' => [
                'required',
                'string',
                Rule::in(['courant', 'epargne']),
            ],
        ];
    }
}
