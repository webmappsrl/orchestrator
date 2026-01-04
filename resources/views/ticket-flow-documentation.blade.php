<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Flusso Naturale dell\'Evoluzione dei Ticket') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 40px;
        }

        h1 {
            color: #343a40;
            font-size: 32px;
            margin-bottom: 10px;
            border-bottom: 3px solid #2FBDA5;
            padding-bottom: 15px;
        }

        h2 {
            color: #2FBDA5;
            font-size: 24px;
            margin-top: 40px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        h3 {
            color: #495057;
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        h4 {
            color: #6c757d;
            font-size: 18px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .intro {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .flow-main {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin: 30px 0;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .flow-main .flow-sequence {
            font-size: 24px;
            margin: 15px 0;
            word-spacing: 10px;
        }

        .transition-card {
            background-color: #f8f9fa;
            border-left: 4px solid #2FBDA5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .transition-card h4 {
            color: #2FBDA5;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .transition-card .trigger {
            background-color: #e3f2fd;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #2196F3;
        }

        .transition-card .rule {
            background-color: #fff3e0;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #ff9800;
        }

        .transition-card .description {
            color: #495057;
            margin-top: 10px;
            line-height: 1.6;
        }

        .alternative-flow {
            background-color: #fff9c4;
            border-left: 4px solid #fbc02d;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .alternative-flow h3 {
            color: #f57f17;
            margin-top: 0;
        }

        .automatic-transition {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }

        .automatic-transition h4 {
            color: #2e7d32;
            margin-top: 0;
        }

        .validation-rule {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }

        .validation-rule h4 {
            color: #c62828;
            margin-top: 0;
        }

        .flow-diagram {
            background-color: #f5f5f5;
            padding: 30px;
            border-radius: 8px;
            margin: 30px 0;
            overflow-x: auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .flow-diagram svg {
            max-width: 100%;
            height: auto;
        }

        .state-box {
            stroke-width: 2;
            stroke: #333;
            rx: 8;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .state-box:hover {
            stroke-width: 3;
            filter: brightness(1.1);
        }

        .state-text {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            font-weight: bold;
            text-anchor: middle;
            dominant-baseline: middle;
            pointer-events: none;
        }

        .arrow {
            stroke: #666;
            stroke-width: 2;
            fill: none;
            marker-end: url(#arrowhead);
        }

        .arrow-dashed {
            stroke: #999;
            stroke-width: 2;
            stroke-dasharray: 5,5;
            fill: none;
            marker-end: url(#arrowhead-dashed);
        }

        .arrow-automatic {
            stroke: #4caf50;
            stroke-width: 2;
            fill: none;
            marker-end: url(#arrowhead-green);
        }

        .best-practices {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .best-practices h3 {
            color: #1565c0;
            margin-top: 0;
        }

        .best-practices ul {
            margin: 10px 0;
            padding-left: 25px;
        }

        .best-practices li {
            margin: 8px 0;
            line-height: 1.6;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .action-card {
            background-color: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .action-card h4 {
            color: #2FBDA5;
            margin-top: 0;
        }

        .legend {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .legend-title {
            font-size: 18px;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 15px;
        }

        .legend-item {
            display: inline-block;
            margin: 5px 15px 5px 0;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
        }

        .legend-blue {
            background-color: #e3f2fd;
            border-left: 4px solid #3b82f6;
        }

        .legend-orange {
            background-color: #fff3e0;
            border-left: 4px solid #ea580c;
        }

        .legend-green {
            background-color: #e8f5e9;
            border-left: 4px solid #16a34a;
        }

        .legend-yellow {
            background-color: #fffde7;
            border-left: 4px solid #eab308;
        }

        .legend-red {
            background-color: #ffebee;
            border-left: 4px solid #dc2626;
        }

        .legend-gray {
            background-color: #f5f5f5;
            border-left: 4px solid #64748b;
        }

        ul {
            line-height: 1.8;
        }

        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .note strong {
            color: #856404;
        }

        .index {
            background-color: #f8f9fa;
            border-left: 4px solid #2FBDA5;
            padding: 25px;
            margin: 30px 0;
            border-radius: 8px;
        }

        .index h3 {
            color: #2FBDA5;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .index ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .index li {
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }

        .index li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #2FBDA5;
            font-weight: bold;
        }

        .index a {
            color: #495057;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .index a:hover {
            color: #2FBDA5;
            text-decoration: underline;
        }

        h2[id] {
            scroll-margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Flusso Naturale dell\'Evoluzione dei Ticket') }}</h1>
        <div class="intro">
            {{ __('Questo documento descrive il flusso naturale dell\'evoluzione dei ticket nel sistema Orchestra, basato sulla definizione degli stati e sulle regole di transizione implementate nel codice.') }}
        </div>

        <div class="index">
            <h3>{{ __('Indice') }}</h3>
            <ul>
                <li><a href="#flusso-principale">{{ __('Flusso Principale') }}</a></li>
                <li><a href="#flussi-alternativi">{{ __('Flussi Alternativi') }}</a></li>
                <li><a href="#transizioni-automatiche">{{ __('Transizioni Automatiche') }}</a></li>
                <li><a href="#regole-validazione">{{ __('Regole di Validazione') }}</a></li>
                <li><a href="#diagramma-flusso">{{ __('Diagramma di Flusso Principale') }}</a></li>
                <li><a href="#best-practices">{{ __('Best Practices') }}</a></li>
            </ul>
        </div>

        <h2 id="flusso-principale">{{ __('Flusso Principale (Happy Path)') }}</h2>
        <div class="flow-main">
            <div class="flow-sequence">
                ✨ New → 👤 Assigned → 📋 Todo → ⚡ Progress → 🧪 Testing → ✅ Tested → 🌐 Released → ✔️ Done
            </div>
            <div style="margin-top: 15px; font-size: 18px; opacity: 0.9;">
                {{ __('Alternativa senza testing') }}: ✨ New → 👤 Assigned → 📋 Todo → ⚡ Progress → 🌐 Released → ✔️ Done
            </div>
        </div>

        <h3>{{ __('Dettaglio delle Transizioni') }}</h3>

        <div class="transition-card">
            <h4>✨ New</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>👤 <strong>{{ __('Assigned') }}</strong> - <span style="color: #4caf50; font-weight: bold;">{{ __('Automatico') }}</span> {{ __('quando viene assegnato un developer (user_id)') }}</li>
                <li>⏱️ <strong>{{ __('Backlog') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando viene messo in coda per lavorazione futura') }}</li>
                <li>⚠️ <strong>{{ __('Problem') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando si incontra un problema tecnico') }}</li>
                <li>⏸️ <strong>{{ __('Waiting') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando è in attesa di informazioni o azioni esterne') }}</li>
                <li>❌ <strong>{{ __('Rejected') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando viene rifiutato (solo da New)') }}</li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>⏱️ Backlog</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>👤 <strong>{{ __('Assigned') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il ticket viene preso in carico e assegnato a uno sviluppatore') }}</li>
                <li>⚠️ <strong>{{ __('Problem') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando si incontra un problema tecnico') }}</li>
                <li>⏸️ <strong>{{ __('Waiting') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando è in attesa di informazioni o azioni esterne') }}</li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>👤 Assigned</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>📋 <strong>{{ __('Todo') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando lo sviluppatore è pronto a iniziare il lavoro') }}</li>
                <li>⚠️ <strong>{{ __('Problem') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando si incontra un problema tecnico') }}</li>
                <li>⏸️ <strong>{{ __('Waiting') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando è in attesa di informazioni o azioni esterne') }}</li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>📋 Todo</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>⚡ <strong>{{ __('Progress') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando lo sviluppatore inizia effettivamente a lavorare sul ticket') }}</li>
                <li>⚠️ <strong>{{ __('Problem') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando si incontra un problema tecnico') }}</li>
                <li>⏸️ <strong>{{ __('Waiting') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando è in attesa di informazioni o azioni esterne') }}</li>
            </ul>
            <div class="rule" style="margin-top: 10px;"><strong>{{ __('Regola Speciale') }}:</strong> {{ __('Solo un ticket può essere in Progress per ogni sviluppatore. Quando un ticket passa a Progress, tutti gli altri ticket dello stesso sviluppatore in Progress vengono automaticamente impostati a Todo') }}</div>
        </div>

        <div class="transition-card">
            <h4>⚡ Progress</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>🧪 <strong>{{ __('Testing') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando lo sviluppatore completa il lavoro di sviluppo e il ticket richiede verifica (richiede tester_id)') }}</li>
                <li>🌐 <strong>{{ __('Released') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando lo sviluppatore completa il lavoro e il ticket non richiede verifica di un tester') }}</li>
                <li>📋 <strong>{{ __('Todo') }}</strong> - <span style="color: #4caf50; font-weight: bold;">{{ __('Automatico') }}</span> {{ __('quando un altro ticket dello stesso sviluppatore passa a Progress') }}</li>
                <li>⚠️ <strong>{{ __('Problem') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando si incontra un problema tecnico') }}</li>
                <li>⏸️ <strong>{{ __('Waiting') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando è in attesa di informazioni o azioni esterne') }}</li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>🧪 Testing</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>✅ <strong>{{ __('Tested') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il tester completa i test con successo') }}</li>
                <li>🌐 <strong>{{ __('Released') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il ticket viene rilasciato direttamente senza passare per Tested') }}</li>
                <li>📋 <strong>{{ __('Todo') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il tester ritiene che il test sia fallito') }}</li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>✅ Tested</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>🌐 <strong>{{ __('Released') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il ticket viene rilasciato in produzione') }}</li>
            </ul>
            <div class="rule" style="margin-top: 10px;"><strong>{{ __('Regola') }}:</strong> {{ __('Imposta automaticamente released_at quando lo stato cambia a Released') }}</div>
        </div>

        <div class="transition-card">
            <h4>🌐 Released</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li>✔️ <strong>{{ __('Done') }}</strong> - <span style="color: #4caf50; font-weight: bold;">{{ __('Automatico') }}</span> {{ __('dopo 3 giorni lavorativi dalla data di released_at (comando schedulato alle 07:45)') }} <strong>{{ __('oppure') }}</strong> <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span></li>
            </ul>
        </div>

        <div class="transition-card">
            <h4>⚠️ Problem</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li><strong>{{ __('Stato precedente') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando il problema viene risolto, il ticket torna allo stato precedente da cui era partito') }}</li>
            </ul>
            <div class="rule" style="margin-top: 10px;"><strong>{{ __('Regola') }}:</strong> {{ __('Richiede obbligatoriamente la compilazione del campo problem_reason') }}</div>
        </div>

        <div class="transition-card">
            <h4>⏸️ Waiting</h4>
            <div class="trigger"><strong>{{ __('Può evolvere in') }}:</strong></div>
            <ul>
                <li><strong>{{ __('Stato precedente') }}</strong> - <span style="color: #666; font-weight: bold;">{{ __('Manuale') }}</span> {{ __('quando l\'attesa termina, il ticket torna allo stato precedente da cui era partito') }}</li>
            </ul>
            <div class="rule" style="margin-top: 10px;"><strong>{{ __('Regola') }}:</strong> {{ __('Richiede obbligatoriamente la compilazione del campo waiting_reason') }}</div>
            <div class="rule" style="margin-top: 10px;"><strong>{{ __('Reminder') }}:</strong> {{ __('I ticket in Waiting da più di 3 giorni lavorativi ricevono automaticamente un reminder email') }}</div>
        </div>

        <div class="transition-card">
            <h4>❌ Rejected</h4>
            <div class="trigger"><strong>{{ __('Stato finale') }}</strong></div>
            <div class="description">{{ __('Il ticket è stato rifiutato e generalmente non viene più lavorato. Non può evolvere in altri stati. Può essere raggiunto solo da New.') }}</div>
        </div>

        <div class="transition-card">
            <h4>✔️ Done</h4>
            <div class="trigger"><strong>{{ __('Stato finale') }}</strong></div>
            <div class="description">{{ __('Il ticket è completamente terminato e chiuso. Non può evolvere in altri stati.') }}</div>
        </div>

        <h2 id="flussi-alternativi">{{ __('Flussi Alternativi') }}</h2>

        <div class="alternative-flow">
            <h3>{{ __('Flusso Backlog') }}</h3>
            <p><strong>{{ __('Sequenza') }}:</strong> ✨ New → ⏱️ Backlog → 👤 Assigned → [continua flusso principale]</p>
            <ul>
                <li><strong>New → Backlog:</strong> {{ __('Quando un ticket viene messo in coda per lavorazione futura') }}</li>
                <li><strong>Backlog → Assigned:</strong> {{ __('Quando il ticket viene preso in carico e assegnato a uno sviluppatore') }}</li>
            </ul>
        </div>

        <h3>{{ __('Flusso Problem e Waiting') }}</h3>
        <p>{{ __('Problem e Waiting sono stati speciali che possono essere impostati durante il flusso di lavorazione e permettono di tornare allo stato precedente quando il problema viene risolto o l\'attesa termina.') }}</p>
        
        <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border: 2px solid #e0e0e0; margin: 30px 0;">
            <p style="text-align: center; color: #6c757d; font-style: italic; margin-bottom: 20px;">
                {{ __('Esempio con stato New. Lo stesso flusso si applica anche a Backlog, Assigned, Todo e Progress.') }}
            </p>
            
            <div style="margin: 20px 0;">
                <h4>{{ __('Transizioni verso Problem e Waiting') }}</h4>
                <p><strong>{{ __('Stati da cui si può passare a Problem/Waiting') }}:</strong> ✨ New, ⏱️ Backlog, 👤 Assigned, 📋 Todo, ⚡ Progress</p>
                <ul>
                    <li><strong>Stato Sorgente → ⚠️ Problem:</strong> {{ __('La freccia parte dal bordo superiore dello stato sorgente e arriva al bordo sinistro di Problem') }}</li>
                    <li><strong>Stato Sorgente → ⏸️ Waiting:</strong> {{ __('La freccia parte dal bordo destro dello stato sorgente e arriva al bordo sinistro di Waiting') }}</li>
                </ul>
            </div>

            <div style="margin: 20px 0;">
                <h4>{{ __('Transizioni di Ritorno') }}</h4>
                <ul>
                    <li><strong>⚠️ Problem → Stato Precedente:</strong> {{ __('La freccia parte dal bordo destro di Problem e arriva al bordo sinistro dello stato precedente (linea tratteggiata)') }}</li>
                    <li><strong>⏸️ Waiting → Stato Precedente:</strong> {{ __('La freccia parte dal bordo destro di Waiting e arriva al bordo sinistro dello stato precedente (linea tratteggiata)') }}</li>
                </ul>
                <p><em>{{ __('Nota: Le frecce di ritorno sono tratteggiate per indicare che rappresentano un ritorno allo stato precedente dopo la risoluzione del problema o la fine dell\'attesa.') }}</em></p>
            </div>
        </div>

        <div class="alternative-flow" style="margin-top: 30px;">
            <h4>{{ __('Flusso Problem') }}</h4>
            <p><strong>{{ __('Sequenza') }}:</strong> [New/Backlog/Assigned/Todo/Progress] → ⚠️ Problem → [stato precedente]</p>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Lo sviluppatore incontra un problema tecnico che non riesce a risolvere autonomamente') }}</p>
            <p><strong>{{ __('Regola') }}:</strong> {{ __('Richiede obbligatoriamente la compilazione del campo problem_reason') }}</p>
            <p><strong>{{ __('Stati da cui si può passare a Problem') }}:</strong> New, Backlog, Assigned, Todo, Progress</p>
            <p><strong>{{ __('Risoluzione') }}:</strong> {{ __('Dopo aver risolto il problema, il ticket torna solo allo stato precedente da cui era partito') }}</p>
        </div>

        <div class="alternative-flow">
            <h4>{{ __('Flusso Waiting') }}</h4>
            <p><strong>{{ __('Sequenza') }}:</strong> [New/Backlog/Assigned/Todo/Progress] → ⏸️ Waiting → [stato precedente]</p>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Il ticket è in pausa in attesa di informazioni, approvazioni o azioni esterne') }}</p>
            <p><strong>{{ __('Regola') }}:</strong> {{ __('Richiede obbligatoriamente la compilazione del campo waiting_reason') }}</p>
            <p><strong>{{ __('Stati da cui si può passare a Waiting') }}:</strong> New, Backlog, Assigned, Todo, Progress</p>
            <p><strong>{{ __('Risoluzione') }}:</strong> {{ __('Quando l\'attesa termina, il ticket torna solo allo stato precedente da cui era partito') }}</p>
            <p><strong>{{ __('Reminder') }}:</strong> {{ __('I ticket in Waiting da più di 3 giorni lavorativi ricevono automaticamente un reminder email') }}</p>
        </div>

        <div class="alternative-flow">
            <h3>{{ __('Flusso Rejected') }}</h3>
            <p><strong>{{ __('Sequenza') }}:</strong> ✨ New → ❌ Rejected</p>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Il ticket viene rifiutato e non verrà implementato') }}</p>
            <p><strong>{{ __('Nota') }}:</strong> {{ __('Rejected può essere raggiunto solo da New. Una volta Rejected, il ticket generalmente non viene più lavorato.') }}</p>
        </div>

        <h2 id="transizioni-automatiche">{{ __('Transizioni Automatiche') }}</h2>

        <div class="automatic-transition">
            <h4>1. {{ __('Assegnazione Automatica (New → Assigned)') }}</h4>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Un ticket con stato New riceve un user_id (developer)') }}</p>
            <p><strong>{{ __('Comportamento') }}:</strong> {{ __('Lo stato cambia automaticamente a Assigned') }}</p>
        </div>

        <div class="automatic-transition">
            <h4>2. {{ __('Solo un Progress per Developer') }}</h4>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Un ticket viene impostato a Progress') }}</p>
            <p><strong>{{ __('Comportamento') }}:</strong> {{ __('Tutti gli altri ticket dello stesso sviluppatore in Progress vengono impostati a Todo') }}</p>
        </div>

        <div class="automatic-transition">
            <h4>3. {{ __('Progress → Todo (Automatico alle 18:00)') }}</h4>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Giornaliero alle 18:00') }}</p>
            <p><strong>{{ __('Comportamento') }}:</strong> {{ __('Tutti i ticket in Progress vengono impostati a Todo per evitare che rimangano in lavorazione durante la notte') }}</p>
        </div>

        <div class="automatic-transition">
            <h4>4. {{ __('Released → Done (Automatico dopo 3 giorni)') }}</h4>
            <p><strong>{{ __('Quando') }}:</strong> {{ __('Giornaliero alle 07:45, per ticket rilasciati da almeno 3 giorni lavorativi') }}</p>
            <p><strong>{{ __('Comportamento') }}:</strong> {{ __('I ticket in Released da più di 3 giorni lavorativi vengono impostati a Done') }}</p>
        </div>

        <h2 id="regole-validazione">{{ __('Regole di Validazione') }}</h2>

        <div class="validation-rule">
            <h4>1. {{ __('Testing richiede Tester') }}</h4>
            <p>{{ __('Non è possibile passare a Testing senza aver assegnato un tester_id') }}</p>
            <p><code>{{ __('Errore') }}: "{{ __('Impossibile cambiare lo stato a \'Da testare\' senza avere assegnato un tester.') }}"</code></p>
        </div>

        <div class="validation-rule">
            <h4>2. {{ __('Waiting richiede Motivo') }}</h4>
            <p>{{ __('Non è possibile passare a Waiting senza aver compilato waiting_reason') }}</p>
            <p><code>{{ __('Errore') }}: "{{ __('Impossibile cambiare lo stato a \'In attesa\' senza specificare il motivo dell\'attesa.') }}"</code></p>
        </div>

        <div class="validation-rule">
            <h4>3. {{ __('Problem richiede Descrizione') }}</h4>
            <p>{{ __('Non è possibile passare a Problem senza aver compilato problem_reason') }}</p>
            <p><code>{{ __('Errore') }}: "{{ __('Impossibile cambiare lo stato a \'Problema\' senza specificare la descrizione del problema.') }}"</code></p>
        </div>

        <h2 id="diagramma-flusso">{{ __('Diagramma di Flusso Principale') }}</h2>
        <div class="flow-diagram" style="background: #f8f9fa; padding: 30px; border-radius: 8px; border: 2px solid #e0e0e0;">
            <h3>{{ __('Descrizione del Flusso Principale') }}</h3>
            
            <div style="margin: 20px 0;">
                <h4>{{ __('Flusso Lineare Principale') }}</h4>
                <p><strong>✨ New</strong> → <strong>👤 Assigned</strong> → <strong>📋 Todo</strong> → <strong>⚡ Progress</strong> → <strong>🧪 Testing</strong> → <strong>✅ Tested</strong> → <strong>🌐 Released</strong> → <strong>✔️ Done</strong></p>
                <ul>
                    <li><strong>New → Assigned:</strong> {{ __('Transizione automatica quando viene assegnato uno sviluppatore (user_id)') }}</li>
                    <li><strong>Assigned → Todo:</strong> {{ __('Transizione manuale quando lo sviluppatore inizia a lavorare') }}</li>
                    <li><strong>Todo → Progress:</strong> {{ __('Transizione manuale quando lo sviluppatore è in fase di sviluppo attivo') }}</li>
                    <li><strong>Progress → Testing:</strong> {{ __('Transizione manuale quando lo sviluppo è completato e il ticket richiede verifica') }}</li>
                    <li><strong>Testing → Tested:</strong> {{ __('Transizione manuale quando il tester completa i test con successo') }}</li>
                    <li><strong>Tested → Released:</strong> {{ __('Transizione manuale quando il ticket viene rilasciato in produzione') }}</li>
                    <li><strong>Released → Done:</strong> {{ __('Transizione automatica dopo 3 giorni lavorativi dalla data di released_at') }}</li>
                </ul>
            </div>

            <div style="margin: 20px 0;">
                <h4>{{ __('Flusso Alternativo con Backlog') }}</h4>
                <p><strong>✨ New</strong> → <strong>⏱️ Backlog</strong> → <strong>👤 Assigned</strong> → [continua flusso principale]</p>
                <ul>
                    <li><strong>New → Backlog:</strong> {{ __('Transizione manuale quando un ticket viene messo in coda per lavorazione futura') }}</li>
                    <li><strong>Backlog → Assigned:</strong> {{ __('Transizione manuale quando il ticket viene preso in carico e assegnato a uno sviluppatore') }}</li>
                </ul>
            </div>

            <div style="margin: 20px 0;">
                <h4>{{ __('Transizioni Alternative') }}</h4>
                <ul>
                    <li><strong>⚡ Progress → 🌐 Released:</strong> {{ __('Transizione diretta quando il ticket non richiede verifica di un tester (senza passare per Testing)') }}</li>
                    <li><strong>🧪 Testing → 📋 Todo:</strong> {{ __('Transizione quando il test viene ritenuto fallito dal tester, permettendo allo sviluppatore di correggere i problemi') }}</li>
                    <li><strong>Qualsiasi stato → ❌ Rejected:</strong> {{ __('Transizione manuale per rifiutare un ticket in qualsiasi momento del flusso') }}</li>
                </ul>
            </div>
        </div>

        <div class="legend">
            <div class="legend-title">{{ __('Legenda Colori del Diagramma') }}</div>
            <div class="legend-item legend-blue">🔵 {{ __('Blu') }}: {{ __('Stati iniziali (New)') }}</div>
            <div class="legend-item legend-orange">🟠 {{ __('Arancione') }}: {{ __('Stati di lavorazione (Assigned, Todo, Progress, Testing)') }}</div>
            <div class="legend-item legend-green">🟢 {{ __('Verde') }}: {{ __('Stati di completamento (Tested, Released, Done)') }}</div>
            <div class="legend-item legend-yellow">🟡 {{ __('Giallo') }}: {{ __('Stati di attesa (Waiting)') }}</div>
            <div class="legend-item legend-red">🔴 {{ __('Rosso') }}: {{ __('Stati di blocco/rifiuto (Problem, Rejected)') }}</div>
            <div class="legend-item legend-gray">⚪ {{ __('Grigio') }}: {{ __('Backlog') }}</div>
        </div>

        <h2 id="best-practices">{{ __('Best Practices') }}</h2>

        <div class="best-practices">
            <h3>{{ __('Per gli Sviluppatori') }}</h3>
            <ul>
                <li><strong>{{ __('Inizia sempre da Todo') }}:</strong> {{ __('Quando prendi in carico un ticket, assicurati che sia in Todo prima di passarlo a Progress') }}</li>
                <li><strong>{{ __('Un Progress alla volta') }}:</strong> {{ __('Ricorda che solo un ticket può essere in Progress per te alla volta') }}</li>
                <li><strong>{{ __('Usa Problem per blocchi tecnici') }}:</strong> {{ __('Se incontri un problema tecnico, passa a Problem e descrivi il problema nel campo problem_reason') }}</li>
                <li><strong>{{ __('Usa Waiting per attese esterne') }}:</strong> {{ __('Se devi aspettare informazioni o approvazioni, passa a Waiting e specifica il motivo') }}</li>
                <li><strong>{{ __('Assegna sempre un Tester') }}:</strong> {{ __('Prima di passare a Testing, assicurati che sia assegnato un tester') }}</li>
            </ul>
        </div>

        <div class="best-practices">
            <h3>{{ __('Per i Tester') }}</h3>
            <ul>
                <li><strong>{{ __('Testa solo ticket in Testing') }}:</strong> {{ __('I ticket in Testing sono quelli completati dallo sviluppatore e pronti per essere testati') }}</li>
                <li><strong>{{ __('Passa a Tested solo se OK') }}:</strong> {{ __('Passa a Tested solo se i test sono completati con successo') }}</li>
                <li><strong>{{ __('Rifiuta se necessario') }}:</strong> {{ __('Se il ticket non è implementato correttamente, puoi rifiutarlo o richiedere modifiche') }}</li>
            </ul>
        </div>

        <div class="best-practices">
            <h3>{{ __('Per i Manager/Admin') }}</h3>
            <ul>
                <li><strong>{{ __('Assegna ticket da New') }}:</strong> {{ __('Quando crei un ticket, assegnarlo immediatamente a uno sviluppatore lo porta automaticamente a Assigned') }}</li>
                <li><strong>{{ __('Usa Backlog per priorità') }}:</strong> {{ __('I ticket meno prioritari possono essere messi in Backlog per lavorazione futura') }}</li>
                <li><strong>{{ __('Monitora Waiting e Problem') }}:</strong> {{ __('I ticket in Waiting e Problem richiedono attenzione e possono bloccare il flusso di lavoro') }}</li>
            </ul>
        </div>

        <div class="note">
            <strong>{{ __('Note Importanti') }}:</strong>
            <ul>
                <li>{{ __('Transizioni Automatiche') }}: {{ __('Alcune transizioni avvengono automaticamente tramite comandi schedulati. Consulta la documentazione per i dettagli completi.') }}</li>
                <li>{{ __('Story Logs') }}: {{ __('Tutte le modifiche di stato vengono registrate in story_logs per tracciabilità completa.') }}</li>
                <li>{{ __('Epic Status') }}: {{ __('Lo status degli Epic viene aggiornato automaticamente in base agli status delle story figlie.') }}</li>
                <li>{{ __('Notifiche') }}: {{ __('Le modifiche di stato generano notifiche email e Nova per gli utenti interessati (developer/tester).') }}</li>
            </ul>
        </div>
    </div>
</body>
</html>

