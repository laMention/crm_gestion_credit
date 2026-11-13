<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class RequeteFinanciereIdempotente extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'cle_idempotence' => $this->header('Idempotency-Key'),
        ];
    }

    /**
     * @return array{cle_idempotence: list<string>}
     */
    protected function reglesIdempotence(): array
    {
        return [
            'cle_idempotence' => ['required', 'string', 'max:100'],
        ];
    }
}
