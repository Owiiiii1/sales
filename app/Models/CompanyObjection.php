<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyObjection extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyObjectionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'objection',
        'recommended_response',
        'notes',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
