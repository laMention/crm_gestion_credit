<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'echeance_credit_id',
    'montant',
    'capital',
    'interet',
])]
class RemboursementEcheance extends Model
{
    protected $table = 'echeance_credit_remboursements';

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'capital' => 'decimal:2',
            'interet' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function echeance(): BelongsTo
    {
        return $this->belongsTo(EcheanceCredit::class, 'echeance_credit_id');
    }
}
