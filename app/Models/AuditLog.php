<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'table_concernee',
    'enregistrement_id',
    'anciennes_valeurs',
    'nouvelles_valeurs',
    'adresse_ip',
    'user_agent',
    'description',
])]

class AuditLog extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'enregistrement_id' => 'integer',
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
