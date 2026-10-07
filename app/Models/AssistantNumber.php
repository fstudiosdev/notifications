<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teléfono de un doctor que habla con el asistente IA de una instancia.
 * Ver la migración create_assistant_numbers_table.
 */
class AssistantNumber extends Model
{
    protected $fillable = ['tenant_id', 'phone'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
