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

        static::saving(function (FundraisingOpportunity $fundraisingOpportunity) {
            $fundraisingOpportunity->calculateEvaluationTotals();
            
            // Imposta evaluated_by se non specificato e c'è un utente autenticato
            if (!$fundraisingOpportunity->evaluation_evaluated_by && auth()->check()) {
                $fundraisingOpportunity->evaluation_evaluated_by = auth()->id();
            }
            
            // Imposta evaluated_at se ci sono valori di valutazione e non è già impostato
            if (!$fundraisingOpportunity->evaluation_evaluated_at && $fundraisingOpportunity->hasEvaluationData()) {
                $fundraisingOpportunity->evaluation_evaluated_at = now();
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
        // Parte 1 - Criteri principali
        'evaluation_criterion_a_score',
        'evaluation_criterion_a_description',
        'evaluation_criterion_b_score',
        'evaluation_criterion_b_description',
        'evaluation_criterion_c_score',
        'evaluation_criterion_c_description',
        'evaluation_criterion_d_score',
        'evaluation_criterion_d_description',
        'evaluation_criterion_e_score',
        'evaluation_criterion_e_description',
        'evaluation_criterion_f_score',
        'evaluation_criterion_f_description',
        // Parte 2 - Requisiti di base
        'evaluation_base_coerenza_bando',
        'evaluation_base_capofila_idoneo',
        'evaluation_base_partner_minimi',
        'evaluation_base_cofinanziamento',
        'evaluation_base_tempistiche',
        // Parte 2 - Valutazione qualitativa
        'evaluation_qual_coerenza_cai',
        'evaluation_qual_imp_ambientale',
        'evaluation_qual_imp_sociale',
        'evaluation_qual_imp_economico',
        'evaluation_qual_obiettivi_chiari',
        'evaluation_qual_solidita_azioni',
        'evaluation_qual_capacita_partner',
        // Parte 2 - Fattori premiali
        'evaluation_prem_innovazione',
        'evaluation_prem_replicabilita',
        'evaluation_prem_comunita',
        'evaluation_prem_sostenibilita',
        // Parte 2 - Rischi
        'evaluation_risk_tecnici',
        'evaluation_risk_finanziari',
        'evaluation_risk_organizzativi',
        'evaluation_risk_logistici',
        // Totali calcolati
        'evaluation_total_positive',
        'evaluation_total_negative',
        'evaluation_total_score',
        // Campi informativi
        'evaluation_evaluated_by',
        'evaluation_evaluated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'deadline' => 'date',
        'endowment_fund' => 'decimal:2',
        'cofinancing_quota' => 'decimal:2',
        'max_contribution' => 'decimal:2',
        'evaluation_evaluated_at' => 'datetime',
        // Cast per tutti i campi numerici della valutazione
        'evaluation_criterion_a_score' => 'integer',
        'evaluation_criterion_b_score' => 'integer',
        'evaluation_criterion_c_score' => 'integer',
        'evaluation_criterion_d_score' => 'integer',
        'evaluation_criterion_e_score' => 'integer',
        'evaluation_criterion_f_score' => 'integer',
        'evaluation_base_coerenza_bando' => 'integer',
        'evaluation_base_capofila_idoneo' => 'integer',
        'evaluation_base_partner_minimi' => 'integer',
        'evaluation_base_cofinanziamento' => 'integer',
        'evaluation_base_tempistiche' => 'integer',
        'evaluation_qual_coerenza_cai' => 'integer',
        'evaluation_qual_imp_ambientale' => 'integer',
        'evaluation_qual_imp_sociale' => 'integer',
        'evaluation_qual_imp_economico' => 'integer',
        'evaluation_qual_obiettivi_chiari' => 'integer',
        'evaluation_qual_solidita_azioni' => 'integer',
        'evaluation_qual_capacita_partner' => 'integer',
        'evaluation_prem_innovazione' => 'integer',
        'evaluation_prem_replicabilita' => 'integer',
        'evaluation_prem_comunita' => 'integer',
        'evaluation_prem_sostenibilita' => 'integer',
        'evaluation_risk_tecnici' => 'integer',
        'evaluation_risk_finanziari' => 'integer',
        'evaluation_risk_organizzativi' => 'integer',
        'evaluation_risk_logistici' => 'integer',
        'evaluation_total_positive' => 'integer',
        'evaluation_total_negative' => 'integer',
        'evaluation_total_score' => 'integer',
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
     * Get the user who evaluated this opportunity.
     */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluation_evaluated_by');
    }

    /**
     * Check if the opportunity has any evaluation data.
     */
    public function hasEvaluationData(): bool
    {
        return !is_null($this->evaluation_criterion_a_score) ||
               !is_null($this->evaluation_criterion_b_score) ||
               !is_null($this->evaluation_criterion_c_score) ||
               !is_null($this->evaluation_criterion_d_score) ||
               !is_null($this->evaluation_criterion_e_score) ||
               !is_null($this->evaluation_criterion_f_score) ||
               !is_null($this->evaluation_base_coerenza_bando) ||
               !is_null($this->evaluation_qual_coerenza_cai) ||
               !is_null($this->evaluation_prem_innovazione) ||
               !is_null($this->evaluation_risk_tecnici);
    }

    /**
     * Calculate evaluation totals.
     */
    public function calculateEvaluationTotals(): void
    {
        $totalPositive = 0;
        $totalNegative = 0;

        // Parte 1 - Criteri principali (0-5 ciascuno)
        $totalPositive += ($this->evaluation_criterion_a_score ?? 0);
        $totalPositive += ($this->evaluation_criterion_b_score ?? 0);
        $totalPositive += ($this->evaluation_criterion_c_score ?? 0);
        $totalPositive += ($this->evaluation_criterion_d_score ?? 0);
        $totalPositive += ($this->evaluation_criterion_e_score ?? 0);
        $totalPositive += ($this->evaluation_criterion_f_score ?? 0);

        // Parte 2 - Requisiti di base (0-1 ciascuno)
        $totalPositive += ($this->evaluation_base_coerenza_bando ?? 0);
        $totalPositive += ($this->evaluation_base_capofila_idoneo ?? 0);
        $totalPositive += ($this->evaluation_base_partner_minimi ?? 0);
        $totalPositive += ($this->evaluation_base_cofinanziamento ?? 0);
        $totalPositive += ($this->evaluation_base_tempistiche ?? 0);

        // Parte 2 - Valutazione qualitativa (0-5 ciascuno)
        $totalPositive += ($this->evaluation_qual_coerenza_cai ?? 0);
        $totalPositive += ($this->evaluation_qual_imp_ambientale ?? 0);
        $totalPositive += ($this->evaluation_qual_imp_sociale ?? 0);
        $totalPositive += ($this->evaluation_qual_imp_economico ?? 0);
        $totalPositive += ($this->evaluation_qual_obiettivi_chiari ?? 0);
        $totalPositive += ($this->evaluation_qual_solidita_azioni ?? 0);
        $totalPositive += ($this->evaluation_qual_capacita_partner ?? 0);

        // Parte 2 - Fattori premiali (0-3 ciascuno)
        $totalPositive += ($this->evaluation_prem_innovazione ?? 0);
        $totalPositive += ($this->evaluation_prem_replicabilita ?? 0);
        $totalPositive += ($this->evaluation_prem_comunita ?? 0);
        $totalPositive += ($this->evaluation_prem_sostenibilita ?? 0);

        // Parte 2 - Rischi
        // Rischi tecnici (0-3, solo positivi)
        $riskTecnici = $this->evaluation_risk_tecnici ?? 0;
        if ($riskTecnici > 0) {
            $totalPositive += $riskTecnici;
        }

        // Rischi finanziari (-3 a 3)
        $riskFinanziari = $this->evaluation_risk_finanziari ?? 0;
        if ($riskFinanziari >= 0) {
            $totalPositive += $riskFinanziari;
        } else {
            $totalNegative += abs($riskFinanziari);
        }

        // Rischi organizzativi (-2 a 2)
        $riskOrganizzativi = $this->evaluation_risk_organizzativi ?? 0;
        if ($riskOrganizzativi >= 0) {
            $totalPositive += $riskOrganizzativi;
        } else {
            $totalNegative += abs($riskOrganizzativi);
        }

        // Rischi logistici (-2 a 2)
        $riskLogistici = $this->evaluation_risk_logistici ?? 0;
        if ($riskLogistici >= 0) {
            $totalPositive += $riskLogistici;
        } else {
            $totalNegative += abs($riskLogistici);
        }

        $this->evaluation_total_positive = $totalPositive;
        $this->evaluation_total_negative = $totalNegative;
        $this->evaluation_total_score = $totalPositive - $totalNegative;
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
