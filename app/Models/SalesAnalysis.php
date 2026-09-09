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
        'summary',
        'result',
        'started_at',
        'completed_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'schema_version' => 'integer',
            'overall_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
