<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyOffering extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyOfferingFactory> */
    use HasFactory;

    public const TYPES = ['product', 'service', 'other'];

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'description',
        'target_customer',
        'value_proposition',
        'pricing',
        'differentiators',
        'common_use_cases',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
