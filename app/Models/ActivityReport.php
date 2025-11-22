<?php

namespace App\Models;

use App\Enums\OwnerType;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class ActivityReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'customer_id',
        'organization_id',
        'report_type',
        'year',
        'month',
        'pdf_url',
    ];

    protected $casts = [
        'owner_type' => OwnerType::class,
        'report_type' => ReportType::class,
        'year' => 'integer',
        'month' => 'integer',
    ];

    /**
     * Get the customer (user) that owns this report.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the organization that owns this report.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the stories associated with this report.
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'activity_report_story')
            ->withTimestamps();
    }

    /**
     * Get the start date of the report period.
     */
    public function getStartDateAttribute(): Carbon
    {
        if ($this->report_type === ReportType::Monthly) {
            return Carbon::create($this->year, $this->month, 1)->startOfMonth();
        }
        // Annual report
        return Carbon::create($this->year, 1, 1)->startOfYear();
    }

    /**
     * Get the end date of the report period.
     */
    public function getEndDateAttribute(): Carbon
    {
        if ($this->report_type === ReportType::Monthly) {
            return Carbon::create($this->year, $this->month, 1)->endOfMonth();
        }
        // Annual report
        return Carbon::create($this->year, 12, 31)->endOfYear();
    }

    /**
     * Get the owner (customer or organization) name.
     */
    public function getOwnerNameAttribute(): ?string
    {
        if ($this->owner_type === OwnerType::Customer && $this->customer) {
            return $this->customer->name;
        }
        
        if ($this->owner_type === OwnerType::Organization && $this->organization) {
            return $this->organization->name;
        }
        
        return null;
    }

    /**
     * Scope to filter by owner type.
     */
    public function scopeForOwnerType($query, OwnerType $ownerType)
    {
        return $query->where('owner_type', $ownerType->value);
    }

    /**
     * Scope to filter by report type.
     */
    public function scopeForReportType($query, ReportType $reportType)
    {
        return $query->where('report_type', $reportType->value);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter by month (only for monthly reports).
     */
    public function scopeForMonth($query, int $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('owner_type', OwnerType::Customer->value)
            ->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by organization.
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('owner_type', OwnerType::Organization->value)
            ->where('organization_id', $organizationId);
    }
}

