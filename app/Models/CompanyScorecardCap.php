<?php

namespace App\Models;

use Database\Factories\CompanyScorecardCapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyScorecardCap extends Model
{
    /** @use HasFactory<CompanyScorecardCapFactory> */
    use HasFactory;

    public const TRIGGER_CRITERION_CRITICAL_FAILURE = 'criterion_critical_failure';

    public const TRIGGER_FORBIDDEN_CLAIM_VIOLATION = 'forbidden_claim_violation';

    protected $fillable = [
        'scorecard_id',
        'name',
        'criterion_key',
        'trigger_type',
        'max_total_score',
        'description',
        'is_active',
        'sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_total_score' => 'integer',
            'is_active' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function triggerTypes(): array
    {
        return [
            self::TRIGGER_CRITERION_CRITICAL_FAILURE,
            self::TRIGGER_FORBIDDEN_CLAIM_VIOLATION,
        ];
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(CompanyScorecard::class, 'scorecard_id');
    }
}
