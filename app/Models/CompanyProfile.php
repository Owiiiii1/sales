<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'short_description',
        'sales_context',
        'target_audience',
        'ideal_customer_profile',
        'value_proposition',
        'usp',
        'pricing_context',
        'competitors',
        'customer_pains',
        'sales_goals',
        'desired_next_steps',
        'forbidden_claims',
        'mandatory_questions',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
