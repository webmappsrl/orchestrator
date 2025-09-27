<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundraisingOpportunity extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (FundraisingOpportunity $fundraisingOpportunity) {
            if (!$fundraisingOpportunity->created_by && auth()->check()) {
                $fundraisingOpportunity->created_by = auth()->id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'official_url',
        'endowment_fund',
        'deadline',
        'program_name',
        'sponsor',
        'cofinancing_quota',
        'max_contribution',
        'territorial_scope',
        'beneficiary_requirements',
        'lead_requirements',
        'created_by',
        'responsible_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'deadline' => 'date',
        'endowment_fund' => 'decimal:2',
        'cofinancing_quota' => 'decimal:2',
        'max_contribution' => 'decimal:2',
    ];

    /**
     * Get the user who created this opportunity.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user responsible for this opportunity.
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the projects associated with this opportunity.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(FundraisingProject::class);
    }

    /**
     * Scope to filter by territorial scope.
     */
    public function scopeByTerritorialScope($query, $scope)
    {
        return $query->where('territorial_scope', $scope);
    }

    /**
     * Scope to filter opportunities that are not expired.
     */
    public function scopeActive($query)
    {
        return $query->where('deadline', '>=', now());
    }

    /**
     * Scope to filter expired opportunities.
     */
    public function scopeExpired($query)
    {
        return $query->where('deadline', '<', now());
    }

    /**
     * Check if the opportunity is expired.
     */
    public function isExpired(): bool
    {
        return $this->deadline < now();
    }

    /**
     * Get the territorial scope label.
     */
    public function getTerritorialScopeLabelAttribute(): string
    {
        $labels = [
            'cooperation' => 'Cooperazione',
            'european' => 'Europeo',
            'national' => 'Nazionale',
            'regional' => 'Regionale',
            'territorial' => 'Territoriale',
            'municipalities' => 'Comuni',
        ];

        return $labels[$this->territorial_scope] ?? $this->territorial_scope;
    }

    /**
     * Get the territorial scope options.
     */
    public static function getTerritorialScopeOptions(): array
    {
        return [
            'cooperation' => 'Cooperazione',
            'european' => 'Europeo',
            'national' => 'Nazionale',
            'regional' => 'Regionale',
            'territorial' => 'Territoriale',
            'municipalities' => 'Comuni',
        ];
    }

}
