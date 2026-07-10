<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Instancia de notificaciones (un cliente: taller-1, clinica-2, ...).
 *
 * Se autentica ante la API con client_id + client_secret y, a cambio,
 * recibe un access token de Sanctum. Emite y recibe mensajes aislados
 * por instancia.
 */
class Tenant extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'active',
        'client_id',
        'client_secret_hash',
        'api_token_hash',
        'provider',
        'wa_phone_number_id',
        'wa_business_account_id',
        'wa_phone_number',
        'wa_access_token',
    ];

    protected $hidden = [
        'client_secret_hash',
        'api_token_hash',
        'wa_access_token',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'wa_access_token' => 'encrypted',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Genera un par client_id + client_secret nuevo.
     * Guarda el hash del secret y devuelve el secret EN CLARO
     * (solo se muestra una vez, al crear o regenerar).
     */
    public function generateCredentials(): string
    {
        $this->client_id = 'cli_'.Str::lower(Str::random(24));
        $secret = 'sec_'.Str::random(48);
        $this->client_secret_hash = Hash::make($secret);

        return $secret;
    }

    /**
     * Verifica el client_secret que envía el cliente contra el hash guardado.
     */
    public function checkSecret(string $secret): bool
    {
        return $this->client_secret_hash
            && Hash::check($secret, $this->client_secret_hash);
    }
}
