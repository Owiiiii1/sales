<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyScorecardCriterion extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyScorecardCriterionFactory> */
    use HasFactory;

    protected $fillable = [
        'scorecard_id',
        'key',
        'name',
        'description',
        'weight',
        'max_score',
        'is_critical',
        'ai_instructions',
        'sequence',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'max_score' => 'integer',
            'is_critical' => 'boolean',
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(CompanyScorecard::class, 'scorecard_id');
    }
}
