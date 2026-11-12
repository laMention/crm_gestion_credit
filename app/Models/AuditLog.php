<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use App\Models\User;
#[Fillable(['user_id', 'action', 'table_concernee', 'enregistrement_id', 'anciennes_valeurs', 'nouvelles_valeurs', 'adresse_ip', 'user_agent', 'description'])]

class AuditLog extends Model
{
    use HasUuids;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relation entre utilisateur et les logs d'audit
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
