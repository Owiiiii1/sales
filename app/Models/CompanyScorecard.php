<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyScorecard extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyScorecardFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(CompanyScorecardCriterion::class, 'scorecard_id')->orderBy('sequence');
    }

    public function activeCriteria(): HasMany
    {
        return $this->criteria()->where('is_active', true);
    }

    public function totalWeight(): float
    {
        return (float) $this->criteria()->where('is_active', true)->sum('weight');
    }
}
