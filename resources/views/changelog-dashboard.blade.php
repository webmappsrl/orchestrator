<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Changelog') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }

        h1 {
            color: #343a40;
            font-size: 28px;
            margin-bottom: 10px;
            border-bottom: 3px solid #2FBDA5;
            padding-bottom: 10px;
        }

        .intro {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .release-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .release-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .release-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #2FBDA5;
        }

        .release-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .release-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .release-version {
            font-size: 24px;
            font-weight: bold;
            color: #2FBDA5;
            margin: 0;
        }

        .release-date {
            font-size: 14px;
            color: #6c757d;
            font-style: italic;
        }

        .release-content {
            padding: 0;
        }

        .release-summary {
            color: #343a40;
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-line;
            text-align: left;
        }

        .release-html-content {
            text-align: left;
        }

        .release-html-content h1 {
            border: none;
            padding: 0;
            margin: 20px 0 15px 0;
            font-size: 24px;
        }

        .release-html-content h2 {
            margin: 20px 0 10px 0;
            font-size: 20px;
            color: #2FBDA5;
        }

        .release-html-content h3 {
            margin: 15px 0 8px 0;
            font-size: 16px;
            color: #495057;
        }

        .release-html-content ul {
            list-style-type: disc;
            padding-left: 20px;
            margin: 10px 0;
        }

        .release-html-content li {
            margin: 5px 0;
        }

        .release-html-content p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .release-html-content hr {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 20px 0;
        }

        .release-html-content strong {
            font-weight: bold;
        }

        .release-html-content em {
            font-style: italic;
        }

        .release-html-content a {
            color: #2FBDA5;
            text-decoration: none;
        }

        .release-html-content a:hover {
            text-decoration: underline;
        }

        .no-changelog {
            text-align: center;
            color: #6c757d;
            font-size: 16px;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Changelog') }}</h1>
        <div class="intro">
            {{ __('Riepilogo delle release con le nuove funzionalità e i miglioramenti apportati al sistema.') }}
        </div>

        <div class="release-list">
            <!-- MS-1.17.1 -->
            <div class="release-card">
                <div class="release-header">
                    <h2 class="release-version">MS-1.17.1</h2>
                    <span class="release-date">29 Ottobre 2025</span>
                </div>
                <div class="release-content">
                    <div class="release-html-content">
                        <h1>🚀 Release MS-1.17.1 - Aggiornamento Piattaforma</h1>

                        <p><strong>Ciao!</strong> 👋</p>

                        <p>Siamo lieti di comunicarvi l'aggiornamento <strong>MS-1.17.1</strong> della piattaforma Orchestrator che introduce miglioramenti significativi nell'automazione e nella gestione delle comunicazioni.</p>

                        <hr>

                        <h2>🎯 COSA C'È DI NUOVO</h2>

                        <p>Questa release migliora l'esperienza di utilizzo della piattaforma attraverso l'automazione di processi che prima richiedevano interventi manuali, garantendo maggiore efficienza e affidabilità.</p>

                        <hr>

                        <h2>🌟 FEATURE PER TUTTI I RUOLI</h2>

                        <h3>📧 Processamento Email Automatico</h3>
                        <ul>
                        <li><strong>Email processate ogni 5 minuti</strong> - Le email in arrivo vengono ora processate automaticamente ogni 5 minuti, invece di richiedere interventi manuali</li>
                        <li><strong>Maggiore velocità</strong> - Le vostre richieste e comunicazioni vengono elaborate più rapidamente</li>
                        <li><strong>Affidabilità migliorata</strong> - Sistema più robusto per garantire che tutte le email vengano gestite correttamente</li>
                        </ul>

                        <h3>📊 Aggiornamenti Automatici</h3>
                        <ul>
                        <li><strong>Sincronizzazione automatica</strong> - I task vengono aggiornati e sincronizzati automaticamente durante la giornata</li>
                        <li><strong>Meno lavoro manuale</strong> - Le attività di routine vengono gestite dalla piattaforma, permettendovi di concentrarvi sul lavoro importante</li>
                        <li><strong>Consistenza migliorata</strong> - Gli aggiornamenti automatici garantiscono maggiore coerenza nei dati</li>
                        </ul>

                        <hr>

                        <h2>👨‍💼 FEATURE SPECIFICHE PER ADMIN</h2>

                        <h3>⚙️ Configurazione Task Schedulati</h3>
                        <ul>
                        <li><strong>Controllo granulare</strong> - Possibilità di configurare quali task automatici abilitare tramite variabili di ambiente nel file <code>.env</code></li>
                        <li><strong>Sicurezza migliorata</strong> - Tutti i task sono disabilitati di default, richiedendo una configurazione esplicita per essere attivati</li>
                        <li><strong>Configurazione centralizzata</strong> - Gestione di tutti i task schedulati tramite file di configurazione dedicato</li>
                        </ul>

                        <h3>📧 Dashboard Mailpit</h3>
                        <ul>
                        <li><strong>Monitoraggio email</strong> - Nuova dashboard web disponibile su <a href="http://localhost:8025">http://localhost:8025</a> per visualizzare tutte le email inviate dall'applicazione</li>
                        <li><strong>Debug migliorato</strong> - Interfaccia semplice per testare e monitorare le email</li>
                        <li><strong>Log completo</strong> - Storia completa delle email per analisi e troubleshooting</li>
                        </ul>

                        <h3>🔧 Gestione Avanzata</h3>
                        <ul>
                        <li><strong>Configurazione flessibile</strong> - Possibilità di abilitare/disabilitare singoli task in base alle necessità dell'ambiente</li>
                        <li><strong>Monitoraggio task</strong> - Verifica dello stato dei task schedulati tramite comandi dedicati</li>
                        </ul>

                        <hr>

                        <h2>👨‍💻 FEATURE SPECIFICHE PER DEVELOPER</h2>

                        <h3>📋 Gestione Ticket Automatica</h3>
                        <ul>
                        <li><strong>Story Progress to Todo</strong> - Le story in stato "Progress" vengono automaticamente spostate a "Todo" alle 18:00</li>
                        <li><strong>Story Scrum to Done</strong> - Le story di tipo "Scrum" vengono processate automaticamente alle 16:00</li>
                        <li><strong>Auto Update Status</strong> - Lo stato delle story viene aggiornato automaticamente alle 07:45 in base alle condizioni configurate</li>
                        </ul>

                        <h3>📅 Sincronizzazione Calendario</h3>
                        <ul>
                        <li><strong>Sync Google Calendar</strong> - Sincronizzazione automatica con Google Calendar ogni mattina alle 07:45</li>
                        <li><strong>Gestione eventi</strong> - I ticket vengono automaticamente aggiunti al calendario con le informazioni corrette</li>
                        <li><strong>Aggiornamenti in tempo reale</strong> - Le modifiche ai ticket vengono riflesse nel calendario</li>
                        </ul>

                        <h3>💼 Workflow Ottimizzato</h3>
                        <ul>
                        <li><strong>Meno interruzioni</strong> - I task di routine vengono gestiti automaticamente, permettendo di concentrarsi sullo sviluppo</li>
                        <li><strong>Tracking migliorato</strong> - Migliore visibilità sullo stato dei ticket e sulle attività</li>
                        <li><strong>Automazione intelligente</strong> - La piattaforma gestisce automaticamente le transizioni di stato dei ticket</li>
                        </ul>

                        <hr>

                        <h2>💰 FEATURE PER FUNDRAISING</h2>

                        <h3>📧 Comunicazioni Progetti</h3>
                        <ul>
                        <li><strong>Processamento email migliorato</strong> - Le email relative ai progetti di fundraising vengono processate ogni 5 minuti</li>
                        <li><strong>Comunicazione più efficiente</strong> - Sistema più affidabile per tutte le comunicazioni relative ai progetti</li>
                        <li><strong>Tracking migliorato</strong> - Migliore visibilità sulle comunicazioni e aggiornamenti dei progetti</li>
                        </ul>

                        <h3>📊 Gestione Progetti Automatica</h3>
                        <ul>
                        <li><strong>Aggiornamenti automatici</strong> - Lo stato dei progetti viene aggiornato automaticamente</li>
                        <li><strong>Sincronizzazione calendario</strong> - I progetti vengono sincronizzati automaticamente con il calendario per una migliore pianificazione</li>
                        <li><strong>Meno attività manuali</strong> - Le attività di routine vengono gestite automaticamente</li>
                        </ul>

                        <hr>

                        <h2>🏢 FEATURE PER CUSTOMER</h2>

                        <h3>📧 Comunicazioni Migliorate</h3>
                        <ul>
                        <li><strong>Risposte più rapide</strong> - Le vostre email vengono processate automaticamente ogni 5 minuti, garantendo risposte più veloci alle vostre richieste</li>
                        <li><strong>Affidabilità</strong> - Sistema migliorato per garantire che tutte le vostre richieste vengano gestite correttamente</li>
                        <li><strong>Comunicazione trasparente</strong> - Migliore visibilità sullo stato delle comunicazioni</li>
                        </ul>

                        <h3>📊 Tracking Ticket Migliorato</h3>
                        <ul>
                        <li><strong>Aggiornamenti automatici</strong> - I vostri ticket vengono aggiornati automaticamente durante la giornata</li>
                        <li><strong>Maggiore trasparenza</strong> - Migliore visibilità sullo stato dei vostri progetti e richieste</li>
                        <li><strong>Informazioni in tempo reale</strong> - Aggiornamenti automatici sui progressi dei vostri progetti</li>
                        </ul>

                        <hr>

                        <h2>📋 DETTAGLI RILASCIO</h2>

                        <ul>
                        <li><strong>Versione:</strong> MS-1.17.1</li>
                        <li><strong>Data:</strong> 29 Ottobre 2025</li>
                        <li><strong>Stato:</strong> Disponibile</li>
                        </ul>

                        <hr>

                        <h2>⚠️ NOTA IMPORTANTE</h2>

                        <p>Le nuove funzionalità automatiche devono essere configurate dall'amministratore di sistema. Se notate che alcune funzionalità automatiche non sono attive, contattate il team tecnico per verificare la configurazione.</p>

                        <hr>

                        <h2>🎉 GRAZIE!</h2>

                        <p>Questo aggiornamento migliora l'esperienza di utilizzo della piattaforma per tutti gli utenti. Continuiamo a lavorare per rendere Orchestrator sempre più efficiente e facile da usare.</p>

                        <p><strong>Buon lavoro!</strong> 🙌</p>

                        <hr>

                        <p><strong>Team Orchestrator</strong><br><em>Webmapp S.r.l.</em></p>

                        <p><em>Per domande o assistenza, contattate il team tecnico.</em></p>
                    </div>
                </div>
            </div>

            <!-- MS-1.16.1 -->
            <div class="release-card">
                <div class="release-header">
                    <h2 class="release-version">MS-1.16.1</h2>
                    <span class="release-date">27 Settembre 2025</span>
                </div>
                <div class="release-content">
                    <div class="release-html-content">
                        <h1>🚀 Release MS-1.16.1 - Sistema FundRaising</h1>

                        <p><strong>Ciao Team!</strong> 👋</p>

                        <p>Siamo orgogliosi di annunciare la <strong>Release MS-1.16.1</strong> - una versione significativa che introduce il <strong>nuovo sistema FundRaising</strong> completamente integrato nella nostra piattaforma Orchestrator.</p>

                        <hr>

                        <h2>🎯 COSA C'È DI NUOVO</h2>

                        <h3>📊 Sistema FundRaising Completo</h3>
                        <ul>
                        <li><strong>Gestione Opportunità di Finanziamento</strong> - Creazione e gestione completa delle opportunità di finanziamento con tutti i dettagli necessari</li>
                        <li><strong>Gestione Progetti di Fundraising</strong> - Progetti collegati alle opportunità, con gestione capofila e partner</li>
                        <li><strong>Import JSON</strong> - Import rapido di opportunità da dati esterni con action dedicata</li>
                        </ul>

                        <h3>👥 Nuovi Ruoli e Permessi</h3>
                        <ul>
                        <li><strong>Ruolo "Fundraising"</strong> - Accesso completo al sistema per gestori fundraising</li>
                        <li><strong>Dashboard Customer Potenziata</strong> - I clienti ora vedono le loro opportunità e progetti in una dashboard dedicata</li>
                        <li><strong>Controllo Accessi Granulare</strong> - Ogni utente vede solo quello che gli serve</li>
                        </ul>

                        <h3>🎛️ Interfaccia Migliorata</h3>
                        <ul>
                        <li><strong>Menu Personalizzato</strong> - Sezioni diverse per fundraising e customer</li>
                        <li><strong>Filtri Avanzati</strong> - Per scope territoriale, stato progetti, scadenze</li>
                        <li><strong>Actions Personalizzate</strong> - Workflow ottimizzato per ogni ruolo</li>
                        </ul>

                        <hr>

                        <h2>🔧 MIGLIORAMENTI TECNICI</h2>

                        <ul>
                        <li><strong>Laravel Debugbar</strong> integrata per debugging più efficiente</li>
                        <li><strong>Database ottimizzato</strong> con nuove tabelle e relazioni</li>
                        <li><strong>Codice pulito</strong> - Rimossi componenti problematici</li>
                        <li><strong>Performance migliorate</strong> con query ottimizzate</li>
                        </ul>

                        <hr>

                        <h2>👥 PER CHI È QUESTA RELEASE</h2>

                        <h3>👨‍💻 Sviluppatori</h3>
                        <ul>
                        <li>Nuovo sistema di gestione progetti fundraising</li>
                        <li>Debugbar per sviluppo più efficiente</li>
                        <li>API e database estesi</li>
                        </ul>

                        <h3>👤 Utenti Fundraising</h3>
                        <ul>
                        <li>Interfaccia dedicata per opportunità e progetti</li>
                        <li>Import rapido da dati esterni</li>
                        <li>Dashboard completa</li>
                        </ul>

                        <h3>🏢 Clienti</h3>
                        <ul>
                        <li>Accesso ai propri progetti di fundraising</li>
                        <li>Dashboard personalizzata</li>
                        <li>Visibilità su opportunità attive</li>
                        </ul>

                        <hr>

                        <h2>📋 DETTAGLI RILASCIO</h2>

                        <ul>
                        <li><strong>Versione:</strong> MS-1.16.1</li>
                        <li><strong>Data:</strong> 27 Settembre 2025</li>
                        <li><strong>Branch:</strong> montagna-servizi</li>
                        <li><strong>Tag:</strong> MS-1.16.1</li>
                        </ul>

                        <hr>

                        <h2>🚀 PROSSIMI PASSI</h2>

                        <ul>
                        <li><strong>Deployment</strong> - La release è pronta per il deploy in produzione</li>
                        <li><strong>Testing</strong> - Invitiamo tutti a testare le nuove funzionalità</li>
                        <li><strong>Feedback</strong> - Condividete i vostri commenti e suggerimenti</li>
                        </ul>

                        <hr>

                        <h2>🎉 GRAZIE!</h2>

                        <p>Un ringraziamento speciale a tutto il team per il lavoro straordinario che ha reso possibile questa release. Il sistema FundRaising rappresenta un importante passo avanti per la nostra piattaforma.</p>

                        <p><strong>Buon lavoro a tutti!</strong> 🙌</p>

                        <hr>

                        <p><strong>Team Orchestrator</strong><br><em>Webmapp S.r.l.</em></p>

                        <p><em>Per dettagli tecnici completi, consultare il CHANGELOG-MS-1.16.1.md</em></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

