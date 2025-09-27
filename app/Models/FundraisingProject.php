<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundraisingProject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'fundraising_opportunity_id',
        'lead_customer_id',
        'created_by',
        'responsible_user_id',
        'description',
        'status',
        'requested_amount',
        'approved_amount',
        'submission_date',
        'decision_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'submission_date' => 'date',
        'decision_date' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    /**
     * Get the fundraising opportunity this project belongs to.
     */
    public function fundraisingOpportunity(): BelongsTo
    {
        return $this->belongsTo(FundraisingOpportunity::class);
    }

    /**
     * Get the lead customer for this project.
     */
    public function leadCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'lead_customer_id');
    }

    /**
     * Get the user who created this project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user responsible for this project.
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the partner customers for this project.
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'fundraising_project_partners');
    }

    /**
     * Get the stories related to this project.
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter projects where user is lead customer.
     */
    public function scopeWhereLeadCustomer($query, $customerId)
    {
        return $query->where('lead_customer_id', $customerId);
    }

    /**
     * Scope to filter projects where user is partner.
     */
    public function scopeWherePartner($query, $customerId)
    {
        return $query->whereHas('partners', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        });
    }

    /**
     * Scope to filter projects where user is involved (lead or partner).
     */
    public function scopeWhereInvolved($query, $customerId)
    {
        return $query->where(function ($q) use ($customerId) {
            $q->where('lead_customer_id', $customerId)
              ->orWhereHas('partners', function ($subQ) use ($customerId) {
                  $subQ->where('customer_id', $customerId);
              });
        });
    }

    /**
     * Get the status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Bozza',
            'submitted' => 'Presentato',
            'approved' => 'Approvato',
            'rejected' => 'Respinto',
            'completed' => 'Completato',
        ];
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    /**
     * Check if the project is submitted.
     */
    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'approved', 'rejected', 'completed']);
    }

    /**
     * Check if the project is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the project is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the project is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
