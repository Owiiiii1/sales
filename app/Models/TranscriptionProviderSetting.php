<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranscriptionProviderSetting extends Model
{
    protected $hidden = [
        'api_key',
    ];

    protected $fillable = [
        'provider',
        'label',
        'api_key',
        'is_connected',
        'is_active',
        'active_model',
        'available_models',
        'last_checked_at',
        'last_error',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'available_models' => 'array',
            'settings' => 'array',
            'is_connected' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }
}
