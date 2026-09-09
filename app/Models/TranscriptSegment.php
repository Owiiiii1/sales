<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    protected $fillable = [
        'transcript_id',
        'speaker',
        'start_seconds',
        'end_seconds',
        'text',
        'confidence',
        'sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speaker' => 'integer',
            'sequence' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'confidence' => 'float',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function speakerLabel(): string
    {
        return 'Speaker '.($this->speaker + 1);
    }
}
