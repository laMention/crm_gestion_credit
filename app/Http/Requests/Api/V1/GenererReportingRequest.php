<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Reporting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenererReportingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('consulterTout', Reporting::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agence_id' => [
                Rule::requiredIf(
                    $this->user()?->role->roleEchelleDuReseau() ?? false
                ),
                'sometimes',
                'integer',
                Rule::exists('agences', 'id'),
                Rule::prohibitedIf(
                    $this->user()?->role->estPersonnelAgence() ?? false
                ),
            ],
            'date_reporting' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
