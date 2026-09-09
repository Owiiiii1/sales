<?php

namespace App\Models;

use Database\Factories\TranscriptSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    /** @use HasFactory<TranscriptSegmentFactory> */
    use HasFactory;
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
