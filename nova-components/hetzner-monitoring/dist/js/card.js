/**
 * Hetzner Monitoring Card — Nova Component
 * Self-contained, no build step required.
 */
Nova.booting(function (app) {
    app.component('hetzner-monitoring-card', {
        template: `
<div class="hetzner-monitoring p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Hetzner Monitoring</h2>
            <p v-if="lastUpdated" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Aggiornato: {{ lastUpdated }}
            </p>
        </div>
        <div class="hetzner-monitoring__actions">
            <button
                @click="refresh"
                :disabled="loading"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
            >
                <svg v-if="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <svg v-else class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ loading ? 'Aggiornamento...' : 'Aggiorna' }}
            </button>
            <a
                href="/nova-vendor/hetzner-monitoring/export"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-primary-500 hover:bg-primary-600 text-white transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
            </a>
        </div>
    </div>

    <!-- Global summary -->
    <div v-if="!loading && projects.length > 0" class="grid grid-cols-3 gap-4 mb-6">
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Costo totale stimato</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">€{{ totalCost }}<span class="text-sm font-normal text-gray-500">/mese</span></p>
        </div>
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700">
            <p class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wider mb-1">Risparmio potenziale</p>
            <p class="text-2xl font-bold text-red-700 dark:text-red-300">€{{ totalSavings }}<span class="text-sm font-normal text-red-500">/mese</span></p>
            <p class="text-xs text-red-500 dark:text-red-400 mt-1">eliminando le risorse 🔴</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Risorse per priorità</p>
            <div class="hetzner-monitoring__priority-stats">
                <span class="hetzner-monitoring__priority-item hetzner-monitoring__priority-item--high">
                    <span class="hetzner-monitoring__priority-icon" aria-hidden="true">🔴</span>
                    <span class="hetzner-monitoring__priority-value">{{ totalHigh }}</span>
                </span>
                <span class="hetzner-monitoring__priority-item hetzner-monitoring__priority-item--medium">
                    <span class="hetzner-monitoring__priority-icon" aria-hidden="true">🟡</span>
                    <span class="hetzner-monitoring__priority-value">{{ totalMedium }}</span>
                </span>
                <span class="hetzner-monitoring__priority-item hetzner-monitoring__priority-item--ok">
                    <span class="hetzner-monitoring__priority-icon" aria-hidden="true">✅</span>
                    <span class="hetzner-monitoring__priority-value">{{ totalOk }}</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Global error -->
    <div v-if="globalError" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg text-red-700 dark:text-red-300">
        {{ globalError }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && projects.length === 0" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-32 bg-gray-100 dark:bg-gray-800 rounded-xl animate-pulse"></div>
    </div>

    <!-- Projects -->
    <div v-for="project in projects" :key="project.slug" class="mb-8">
        <!-- Project header -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <span class="text-lg font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
                    {{ project.slug }}
                </span>
                <span v-if="project.status === 'error'" class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300">
                    Errore API
                </span>
            </div>
            <div v-if="project.status === 'ok'" class="flex items-center gap-4 text-sm">
                <span v-if="project.potential_savings > 0" class="font-medium text-red-600 dark:text-red-400">
                    Risparmio potenziale: <strong>€{{ project.potential_savings }}/mese</strong>
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                    Costo stimato: <span class="font-bold text-gray-900 dark:text-white">€{{ project.monthly_cost_estimate }}/mese</span>
                </span>
            </div>
        </div>

        <!-- Project error -->
        <div v-if="project.status === 'error'" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg text-sm text-red-700 dark:text-red-300 mb-4">
            {{ project.error }}
        </div>

        <div v-else class="space-y-4">
            <!-- Servers -->
            <div v-if="project.servers && project.servers.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Server ({{ project.servers.length }})
                    <span v-if="highCount(project.servers) > 0" class="ml-2 text-xs font-medium text-red-600 dark:text-red-400">
                        🔴 {{ highCount(project.servers) }} critici
                    </span>
                    <span v-if="mediumCount(project.servers) > 0" class="ml-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                        🟡 {{ mediumCount(project.servers) }} da valutare
                    </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Azione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">€/mese stimato</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Base</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">CPU</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">RAM</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Disk</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datacenter</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IPv4</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Backup</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Età</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="s in project.servers" :key="s.id">
                            <tr :class="rowClass(s.action_priority)">
                                <td class="px-3 py-2">
                                    <button @click="toggleNote(project.slug, 'server', s.id)" :title="s.note ? 'Modifica nota' : 'Aggiungi nota'" class="text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg v-if="!s.note" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg v-else class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="actionBadgeClass(s.action_priority)" class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                                        {{ actionIcon(s.action_priority) }} {{ s.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ s.name }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="(s.backup_enabled || s.ipv4_assigned) ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-gray-100'">
                                    <span :title="priceBreakdown(s)">€{{ s.monthly_price }}</span>
                                    <span v-if="s.backup_enabled || s.ipv4_assigned" class="block text-xs font-normal text-gray-400 leading-tight">
                                        <span v-if="s.backup_enabled">+bkp</span>
                                        <span v-if="s.ipv4_assigned"> +ipv4</span>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right text-xs text-gray-400">€{{ s.monthly_price_base }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span :class="statusDot(s.status)" class="w-2 h-2 rounded-full inline-block"></span>
                                        {{ s.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.type }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.cores }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.memory_gb }} GB</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.disk_gb }} GB</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.datacenter }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ s.ipv4 }}</td>
                                <td class="px-3 py-2 text-center text-sm">
                                    <span v-if="s.backup_enabled" title="Backup automatici attivi (+20%)" aria-label="Backup attivo">✅</span>
                                    <span v-else title="Backup non attivo" aria-label="Backup non attivo">❌</span>
                                </td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ formatAge(s.age_days) }}</td>
                            </tr>
                            <tr v-if="s.note && activeNoteKey !== noteKey(project.slug,'server',s.id)" :class="rowClass(s.action_priority)">
                                <td :colspan="14" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-display">
                                        <p class="hetzner-monitoring__note-display-text">{{ s.note.text }}</p>
                                        <p class="hetzner-monitoring__note-display-meta"><span class="font-medium">{{ s.note.user_name }}</span>, {{ formatDate(s.note.updated_at) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activeNoteKey === noteKey(project.slug,'server',s.id)">
                                <td :colspan="14" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-editor">
                                        <div class="hetzner-monitoring__note-input-wrap">
                                            <textarea v-model="noteText" @input="clampNoteText" :maxlength="noteMaxLength" rows="3" placeholder="Aggiungi una nota su questa risorsa..." class="hetzner-monitoring__note-textarea text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                            <p class="hetzner-monitoring__note-counter" :class="{ 'hetzner-monitoring__note-counter--limit': noteText.length >= noteMaxLength }">{{ noteText.length }} / {{ noteMaxLength }}</p>
                                        </div>
                                        <div class="hetzner-monitoring__note-actions">
                                            <button @click="saveNote(project.slug,'server',s.id, s)" :disabled="noteSaving || !canSaveNote" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--save">Salva</button>
                                            <button v-if="s.note" @click="deleteNote(project.slug,'server',s.id, s)" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--delete">Elimina</button>
                                            <button @click="closeNote()" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--cancel">Annulla</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Floating IPs -->
            <div v-if="project.floating_ips && project.floating_ips.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Floating IP ({{ project.floating_ips.length }})
                    <span v-if="highCount(project.floating_ips) > 0" class="ml-2 text-xs font-medium text-red-600 dark:text-red-400">
                        🔴 {{ highCount(project.floating_ips) }} non assegnati
                    </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Azione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">€/mese</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrizione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Server</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="ip in project.floating_ips" :key="ip.id">
                            <tr :class="rowClass(ip.action_priority)">
                                <td class="px-3 py-2">
                                    <button @click="toggleNote(project.slug, 'floating_ip', ip.id)" class="text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg v-if="!ip.note" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg v-else class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="actionBadgeClass(ip.action_priority)" class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                                        {{ actionIcon(ip.action_priority) }} {{ ip.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-sm text-gray-900 dark:text-gray-100">{{ ip.ip }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">€{{ ip.monthly_price }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ ip.type }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ ip.description || '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ ip.server_id || '—' }}</td>
                            </tr>
                            <tr v-if="ip.note && activeNoteKey !== noteKey(project.slug,'floating_ip',ip.id)" :class="rowClass(ip.action_priority)">
                                <td :colspan="7" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-display">
                                        <p class="hetzner-monitoring__note-display-text">{{ ip.note.text }}</p>
                                        <p class="hetzner-monitoring__note-display-meta"><span class="font-medium">{{ ip.note.user_name }}</span>, {{ formatDate(ip.note.updated_at) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activeNoteKey === noteKey(project.slug,'floating_ip',ip.id)">
                                <td :colspan="7" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-editor">
                                        <div class="hetzner-monitoring__note-input-wrap">
                                            <textarea v-model="noteText" @input="clampNoteText" :maxlength="noteMaxLength" rows="3" placeholder="Aggiungi una nota..." class="hetzner-monitoring__note-textarea text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                            <p class="hetzner-monitoring__note-counter" :class="{ 'hetzner-monitoring__note-counter--limit': noteText.length >= noteMaxLength }">{{ noteText.length }} / {{ noteMaxLength }}</p>
                                        </div>
                                        <div class="hetzner-monitoring__note-actions">
                                            <button @click="saveNote(project.slug,'floating_ip',ip.id,ip)" :disabled="noteSaving || !canSaveNote" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--save">Salva</button>
                                            <button v-if="ip.note" @click="deleteNote(project.slug,'floating_ip',ip.id,ip)" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--delete">Elimina</button>
                                            <button @click="closeNote()" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--cancel">Annulla</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Volumes -->
            <div v-if="project.volumes && project.volumes.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Volumes ({{ project.volumes.length }})
                    <span v-if="highCount(project.volumes) > 0" class="ml-2 text-xs font-medium text-red-600 dark:text-red-400">
                        🔴 {{ highCount(project.volumes) }} non montati
                    </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Azione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">€/mese</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Montato su</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Età</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="v in project.volumes" :key="v.id">
                            <tr :class="rowClass(v.action_priority)">
                                <td class="px-3 py-2">
                                    <button @click="toggleNote(project.slug, 'volume', v.id)" class="text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg v-if="!v.note" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg v-else class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="actionBadgeClass(v.action_priority)" class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                                        {{ actionIcon(v.action_priority) }} {{ v.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ v.name }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">€{{ v.monthly_price }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ v.size_gb }} GB</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ v.status }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ v.server_id || '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ v.location }}</td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ formatAge(v.age_days) }}</td>
                            </tr>
                            <tr v-if="v.note && activeNoteKey !== noteKey(project.slug,'volume',v.id)" :class="rowClass(v.action_priority)">
                                <td :colspan="9" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-display">
                                        <p class="hetzner-monitoring__note-display-text">{{ v.note.text }}</p>
                                        <p class="hetzner-monitoring__note-display-meta"><span class="font-medium">{{ v.note.user_name }}</span>, {{ formatDate(v.note.updated_at) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activeNoteKey === noteKey(project.slug,'volume',v.id)">
                                <td :colspan="9" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-editor">
                                        <div class="hetzner-monitoring__note-input-wrap">
                                            <textarea v-model="noteText" @input="clampNoteText" :maxlength="noteMaxLength" rows="3" placeholder="Aggiungi una nota..." class="hetzner-monitoring__note-textarea text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                            <p class="hetzner-monitoring__note-counter" :class="{ 'hetzner-monitoring__note-counter--limit': noteText.length >= noteMaxLength }">{{ noteText.length }} / {{ noteMaxLength }}</p>
                                        </div>
                                        <div class="hetzner-monitoring__note-actions">
                                            <button @click="saveNote(project.slug,'volume',v.id,v)" :disabled="noteSaving || !canSaveNote" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--save">Salva</button>
                                            <button v-if="v.note" @click="deleteNote(project.slug,'volume',v.id,v)" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--delete">Elimina</button>
                                            <button @click="closeNote()" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--cancel">Annulla</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Load Balancers -->
            <div v-if="project.load_balancers && project.load_balancers.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Load Balancers ({{ project.load_balancers.length }})
                    <span v-if="highCount(project.load_balancers) > 0" class="ml-2 text-xs font-medium text-red-600 dark:text-red-400">
                        🔴 {{ highCount(project.load_balancers) }} senza target
                    </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Azione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">€/mese</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Targets</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="lb in project.load_balancers" :key="lb.id">
                            <tr :class="rowClass(lb.action_priority)">
                                <td class="px-3 py-2">
                                    <button @click="toggleNote(project.slug, 'load_balancer', lb.id)" class="text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg v-if="!lb.note" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg v-else class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="actionBadgeClass(lb.action_priority)" class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                                        {{ actionIcon(lb.action_priority) }} {{ lb.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ lb.name }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">€{{ lb.monthly_price }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ lb.type }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ lb.targets_count }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ lb.location }}</td>
                            </tr>
                            <tr v-if="lb.note && activeNoteKey !== noteKey(project.slug,'load_balancer',lb.id)" :class="rowClass(lb.action_priority)">
                                <td :colspan="7" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-display">
                                        <p class="hetzner-monitoring__note-display-text">{{ lb.note.text }}</p>
                                        <p class="hetzner-monitoring__note-display-meta"><span class="font-medium">{{ lb.note.user_name }}</span>, {{ formatDate(lb.note.updated_at) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activeNoteKey === noteKey(project.slug,'load_balancer',lb.id)">
                                <td :colspan="7" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-editor">
                                        <div class="hetzner-monitoring__note-input-wrap">
                                            <textarea v-model="noteText" @input="clampNoteText" :maxlength="noteMaxLength" rows="3" placeholder="Aggiungi una nota..." class="hetzner-monitoring__note-textarea text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                            <p class="hetzner-monitoring__note-counter" :class="{ 'hetzner-monitoring__note-counter--limit': noteText.length >= noteMaxLength }">{{ noteText.length }} / {{ noteMaxLength }}</p>
                                        </div>
                                        <div class="hetzner-monitoring__note-actions">
                                            <button @click="saveNote(project.slug,'load_balancer',lb.id,lb)" :disabled="noteSaving || !canSaveNote" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--save">Salva</button>
                                            <button v-if="lb.note" @click="deleteNote(project.slug,'load_balancer',lb.id,lb)" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--delete">Elimina</button>
                                            <button @click="closeNote()" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--cancel">Annulla</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Snapshots -->
            <div v-if="project.snapshots && project.snapshots.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Snapshots ({{ project.snapshots.length }})
                    <span v-if="mediumCount(project.snapshots) > 0" class="ml-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                        🟡 {{ mediumCount(project.snapshots) }} vecchi (>6 mesi)
                    </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Azione</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">€/mese</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Creato</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Età</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="snap in project.snapshots" :key="snap.id">
                            <tr :class="rowClass(snap.action_priority)">
                                <td class="px-3 py-2">
                                    <button @click="toggleNote(project.slug, 'snapshot', snap.id)" class="text-gray-400 hover:text-blue-500 transition-colors">
                                        <svg v-if="!snap.note" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg v-else class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="actionBadgeClass(snap.action_priority)" class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap">
                                        {{ actionIcon(snap.action_priority) }} {{ snap.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ snap.name || '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">€{{ snap.monthly_price }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ snap.size_gb }} GB</td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ formatDate(snap.created_at) }}</td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ formatAge(snap.age_days) }}</td>
                            </tr>
                            <tr v-if="snap.note && activeNoteKey !== noteKey(project.slug,'snapshot',snap.id)" :class="rowClass(snap.action_priority)">
                                <td :colspan="7" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-display">
                                        <p class="hetzner-monitoring__note-display-text">{{ snap.note.text }}</p>
                                        <p class="hetzner-monitoring__note-display-meta"><span class="font-medium">{{ snap.note.user_name }}</span>, {{ formatDate(snap.note.updated_at) }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activeNoteKey === noteKey(project.slug,'snapshot',snap.id)">
                                <td :colspan="7" class="px-4 py-3 bg-gray-50 dark:bg-gray-800 hetzner-monitoring__note-cell">
                                    <div class="hetzner-monitoring__note-editor">
                                        <div class="hetzner-monitoring__note-input-wrap">
                                            <textarea v-model="noteText" @input="clampNoteText" :maxlength="noteMaxLength" rows="3" placeholder="Aggiungi una nota..." class="hetzner-monitoring__note-textarea text-sm border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                            <p class="hetzner-monitoring__note-counter" :class="{ 'hetzner-monitoring__note-counter--limit': noteText.length >= noteMaxLength }">{{ noteText.length }} / {{ noteMaxLength }}</p>
                                        </div>
                                        <div class="hetzner-monitoring__note-actions">
                                            <button @click="saveNote(project.slug,'snapshot',snap.id,snap)" :disabled="noteSaving || !canSaveNote" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--save">Salva</button>
                                            <button v-if="snap.note" @click="deleteNote(project.slug,'snapshot',snap.id,snap)" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--delete">Elimina</button>
                                            <button @click="closeNote()" class="hetzner-monitoring__note-btn hetzner-monitoring__note-btn--cancel">Annulla</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty project -->
            <div v-if="isEmptyProject(project)" class="p-4 text-sm text-gray-500 dark:text-gray-400 text-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                Nessuna risorsa trovata in questo progetto.
            </div>
        </div>

        <hr class="mt-6 border-gray-200 dark:border-gray-700" />
    </div>

    <!-- Empty state -->
    <div v-if="!loading && projects.length === 0 && !globalError" class="text-center py-12 text-gray-500 dark:text-gray-400">
        <svg class="mx-auto w-12 h-12 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
        </svg>
        <p>Nessun progetto Hetzner configurato.</p>
        <p class="text-xs mt-1">Aggiungere le variabili d'ambiente <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">HETZNER_TOKEN_&lt;SLUG&gt;</code></p>
    </div>
</div>
        `,

        data() {
            return {
                projects: [],
                loading: false,
                globalError: null,
                lastUpdated: null,
                activeNoteKey: null,
                noteText: '',
                noteMaxLength: 500,
                noteSaving: false,
            };
        },

        computed: {
            totalCost() {
                return this.projects
                    .filter(p => p.status === 'ok')
                    .reduce((sum, p) => sum + (p.monthly_cost_estimate || 0), 0)
                    .toFixed(2);
            },
            totalSavings() {
                return this.projects
                    .filter(p => p.status === 'ok')
                    .reduce((sum, p) => sum + (p.potential_savings || 0), 0)
                    .toFixed(2);
            },
            allResources() {
                return this.projects.filter(p => p.status === 'ok').flatMap(p => [
                    ...(p.servers || []),
                    ...(p.floating_ips || []),
                    ...(p.volumes || []),
                    ...(p.load_balancers || []),
                    ...(p.snapshots || []),
                ]);
            },
            totalHigh() {
                return this.allResources.filter(r => r.action_priority === 'high').length;
            },
            totalMedium() {
                return this.allResources.filter(r => r.action_priority === 'medium').length;
            },
            totalOk() {
                return this.allResources.filter(r => r.action_priority === 'ok').length;
            },
            canSaveNote() {
                const len = this.noteText.trim().length;
                return len > 0 && len <= this.noteMaxLength;
            },
        },

        mounted() {
            this.fetchData();
        },

        methods: {
            async fetchData() {
                this.loading = true;
                this.globalError = null;
                try {
                    const response = await fetch('/nova-vendor/hetzner-monitoring/data', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    this.projects = await response.json();
                    this.lastUpdated = new Date().toLocaleString('it-IT');
                } catch (e) {
                    this.globalError = 'Impossibile caricare i dati: ' + e.message;
                } finally {
                    this.loading = false;
                }
            },

            async refresh() {
                this.loading = true;
                this.globalError = null;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch('/nova-vendor/hetzner-monitoring/refresh', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    this.projects = await response.json();
                    this.lastUpdated = new Date().toLocaleString('it-IT');
                } catch (e) {
                    this.globalError = 'Errore durante il refresh: ' + e.message;
                } finally {
                    this.loading = false;
                }
            },

            highCount(resources) {
                return (resources || []).filter(r => r.action_priority === 'high').length;
            },

            mediumCount(resources) {
                return (resources || []).filter(r => r.action_priority === 'medium').length;
            },

            rowClass(priority) {
                const map = {
                    high:   'bg-red-50 dark:bg-red-900/10',
                    medium: 'bg-amber-50 dark:bg-amber-900/10',
                    ok:     'bg-white dark:bg-gray-900',
                };
                return map[priority] || 'bg-white dark:bg-gray-900';
            },

            actionBadgeClass(priority) {
                const map = {
                    high:   'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
                    medium: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
                    ok:     'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
                };
                return map[priority] || '';
            },

            actionIcon(priority) {
                const map = { high: '🔴', medium: '🟡', ok: '✅' };
                return map[priority] || '';
            },

            priceBreakdown(server) {
                let lines = [`Base: €${server.monthly_price_base}`];
                if (server.backup_enabled) {
                    const bkp = (server.monthly_price_base * 0.20).toFixed(2);
                    lines.push(`Backup +20%: +€${bkp}`);
                }
                if (server.ipv4_assigned) {
                    lines.push('Primary IPv4: +€0.50');
                }
                lines.push(`Totale stimato: €${server.monthly_price}`);
                return lines.join('\n');
            },

            statusDot(status) {
                const map = {
                    running:     'bg-green-500',
                    off:         'bg-gray-400',
                    initializing: 'bg-yellow-400',
                    starting:    'bg-yellow-400',
                    stopping:    'bg-yellow-400',
                    rebuilding:  'bg-yellow-400',
                    migrating:   'bg-yellow-400',
                    deleting:    'bg-red-400',
                };
                return map[status] || 'bg-gray-400';
            },

            isEmptyProject(project) {
                return (
                    (!project.servers || project.servers.length === 0) &&
                    (!project.floating_ips || project.floating_ips.length === 0) &&
                    (!project.volumes || project.volumes.length === 0) &&
                    (!project.load_balancers || project.load_balancers.length === 0) &&
                    (!project.snapshots || project.snapshots.length === 0)
                );
            },

            formatAge(days) {
                if (days === null || days === undefined) return '—';

                const d = Math.abs(days);

                const dayStr = (n) => (n === 1 ? '1 giorno' : `${n} giorni`);
                const monthStr = (n) => (n === 1 ? '1 mese' : `${n} mesi`);
                const yearStr = (n) => (n === 1 ? '1 anno' : `${n} anni`);

                if (d < 30) return dayStr(d);

                if (d < 365) {
                    const months = Math.floor(d / 30);
                    const rem = d % 30;
                    return rem === 0 ? monthStr(months) : `${monthStr(months)} e ${dayStr(rem)}`;
                }

                const years = Math.floor(d / 365);
                const rem = d % 365;
                return rem === 0 ? yearStr(years) : `${yearStr(years)} e ${dayStr(rem)}`;
            },

            noteKey(projectSlug, type, id) {
                return `${projectSlug}::${type}::${id}`;
            },

            toggleNote(projectSlug, type, id) {
                const key = this.noteKey(projectSlug, type, id);
                if (this.activeNoteKey === key) {
                    this.closeNote();
                    return;
                }
                const resource = this.findResource(projectSlug, type, id);
                this.noteText = (resource?.note?.text || '').slice(0, this.noteMaxLength);
                this.activeNoteKey = key;
            },

            closeNote() {
                this.activeNoteKey = null;
                this.noteText = '';
            },

            clampNoteText() {
                if (this.noteText.length > this.noteMaxLength) {
                    this.noteText = this.noteText.slice(0, this.noteMaxLength);
                }
            },

            findResource(projectSlug, type, id) {
                const project = this.projects.find(p => p.slug === projectSlug);
                if (!project) return null;
                const listKey = { server: 'servers', floating_ip: 'floating_ips', volume: 'volumes', load_balancer: 'load_balancers', snapshot: 'snapshots' }[type];
                return (project[listKey] || []).find(r => r.id === id) || null;
            },

            async saveNote(projectSlug, type, id, resource) {
                const text = this.noteText.trim();
                if (!text || text.length > this.noteMaxLength) return;
                this.noteSaving = true;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const resp = await fetch('/nova-vendor/hetzner-monitoring/note', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ project_slug: projectSlug, resource_type: type, resource_id: id, text }),
                    });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    resource.note = data.note;
                    this.closeNote();
                } catch (e) {
                    alert('Errore nel salvataggio della nota: ' + e.message);
                } finally {
                    this.noteSaving = false;
                }
            },

            async deleteNote(projectSlug, type, id, resource) {
                this.noteSaving = true;
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const resp = await fetch('/nova-vendor/hetzner-monitoring/note', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ project_slug: projectSlug, resource_type: type, resource_id: id }),
                    });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    resource.note = null;
                    this.closeNote();
                } catch (e) {
                    alert('Errore nella cancellazione della nota: ' + e.message);
                } finally {
                    this.noteSaving = false;
                }
            },

            formatDate(dateStr) {
                if (!dateStr) return '—';
                try {
                    return new Date(dateStr).toLocaleDateString('it-IT', {
                        year: 'numeric', month: 'short', day: 'numeric',
                    });
                } catch {
                    return dateStr;
                }
            },
        },
    });
});
