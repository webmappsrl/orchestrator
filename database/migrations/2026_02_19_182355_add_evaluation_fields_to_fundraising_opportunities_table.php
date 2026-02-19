<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fundraising_opportunities', function (Blueprint $table) {
            // Parte 1 - Criteri principali (0-5 + descrizione)
            $table->integer('evaluation_criterion_a_score')->nullable()->comment('Criterio A: Coerenza e rilevanza della proposta (0-5)');
            $table->text('evaluation_criterion_a_description')->nullable();
            $table->integer('evaluation_criterion_b_score')->nullable()->comment('Criterio B: Qualità dell\'idea e fattibilità tecnica/organizzativa (0-5)');
            $table->text('evaluation_criterion_b_description')->nullable();
            $table->integer('evaluation_criterion_c_score')->nullable()->comment('Criterio C: Impatto su soci, territorio e comunità (0-5)');
            $table->text('evaluation_criterion_c_description')->nullable();
            $table->integer('evaluation_criterion_d_score')->nullable()->comment('Criterio D: Valore aggiunto e replicabilità (0-5)');
            $table->text('evaluation_criterion_d_description')->nullable();
            $table->integer('evaluation_criterion_e_score')->nullable()->comment('Criterio E: Partenariato e capacità operativa (0-5)');
            $table->text('evaluation_criterion_e_description')->nullable();
            $table->integer('evaluation_criterion_f_score')->nullable()->comment('Criterio F: Sostenibilità economica e gestionale (0-5)');
            $table->text('evaluation_criterion_f_description')->nullable();

            // Parte 2 - Requisiti di base (0-1)
            $table->integer('evaluation_base_coerenza_bando')->nullable()->comment('Coerenza bando (0-1)');
            $table->integer('evaluation_base_capofila_idoneo')->nullable()->comment('Capofila idoneo (0-1)');
            $table->integer('evaluation_base_partner_minimi')->nullable()->comment('Partner minimi (0-1)');
            $table->integer('evaluation_base_cofinanziamento')->nullable()->comment('Cofinanziamento (0-1)');
            $table->integer('evaluation_base_tempistiche')->nullable()->comment('Tempistiche (0-1)');

            // Parte 2 - Valutazione qualitativa (0-5)
            $table->integer('evaluation_qual_coerenza_cai')->nullable()->comment('Coerenza CAI (0-5)');
            $table->integer('evaluation_qual_imp_ambientale')->nullable()->comment('Impatto Ambientale (0-5)');
            $table->integer('evaluation_qual_imp_sociale')->nullable()->comment('Impatto Sociale (0-5)');
            $table->integer('evaluation_qual_imp_economico')->nullable()->comment('Impatto Economico (0-5)');
            $table->integer('evaluation_qual_obiettivi_chiari')->nullable()->comment('Obiettivi chiari (0-5)');
            $table->integer('evaluation_qual_solidita_azioni')->nullable()->comment('Solidità azioni (0-5)');
            $table->integer('evaluation_qual_capacita_partner')->nullable()->comment('Capacità partner (0-5)');

            // Parte 2 - Fattori premiali (0-3)
            $table->integer('evaluation_prem_innovazione')->nullable()->comment('Innovazione (0-3)');
            $table->integer('evaluation_prem_replicabilita')->nullable()->comment('Replicabilità (0-3)');
            $table->integer('evaluation_prem_comunita')->nullable()->comment('Comunità (0-3)');
            $table->integer('evaluation_prem_sostenibilita')->nullable()->comment('Sostenibilità (0-3)');

            // Parte 2 - Rischi
            $table->integer('evaluation_risk_tecnici')->nullable()->comment('Rischi tecnici (0-3)');
            $table->integer('evaluation_risk_finanziari')->nullable()->comment('Rischi finanziari (-3 a 3)');
            $table->integer('evaluation_risk_organizzativi')->nullable()->comment('Rischi organizzativi (-2 a 2)');
            $table->integer('evaluation_risk_logistici')->nullable()->comment('Rischi logistici (-2 a 2)');

            // Totali calcolati
            $table->integer('evaluation_total_positive')->nullable()->comment('Totale punteggi positivi');
            $table->integer('evaluation_total_negative')->nullable()->comment('Totale punteggi negativi');
            $table->integer('evaluation_total_score')->nullable()->comment('Totale complessivo (positive - negative)');

            // Campi informativi
            $table->foreignId('evaluation_evaluated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('evaluation_evaluated_at')->nullable();

            // Indici
            $table->index(['evaluation_evaluated_by']);
            $table->index(['evaluation_evaluated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fundraising_opportunities', function (Blueprint $table) {
            $table->dropForeign(['evaluation_evaluated_by']);
            $table->dropIndex(['evaluation_evaluated_by']);
            $table->dropIndex(['evaluation_evaluated_at']);
            
            // Parte 1
            $table->dropColumn([
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
            ]);

            // Parte 2 - Requisiti di base
            $table->dropColumn([
                'evaluation_base_coerenza_bando',
                'evaluation_base_capofila_idoneo',
                'evaluation_base_partner_minimi',
                'evaluation_base_cofinanziamento',
                'evaluation_base_tempistiche',
            ]);

            // Parte 2 - Valutazione qualitativa
            $table->dropColumn([
                'evaluation_qual_coerenza_cai',
                'evaluation_qual_imp_ambientale',
                'evaluation_qual_imp_sociale',
                'evaluation_qual_imp_economico',
                'evaluation_qual_obiettivi_chiari',
                'evaluation_qual_solidita_azioni',
                'evaluation_qual_capacita_partner',
            ]);

            // Parte 2 - Fattori premiali
            $table->dropColumn([
                'evaluation_prem_innovazione',
                'evaluation_prem_replicabilita',
                'evaluation_prem_comunita',
                'evaluation_prem_sostenibilita',
            ]);

            // Parte 2 - Rischi
            $table->dropColumn([
                'evaluation_risk_tecnici',
                'evaluation_risk_finanziari',
                'evaluation_risk_organizzativi',
                'evaluation_risk_logistici',
            ]);

            // Totali
            $table->dropColumn([
                'evaluation_total_positive',
                'evaluation_total_negative',
                'evaluation_total_score',
            ]);

            // Campi informativi
            $table->dropColumn([
                'evaluation_evaluated_by',
                'evaluation_evaluated_at',
            ]);
        });
    }
};
