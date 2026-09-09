<?php

namespace App\Models;

use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    public const STATUSES = [
        'pending',
        'uploaded',
        'processing',
        'transcribed',
        'analysis_pending',
        'analyzing',
        'completed',
        'failed',
    ];

    protected $hidden = [
        'storage_path',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'source',
        'public_token',
        'external_id',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'duration_seconds',
        'status',
        'recorded_at',
        'uploaded_by',
        'processing_started_at',
        'processing_completed_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Call $call): void {
            if (! filled($call->public_token)) {
                $call->public_token = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(SalesAnalysis::class);
    }

    public function fileSizeLabel(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        $precision = $unit === 0 ? 0 : 1;

        return number_format($bytes, $precision).' '.$units[$unit];
    }
}
