<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunità di Finanziamento - {{ $opportunity->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #18b69b;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #18b69b;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: #18b69b;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            font-size: 18px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .deadline {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .deadline.warning {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .requirements {
            background-color: #f8f9fa;
            border-left: 4px solid #18b69b;
            padding: 15px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $opportunity->name }}</h1>
        <p><strong>Sponsor:</strong> {{ $opportunity->sponsor ?? 'Non specificato' }}</p>
        <p><strong>Programma:</strong> {{ $opportunity->program_name ?? 'Non specificato' }}</p>
    </div>

    <div class="section">
        <h2>Informazioni Generali</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Fondo di Dotazione:</span><br>
                <span class="info-value">
                    @if($opportunity->endowment_fund)
                        € {{ number_format($opportunity->endowment_fund, 2, ',', '.') }}
                    @else
                        Non specificato
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Contributo Massimo:</span><br>
                <span class="info-value">
                    @if($opportunity->max_contribution)
                        € {{ number_format($opportunity->max_contribution, 2, ',', '.') }}
                    @else
                        Non specificato
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Quota Cofinanziamento:</span><br>
                <span class="info-value">
                    @if($opportunity->cofinancing_quota)
                        {{ $opportunity->cofinancing_quota }}%
                    @else
                        Non specificato
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Scope Territoriale:</span><br>
                <span class="info-value">{{ $opportunity->getTerritorialScopeLabelAttribute() }}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Scadenze e Contatti</h2>
        <div class="deadline {{ $opportunity->isExpired() ? 'warning' : '' }}">
            <strong>Data di Scadenza:</strong> {{ $opportunity->deadline->format('d/m/Y') }}
            <span class="status-badge {{ $opportunity->isExpired() ? 'status-expired' : 'status-active' }}">
                {{ $opportunity->isExpired() ? 'SCADUTO' : 'ATTIVO' }}
            </span>
        </div>
        
        @if($opportunity->official_url)
        <div class="info-item">
            <span class="info-label">URL Ufficiale:</span><br>
            <span class="info-value">{{ $opportunity->official_url }}</span>
        </div>
        @endif

        <div class="info-item">
            <span class="info-label">Responsabile:</span><br>
            <span class="info-value">{{ $opportunity->responsibleUser->name ?? 'Non assegnato' }}</span>
        </div>
    </div>

    @if($opportunity->beneficiary_requirements)
    <div class="section">
        <h2>Requisiti del Beneficiario</h2>
        <div class="requirements">
            {!! nl2br(e($opportunity->beneficiary_requirements)) !!}
        </div>
    </div>
    @endif

    @if($opportunity->lead_requirements)
    <div class="section">
        <h2>Requisiti del Capofila</h2>
        <div class="requirements">
            {!! nl2br(e($opportunity->lead_requirements)) !!}
        </div>
    </div>
    @endif

    <div class="footer">
        <p>Documento generato il {{ now()->format('d/m/Y H:i') }} da Montagna Servizi</p>
        <p>Per maggiori informazioni, contattare il responsabile del progetto</p>
    </div>
</body>
</html>
