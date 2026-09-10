<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesAnalysis extends Model
{
    /** @use HasFactory<\Database\Factories\SalesAnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'call_id',
        'provider',
        'model',
        'schema_version',
        'overall_score',
        'company_scorecard_score',
        'summary',
        'result',
        'started_at',
        'completed_at',
        'error_message',
        'company_context_hash',
        'scorecard_id',
        'scorecard_snapshot',
        'context_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'scorecard_snapshot' => 'array',
            'context_snapshot' => 'array',
            'schema_version' => 'integer',
            'overall_score' => 'integer',
            'company_scorecard_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(CompanyScorecard::class, 'scorecard_id');
    }
}
