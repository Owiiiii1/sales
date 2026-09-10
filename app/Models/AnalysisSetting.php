<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisSetting extends Model
{
    public const LANGUAGE_SAME_AS_CALL = 'same_as_call';

    protected $fillable = [
        'report_language_mode',
        'max_output_tokens',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_output_tokens' => 'integer',
        ];
    }
}
