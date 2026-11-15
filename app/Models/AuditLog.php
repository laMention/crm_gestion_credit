<?php

namespace App\Models;

use App\Services\Authorization\AccesAgenceService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'user_id',
    'agence_id',
    'role_utilisateur',
    'action',
    'module',
    'type_ressource',
    'resultat',
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

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Les journaux d’audit sont immuables.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Les journaux d’audit sont immuables.');
        });
    }

    #[Scope]
    protected function accessiblePar(Builder $query, User $utilisateur): void
    {
        if (app(AccesAgenceService::class)->peutAccederAuReseau($utilisateur)) {
            return;
        }

        if ($utilisateur->agence_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $journaux) use ($utilisateur): void {
            $journaux
                ->where('agence_id', $utilisateur->agence_id)
                ->orWhere(function (Builder $anciensJournaux) use ($utilisateur): void {
                    $anciensJournaux
                        ->whereNull('agence_id')
                        ->whereHas(
                            'user',
                            fn (Builder $users): Builder => $users->where(
                                'agence_id',
                                $utilisateur->agence_id
                            )
                        );
                });
        });
    }
}
