<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'website',
        'industry',
        'description',
        'country',
        'city',
        'phone',
        'email',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CompanyOffering::class);
    }

    public function objections(): HasMany
    {
        return $this->hasMany(CompanyObjection::class);
    }

    public function salesScripts(): HasMany
    {
        return $this->hasMany(CompanySalesScript::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(CompanyScorecard::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(CompanyFact::class);
    }
}
