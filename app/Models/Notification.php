<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'tenant_id',
        'channel',
        'direction',
        'to_address',
        'from_address',
        'type',
        'payload',
        'reference',
        'button_payload',
        'provider',
        'provider_message_id',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
