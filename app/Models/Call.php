<?php

namespace App\Models;

use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    public const STATUSES = [
        'pending',
        'uploaded',
        'processing',
        'completed',
        'failed',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'source',
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
}
