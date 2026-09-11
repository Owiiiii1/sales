<?php

namespace App\Models;

use Database\Factories\CompanyFactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFact extends Model
{
    /** @use HasFactory<CompanyFactFactory> */
    use HasFactory;

    public const STATUS_CURRENT = 'current';

    public const STATUS_OUTDATED = 'outdated';

    protected $fillable = [
        'company_id',
        'label',
        'value',
        'status',
        'valid_until',
        'source',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isCurrent(): bool
    {
        return $this->status === self::STATUS_CURRENT;
    }
}
