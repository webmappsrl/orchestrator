Nova.booting(function (app) {
    app.component('team-performance-card', {
        template: `
<div class="team-performance p-6">
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mr-auto">
            Team Performance
        </h2>

        <select
            v-if="developers.length > 1"
            v-model="selectedDeveloperId"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option v-for="dev in developers" :key="dev.id" :value="dev.id">{{ dev.name }}</option>
        </select>
        <span v-else class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ developers[0] && developers[0].name }}</span>

        <select
            v-model="selectedYear"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
        </select>

        <select
            v-model="selectedQuarter"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option value="1">Q1 (Gen-Mar)</option>
            <option value="2">Q2 (Apr-Giu)</option>
            <option value="3">Q3 (Lug-Set)</option>
            <option value="4">Q4 (Ott-Dic)</option>
        </select>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    <div v-else-if="tickets.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
        Nessun ticket Bug/Feature chiuso in questo periodo.
    </div>

    <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Ticket</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Tipo</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Minuti totali trascorsi in stato 'progress'. Esclude il tempo in altri stati (todo, testing, ecc.). Fonte: StoryLog.">Cycle Time ⓘ</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Numero di volte che il ticket è tornato a progress/todo da uno stato avanzato (testing, tested, released). Indica rilavorazioni.">Reopen ⓘ</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="✓ se il cycle time è entro il benchmark. Benchmark: ore stimate × 60 min se presenti, altrimenti media del cycle time del team nel quarter.">On Time ⓘ</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Numero di commit su GitHub che richiamano questo ticket (pattern: oc seguito da max 3 caratteri e poi il numero, es. oc:1234, oc-1234).">Commit ⓘ</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Numero di Pull Request su GitHub collegate al ticket.">PR ⓘ</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Numero totale di review ricevute sulle PR collegate (commenti, approvazioni, richieste di modifica).">Reviews ⓘ</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <td class="py-3 px-3">
                        <a :href="ticket.nova_url" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            <span class="text-gray-400 font-normal mr-1">[{{ ticket.id }}]</span>{{ ticket.name }}
                        </a>
                    </td>
                    <td class="py-3 px-3 text-center">
                        <span :class="typeBadgeClass(ticket.type)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                            {{ ticket.type }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        <span :class="ticket.cycle_time_hours >= 80 ? 'text-red-500 font-semibold' : ''">{{ ticket.cycle_time_hours !== null ? ticket.cycle_time_hours + (ticket.cycle_time_hours >= 80 ? 'h+' : 'h') : '-' }}</span>
                    </td>
                    <td class="py-3 px-3 text-center">
                        <span :class="ticket.reopen_count > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400'">
                            {{ ticket.reopen_count }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-center" style="cursor:help" :title="ticket.on_time_detail || ''">
                        <span v-if="ticket.on_time === true" class="text-green-500 text-lg">&#10003;</span>
                        <span v-else-if="ticket.on_time === false" class="text-red-500 text-lg">&#10007;</span>
                        <span v-else class="text-gray-400">-</span>
                        <span v-if="ticket.on_time_diff_hours !== null && ticket.on_time !== null"
                              class="text-xs ml-1"
                              :class="ticket.on_time_diff_hours > 0 ? 'text-red-400' : 'text-green-400'">
                            {{ ticket.on_time_diff_hours > 0 ? '+' : '' }}{{ ticket.on_time_diff_hours }}h
                        </span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.commit_count !== null ? ticket.commit_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.pr_count !== null ? ticket.pr_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.change_requests_count !== null ? ticket.change_requests_count : '-' }}
                    </td>
                </tr>
            </tbody>

            <tfoot v-if="aggregate">
                <!-- Riga 1: Media team — valori puri, nessun diff -->
                <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 text-sm">
                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                        Media team ({{ aggregate.team_average.story_count }} ticket)
                    </td>
                    <td class="py-3 px-3 text-center text-gray-500">-</td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400" style="cursor:help" title="Media del cycle time (tempo attivo in progress) di tutti i developer nel quarter">
                        {{ aggregate.team_average.avg_cycle_time_hours !== null ? aggregate.team_average.avg_cycle_time_hours + 'h' : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400" style="cursor:help" title="Media del numero di riaperture per ticket di tutti i developer nel quarter">
                        {{ aggregate.team_average.avg_reopen_count !== null ? aggregate.team_average.avg_reopen_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400" style="cursor:help" title="% di ticket completati entro il benchmark (ore stimate o media cycle time team) sul totale dei ticket con cycle time misurabile">
                        {{ aggregate.team_average.on_time_rate !== null ? aggregate.team_average.on_time_rate + '%' : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_commit_count !== null ? aggregate.team_average.avg_commit_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_pr_count !== null ? aggregate.team_average.avg_pr_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_change_requests !== null ? aggregate.team_average.avg_change_requests : '-' }}
                    </td>
                </tr>

                <!-- Riga 2: Developer — valori con diff rispetto alla media team -->
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-900/20 font-semibold">
                    <td class="py-3 px-3 text-blue-700 dark:text-blue-300">
                        {{ developers.find(d => d.id == selectedDeveloperId) ? developers.find(d => d.id == selectedDeveloperId).name : (developers[0] && developers[0].name) }}
                        <span class="font-normal text-xs text-blue-500 ml-1">({{ aggregate.developer.story_count }} ticket)</span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-500">-</td>
                    <td class="py-3 px-3 text-center" :class="deltaClass(aggregate.developer.avg_cycle_time_hours, aggregate.team_average.avg_cycle_time_hours, true)" style="cursor:help" title="Media del cycle time del developer. Verde = più veloce della media team, rosso = più lento.">
                        {{ aggregate.developer.avg_cycle_time_hours !== null ? aggregate.developer.avg_cycle_time_hours + 'h' : '-' }}
                        <span v-if="aggregate.developer.avg_cycle_time_hours !== null && aggregate.team_average.avg_cycle_time_hours !== null" class="text-xs ml-1">{{ delta(aggregate.developer.avg_cycle_time_hours, aggregate.team_average.avg_cycle_time_hours) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center" :class="deltaClass(aggregate.developer.avg_reopen_count, aggregate.team_average.avg_reopen_count, true)" style="cursor:help" title="Media riaperture per ticket del developer. Verde = meno riaperture della media team, rosso = più riaperture.">
                        {{ aggregate.developer.avg_reopen_count !== null ? aggregate.developer.avg_reopen_count : '-' }}
                        <span v-if="aggregate.developer.avg_reopen_count !== null && aggregate.team_average.avg_reopen_count !== null" class="text-xs ml-1">{{ delta(aggregate.developer.avg_reopen_count, aggregate.team_average.avg_reopen_count) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center" :class="deltaClass(aggregate.developer.on_time_rate, aggregate.team_average.on_time_rate, false)" style="cursor:help" title="% ticket completati in tempo dal developer. Verde = sopra la media team, rosso = sotto. Il diff mostra la distanza dalla media.">
                        {{ aggregate.developer.on_time_rate !== null ? aggregate.developer.on_time_rate + '%' : '-' }}
                        <span v-if="aggregate.developer.on_time_rate !== null && aggregate.team_average.on_time_rate !== null" class="text-xs ml-1">{{ delta(aggregate.developer.on_time_rate, aggregate.team_average.on_time_rate) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_commit_count !== null ? aggregate.developer.avg_commit_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_pr_count !== null ? aggregate.developer.avg_pr_count : '-' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_change_requests !== null ? aggregate.developer.avg_change_requests : '-' }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
        `,

        props: ['card'],

        data: function() {
            var now = new Date();
            var currentQuarter = Math.ceil((now.getMonth() + 1) / 3);
            var currentYear = now.getFullYear();
            var years = [];
            for (var i = 0; i < 5; i++) { years.push(currentYear - i); }

            return {
                loading: false,
                developers: [],
                tickets: [],
                aggregate: null,
                selectedDeveloperId: null,
                selectedYear: currentYear,
                selectedQuarter: currentQuarter,
                availableYears: years,
            };
        },

        mounted: function() {
            this.loadData();
        },

        methods: {
            loadData: function() {
                var self = this;
                self.loading = true;

                var params = 'year=' + self.selectedYear + '&quarter=' + self.selectedQuarter;
                if (self.selectedDeveloperId) {
                    params += '&developer_id=' + self.selectedDeveloperId;
                }

                fetch('/nova-vendor/team-performance/data?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function(data) {
                    self.developers = data.developers || [];
                    self.tickets = data.tickets || [];
                    self.aggregate = data.aggregate || null;
                    if (!self.selectedDeveloperId && data.selected_developer_id) {
                        self.selectedDeveloperId = data.selected_developer_id;
                    }
                })
                .catch(function(e) {
                    console.error('TeamPerformance: errore caricamento dati', e);
                })
                .finally(function() {
                    self.loading = false;
                });
            },

            typeBadgeClass: function(type) {
                return type === 'Bug'
                    ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            },

            delta: function(devVal, teamVal) {
                if (devVal === null || devVal === undefined || teamVal === null || teamVal === undefined) return '';
                var diff = devVal - teamVal;
                if (Math.abs(diff) < 0.05) return '';
                return (diff > 0 ? '+' : '') + diff.toFixed(1);
            },

            deltaClass: function(devVal, teamVal, lowerIsBetter) {
                if (devVal === null || teamVal === null) return 'text-gray-600 dark:text-gray-400';
                var better = lowerIsBetter ? devVal < teamVal : devVal > teamVal;
                var worse  = lowerIsBetter ? devVal > teamVal : devVal < teamVal;
                if (better) return 'text-green-600 dark:text-green-400 font-semibold';
                if (worse)  return 'text-red-600 dark:text-red-400 font-semibold';
                return 'text-gray-600 dark:text-gray-400';
            },
        },
    });
});
