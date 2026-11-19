<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Models\Agence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use App\Models\Compte;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Models\EcheanceCredit;
use App\Models\Credit;
use App\Models\RemboursementEcheance;
use App\Models\User;
use App\Models\Reporting;
use App\Models\AuditLog;
class CreerClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creer', Client::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'agence_id' => ['prohibited'],
            'code_client' => ['prohibited'],
            'statut' => ['prohibited'],
            'cree_par_user_id' => ['prohibited'],
            'nom_client' => ['required', 'string', 'max:255'],
            'prenom_client' => ['required', 'string', 'max:255'],
            'adresse_client' => ['required', 'string', 'max:255'],
            'telephone_client' => ['required', 'string', 'max:255'],
            'ville_client' => ['required', 'string', 'max:255'],
            'email_client' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('clients', 'email_client'),
            ],
            'date_naissance' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sexe' => ['sometimes', 'nullable', 'string', 'max:10'],
            'type_piece_identite' => ['sometimes', 'nullable', 'string', 'max:50'],
            'numero_piece_identite' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_expiration_piece' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'revenu_mensuel' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'types_comptes' => ['sometimes', 'array'],
            'types_comptes.*' => ['string', Rule::in(['courant', 'epargne', 'credit'])],
        ];
    }
}
