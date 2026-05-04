/**
 * Kanban Card — Nova 4 Card Component
 * Self-contained, no build step required.
 * Uses Vue 3 (provided by Nova) + native HTML5 Drag & Drop.
 */
Nova.booting((app) => {
    app.component('kanban-card', {
        props: ['card'],

        template: `
            <div class="kanban-board-wrapper">
                <!-- Collapsible header (only when collapsible is true) -->
                <div v-if="collapsible" class="kanban-collapse-header" @click="toggleCollapsed" :title="isCollapsed ? translations.expand : translations.collapse">
                    <span class="kanban-collapse-icon" :class="{ 'kanban-collapse-icon-open': !isCollapsed }">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4V4z"/></svg>
                    </span>
                    <span class="kanban-collapse-label">{{ translations.collapseLabel }}</span>
                </div>
                <!-- Body: toolbar + board (hidden when collapsed if collapsible) -->
                <div v-show="!collapsible || !isCollapsed" class="kanban-collapse-body">
                <!-- Toolbar: single search + filter field (combobox) -->
                <div v-if="(filterField && filterOptions.length) || searchFields.length" class="kanban-toolbar-container">
                    <div v-if="toolbarTitle" class="kanban-toolbar-title">{{ toolbarTitle }}</div>
                <div class="kanban-toolbar">
                    <div v-if="toolbarLabel" class="kanban-toolbar-label">{{ toolbarLabel }}</div>
                    <div ref="comboboxWrap" class="kanban-combobox-wrap" :class="{ 'kanban-combobox-has-filter': filterField && filterOptions.length, 'kanban-combobox-has-arrow': selectOnly }">
                        <input
                            type="text"
                            class="kanban-combobox-input"
                            :placeholder="comboboxPlaceholder"
                            :value="comboboxInput"
                            :readonly="selectOnly"
                            @input="onComboboxInput"
                            @focus="comboboxOpen = true"
                            @click="comboboxOpen = true"
                            @keydown.down.prevent="comboboxFocusNext"
                            @keydown.up.prevent="comboboxFocusPrev"
                            @keydown.enter.prevent="comboboxConfirmHighlighted"
                            @keydown.escape="comboboxOpen = false"
                        >
                        <button v-if="comboboxInput && !selectOnly" type="button" class="kanban-combobox-clear" @click="clearCombobox" aria-label="Clear">×</button>
                        <div v-show="comboboxOpen && filterField && filterOptions.length" class="kanban-combobox-dropdown" ref="comboboxDropdown">
                            <div v-if="showFilterAll" class="kanban-combobox-option" :class="{ 'kanban-combobox-option-highlight': comboboxHighlightIndex === -1 }" @mousedown.prevent="selectFilterOption(null)">
                                {{ translations.filterAll }}
                            </div>
                            <div
                                v-for="(opt, idx) in filteredFilterOptions"
                                :key="opt.value"
                                class="kanban-combobox-option"
                                :class="{ 'kanban-combobox-option-highlight': comboboxHighlightIndex === idx }"
                                @mousedown.prevent="selectFilterOption(opt)"
                            >
                                {{ opt.label }}
                            </div>
                            <div v-if="filteredFilterOptions.length === 0 && comboboxInput" class="kanban-combobox-empty">
                                {{ translations.noFilterMatch }}
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <!-- Loading -->
                <div v-if="loading" class="kanban-loading">
                    <svg class="kanban-spinner" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31" stroke-linecap="round"/>
                    </svg>
                    <span>{{ translations.loading }}</span>
                </div>

                <!-- Board (ref for auto-scroll during drag) -->
                <div v-else ref="kanbanBoard" class="kanban-board" @dragover.prevent="onBoardDragOver($event)">
                    <div
                        v-for="column in columns"
                        :key="column.value"
                        class="kanban-column"
                        :class="{
                            'kanban-column-compact-empty': isColumnCollapsed(column.value),
                            'kanban-column-drop-target': dragOverColumn === column.value && !!draggedItem
                        }"
                        :style="{ borderColor: column.color }"
                        @dragover.prevent="canUpdate && onDragOver($event, column.value)"
                        @dragleave="onDragLeave($event)"
                        @drop="canUpdate && onDrop($event, column.value)"
                    >
                        <!-- Column Header -->
                        <div class="kanban-column-header kanban-column-header-clickable" @click.stop="toggleColumnCollapsed(column.value)">
                            <div class="kanban-column-title-wrap">
                                <span class="kanban-column-title">{{ column.label }}</span>
                                <span v-if="getHeaderSum(column.value) !== null" class="kanban-column-sum">
                                    {{ formatCurrency(getHeaderSum(column.value)) }}
                                </span>
                            </div>
                            <span class="kanban-column-count" :style="{ backgroundColor: column.color }">
                                {{ getHeaderCount(column.value) }}
                            </span>
                        </div>

                        <!-- Items -->
                        <div class="kanban-column-body" :class="{ 'kanban-column-dragover': dragOverColumn === column.value }" @dragover.prevent="onColumnBodyDragOver($event, column.value)">
                            <template v-if="!isColumnCollapsed(column.value)">
                                <template v-for="item in getColumnItems(column.value)" :key="item.id">
                                    <div
                                        v-if="showDropIndicatorBefore(column.value, item.id)"
                                        class="kanban-drop-indicator"
                                        :style="{ backgroundColor: column.color }"
                                    ></div>
                                    <div
                                        class="kanban-item"
                                        :class="{ 'kanban-item-updating': updatingIds.includes(item.id), 'kanban-item-readonly': !canUpdate }"
                                        :draggable="canUpdate"
                                        @dragstart="onDragStart($event, item)"
                                        @dragover.prevent="onItemDragOver($event, item, column.value)"
                                        @drop.stop="onItemDrop($event, item, column.value)"
                                        @dragend="onDragEnd"
                                        @click="openDetail(item)"
                                    >
                                        <div class="kanban-item-title">{{ item.title }}</div>
                                        <div v-if="item.subtitle" class="kanban-item-subtitle">
                                            {{ item.subtitle }}
                                        </div>
                                        <div v-if="item.fields && item.fields.length" class="kanban-item-fields">
                                            <div
                                                v-for="field in item.fields"
                                                :key="field.label"
                                                class="kanban-item-field"
                                            >
                                                <span class="kanban-item-field-label">{{ field.label }}:</span>
                                                <span
                                                    class="kanban-item-field-value"
                                                    :class="fieldValueClass(field)"
                                                    :style="fieldValueStyle(field)"
                                                >{{ getFieldDisplayValue(item, field) }}</span>
                                                <button
                                                    v-if="canToggleDescription(item, field)"
                                                    type="button"
                                                    class="kanban-item-field-toggle"
                                                    @click.stop="toggleDescription(item, field)"
                                                >
                                                    {{ isDescriptionExpanded(item, field) ? translations.showLess : translations.showMore }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="showDropIndicatorAfter(column.value, item.id)"
                                        class="kanban-drop-indicator"
                                        :style="{ backgroundColor: column.color }"
                                    ></div>
                                </template>

                                <div
                                    v-if="showDropIndicatorEnd(column.value)"
                                    class="kanban-drop-indicator kanban-drop-indicator-end"
                                    :style="{ backgroundColor: column.color }"
                                ></div>
                                <div v-if="getColumnItems(column.value).length === 0 && dragOverColumn !== column.value" class="kanban-column-empty">
                                    {{ translations.noItems }}
                                </div>
                                <!-- Load more (when column has reached initial limit and there may be more) -->
                                <div v-if="hasMoreByStatus[column.value]" class="kanban-column-load-more">
                                    <button type="button" class="kanban-load-more-btn" :disabled="loadingMoreStatus === column.value" @click.stop="fetchMore(column.value)">
                                        <span v-if="loadingMoreStatus === column.value" class="kanban-load-more-spinner"></span>
                                        <span v-else>{{ translations.loadMore }}</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Toast -->
                <div v-if="errorMessage" class="kanban-toast kanban-toast-error">
                    {{ errorMessage }}
                </div>
                <div v-if="successMessage" class="kanban-toast kanban-toast-success">
                    {{ successMessage }}
                </div>
                </div>
            </div>
        `,

        /** Reactive data: loading state, items list, filter/search/combobox state, drag state, toasts, collapse. */
        data() {
            var storageKey = 'kanban_collapsed_' + (this.card.resourceUri || 'default') + '_' + (this.card.columns || []).length;
            var saved = false;
            try {
                var raw = localStorage.getItem(storageKey);
                if (raw !== null) saved = raw === '1';
            } catch (e) {}
            var initialFilter = this.card.initialFilterValue;
            var initialFilterStr = (initialFilter !== undefined && initialFilter !== null && initialFilter !== '') ? String(initialFilter) : '';
            return {
                loading: true,
                items: [],
                filterValue: initialFilterStr,
                filterFieldSelected: null,
                searchValue: '',
                searchDebounce: null,
                updatingIds: [],
                draggedItem: null,
                dragOverColumn: null,
                dropIndicator: null,
                errorMessage: null,
                successMessage: null,
                isCollapsed: saved,
                collapseStorageKey: storageKey,
                hasMoreByStatus: {},
                totalCountByStatus: {},
                loadingMoreStatus: null,
                dragScrollRAF: null,
                dragScrollDirection: 0,
                dragVScrollRAF: null,
                dragVScrollDirection: 0,
                dragVScrollEl: null,
                comboboxInput: '',
                comboboxOpen: false,
                comboboxHighlightIndex: -1,
                expandedDescriptionMap: {},
                columnCollapsedState: {},
            };
        },

        /** Computed: card config (columns, apiConfig, filter options, translations), combobox placeholder and filtered options. */
        computed: {
            cardData() {
                return this.card || {};
            },
            columns() {
                return this.cardData.columns || [];
            },
            apiConfig() {
                return this.cardData.apiConfig || {};
            },
            configParam() {
                return encodeURIComponent(JSON.stringify(this.apiConfig));
            },
            resourceUri() {
                return this.cardData.resourceUri || null;
            },
            filterField() {
                return this.cardData.filterField || null;
            },
            filterOptions() {
                return this.cardData.filterOptions || [];
            },
            searchFields() {
                return this.cardData.searchFields || [];
            },
            collapsible() {
                return this.cardData.collapsible === true;
            },
            canUpdate() {
                return this.cardData.canUpdate !== false;
            },
            canReorder() {
                return this.canUpdate === true
                    && this.apiConfig.enableIntraColumnReorder === true
                    && !!this.apiConfig.priorityField;
            },
            showFilterAll() {
                return this.cardData.showFilterAll !== false;
            },
            toolbarTitle() {
                return this.cardData.toolbarTitle || '';
            },
            toolbarLabel() {
                return this.cardData.toolbarLabel || '';
            },
            selectOnly() {
                return this.cardData.selectOnly === true;
            },
            statusColumnLimits() {
                return this.apiConfig.statusColumnLimits || {};
            },
            limitPerColumn() {
                var n = parseInt(this.cardData.limitPerColumn, 10);
                return isNaN(n) || n < 1 ? 50 : Math.min(500, n);
            },
            comboboxPlaceholder() {
                if (this.filterField && this.filterOptions.length && this.searchFields.length) {
                    return this.translations.searchOrFilterPlaceholder;
                }
                if (this.filterField && this.filterOptions.length) return this.translations.filterPlaceholder;
                return this.translations.searchPlaceholder;
            },
            filteredFilterOptions() {
                if (!this.filterOptions.length) return this.filterOptions;
                if (this.selectOnly) return this.filterOptions;
                // UX: when an option is already selected, first click should still show full list.
                // Avoid forcing user to clear input before selecting another user.
                if (
                    this.comboboxOpen &&
                    this.filterValue &&
                    this.selectedFilterOption &&
                    this.comboboxInput === this.selectedFilterOption.label
                ) {
                    return this.filterOptions;
                }
                if (!this.comboboxInput) return this.filterOptions;
                var q = this.comboboxInput.toLowerCase().trim();
                return this.filterOptions.filter(function (o) {
                    return (o.label || '').toLowerCase().indexOf(q) !== -1;
                });
            },
            selectedFilterOption() {
                if (!this.filterValue || !this.filterOptions.length) return null;
                return this.filterOptions.find((o) => o.value === this.filterValue) || null;
            },
            translations() {
                var t = this.cardData.translations || {};
                return {
                    loading: t.loading || 'Loading...',
                    noItems: t.noItems || 'No items',
                    errorLoading: t.errorLoading || 'Error loading data.',
                    errorUpdating: t.errorUpdating || 'Error updating status.',
                    statusUpdated: t.statusUpdated || 'Status updated.',
                    filterLabel: t.filterLabel || 'Filter',
                    filterAll: t.filterAll || 'All',
                    searchPlaceholder: t.searchPlaceholder || 'Search...',
                    expand: t.expand || 'Expand',
                    collapse: t.collapse || 'Collapse',
                    collapseLabel: t.collapseLabel || 'Board',
                    loadMore: t.loadMore || 'Load 10 more',
                    searchOrFilterPlaceholder: t.searchOrFilterPlaceholder || 'Search or select',
                    filterPlaceholder: t.filterPlaceholder || 'Select...',
                    noFilterMatch: t.noFilterMatch || 'No results',
                    showMore: t.showMore || 'Show more',
                    showLess: t.showLess || 'Show less',
                };
            },
        },

        /** On mount: restore initial filter label in combobox, set filterFieldSelected when options have filterField, fetch items, bind click-outside for dropdown. */
        mounted() {
            var self = this;
            var savedFilterValue = self.getSavedFilterValue();
            if (savedFilterValue) {
                self.filterValue = savedFilterValue;
            }
            if (self.filterValue && self.filterOptions.length) {
                var opt = self.filterOptions.find(function (o) { return o.value === self.filterValue; });
                if (opt) {
                    self.comboboxInput = opt.label;
                    self.filterFieldSelected = opt.filterField || self.filterField;
                } else if (savedFilterValue) {
                    // Cookie points to an option no longer available: clear stale value.
                    self.filterValue = '';
                    self.clearSavedFilterValue();
                }
            } else if (self.filterOptions.length && self.filterOptions[0].filterField) {
                self.filterFieldSelected = self.filterField;
            }
            var initialColumnState = {};
            (self.columns || []).forEach(function (col) {
                if (col && col.value && col.collapse === true) {
                    initialColumnState[col.value] = true;
                }
            });
            self.columnCollapsedState = initialColumnState;
            self.fetchItems();
            document.addEventListener('click', self.onComboboxClickOutside);
        },

        /** On unmount: remove click-outside listener. */
        beforeUnmount() {
            document.removeEventListener('click', this.onComboboxClickOutside);
        },

        methods: {
            /**
             * Returns the list of items that belong to the given column (status).
             * @param {string} status - Column status value (e.g. 'todo', 'progress').
             * @returns {Array} Items for that column.
             */
            getColumnItems(status) {
                var list = this.items.filter(function (item) {
                    return item.status === status;
                });
                var limit = parseInt(this.statusColumnLimits[status], 10);
                if (!isNaN(limit) && limit > 0) {
                    list.sort(function (a, b) { return Number(b.id) - Number(a.id); });
                    return list.slice(0, limit);
                }
                return list;
            },

            /** True when a column has zero items (uses total count if available). */
            isColumnEmpty(status) {
                var localCount = this.getColumnItems(status).length;
                if (localCount > 0) return false;
                if (this.totalCountByStatus && this.totalCountByStatus[status] !== undefined) {
                    var v = this.totalCountByStatus[status];
                    if (v && typeof v === 'object' && v.count !== undefined) return Number(v.count) === 0;
                    return Number(v) === 0;
                }
                return true;
            },

            /** Drop indicator helpers (intra/cross column priority positioning). */
            showDropIndicatorBefore(status, targetId) {
                if (!this.dropIndicator) return false;
                return this.dropIndicator.status === status
                    && this.dropIndicator.targetId !== null
                    && String(this.dropIndicator.targetId) === String(targetId)
                    && this.dropIndicator.before === true;
            },

            showDropIndicatorAfter(status, targetId) {
                if (!this.dropIndicator) return false;
                return this.dropIndicator.status === status
                    && this.dropIndicator.targetId !== null
                    && String(this.dropIndicator.targetId) === String(targetId)
                    && this.dropIndicator.before === false;
            },

            showDropIndicatorEnd(status) {
                if (!this.dropIndicator) return false;
                return this.dropIndicator.status === status && this.dropIndicator.targetId === null;
            },

            /** Resolved collapsed state: explicit column flag (toggle) or automatic for empty columns. */
            isColumnCollapsed(status) {
                if (Object.prototype.hasOwnProperty.call(this.columnCollapsedState, status)) {
                    return this.columnCollapsedState[status] === true;
                }
                return this.isColumnEmpty(status);
            },

            /** Toggle single column collapsed state on header click. */
            toggleColumnCollapsed(status) {
                this.columnCollapsedState = Object.assign({}, this.columnCollapsedState, {
                    [status]: !this.isColumnCollapsed(status),
                });
                var self = this;
                this.$nextTick(function () {
                    self.updateBoardFit();
                });
            },

            /** Toggle centering only when all columns fit without horizontal scroll. */
            updateBoardFit() {
                var board = this.$refs.kanbanBoard;
                if (!board) return;
                var fits = board.scrollWidth <= board.clientWidth + 1;
                if (fits) {
                    board.classList.add('kanban-board-fit');
                } else {
                    board.classList.remove('kanban-board-fit');
                }
            },

            /**
             * Handles combobox text input. Debounces then applies filter (if text matches an option)
             * or search, then refetches items.
             */
            onComboboxInput(event) {
                var self = this;
                if (self.selectOnly) return;
                self.comboboxInput = event.target.value;
                self.comboboxHighlightIndex = -1;
                if (self.searchDebounce) clearTimeout(self.searchDebounce);
                self.searchDebounce = setTimeout(function () {
                    var text = (self.comboboxInput || '').trim();
                    if (self.filterField && self.filterOptions.length && text) {
                        var exact = self.filterOptions.find(function (o) {
                            return (o.label || '').trim().toLowerCase() === text.toLowerCase();
                        });
                        if (exact) {
                            self.filterValue = exact.value;
                            self.filterFieldSelected = exact.filterField || self.filterField;
                            self.searchValue = '';
                        } else {
                            self.filterValue = '';
                            self.searchValue = self.comboboxInput;
                        }
                    } else if (!text) {
                        self.filterValue = '';
                        self.filterFieldSelected = null;
                        self.searchValue = '';
                    } else {
                        self.filterValue = '';
                        self.filterFieldSelected = null;
                        self.searchValue = self.comboboxInput;
                    }
                    self.fetchItems();
                }, 400);
            },

            /**
             * Closes the combobox dropdown when the user clicks outside it.
             */
            onComboboxClickOutside(event) {
                if (this.$refs.comboboxWrap && !this.$refs.comboboxWrap.contains(event.target)) {
                    this.comboboxOpen = false;
                }
            },

            /**
             * Clears filter, search and combobox input, then refetches all items.
             */
            clearCombobox() {
                if (this.selectOnly) return;
                this.comboboxInput = '';
                this.filterValue = '';
                this.filterFieldSelected = null;
                this.searchValue = '';
                this.comboboxOpen = false;
                this.comboboxHighlightIndex = -1;
                this.clearSavedFilterValue();
                this.fetchItems();
            },

            /**
             * Applies the selected filter option (or "All" when opt is null), closes dropdown, refetches.
             * @param {{ value: string, label: string }|null} opt - Selected option or null for "All".
             */
            selectFilterOption(opt) {
                if (!opt) {
                    if (this.selectOnly) return;
                    this.filterValue = '';
                    this.filterFieldSelected = null;
                    this.comboboxInput = '';
                    this.clearSavedFilterValue();
                } else {
                    this.filterValue = opt.value;
                    this.filterFieldSelected = opt.filterField || this.filterField;
                    this.comboboxInput = opt.label;
                    this.saveFilterValue(this.filterValue);
                }
                this.searchValue = '';
                this.comboboxOpen = false;
                this.comboboxHighlightIndex = -1;
                this.fetchItems();
            },

            /** Cookie key scoped by current Kanban resource URI. */
            filterCookieKey() {
                var scope = this.resourceUri || 'default';
                return 'kanban_filter_' + scope;
            },

            /** Save selected filter value for 30 days. */
            saveFilterValue(value) {
                if (!value) return;
                var days = 30;
                var expires = new Date();
                expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = this.filterCookieKey() + '=' + encodeURIComponent(String(value)) + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
            },

            /** Read saved filter value from cookie. */
            getSavedFilterValue() {
                var key = this.filterCookieKey() + '=';
                var parts = document.cookie ? document.cookie.split(';') : [];
                for (var i = 0; i < parts.length; i += 1) {
                    var c = parts[i].trim();
                    if (c.indexOf(key) === 0) {
                        return decodeURIComponent(c.substring(key.length));
                    }
                }
                return '';
            },

            /** Remove saved filter cookie. */
            clearSavedFilterValue() {
                document.cookie = this.filterCookieKey() + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
            },

            /** Moves keyboard highlight to the next option in the combobox dropdown. */
            comboboxFocusNext() {
                var opts = this.filteredFilterOptions;
                if (!opts.length) return;
                if (this.showFilterAll) {
                    var max = opts.length;
                    this.comboboxHighlightIndex = this.comboboxHighlightIndex >= max - 1 ? -1 : this.comboboxHighlightIndex + 1;
                    return;
                }
                if (this.comboboxHighlightIndex < 0) {
                    this.comboboxHighlightIndex = 0;
                    return;
                }
                this.comboboxHighlightIndex = this.comboboxHighlightIndex >= opts.length - 1 ? 0 : this.comboboxHighlightIndex + 1;
            },

            /** Moves keyboard highlight to the previous option in the combobox dropdown. */
            comboboxFocusPrev() {
                var opts = this.filteredFilterOptions;
                if (!opts.length) return;
                if (this.showFilterAll) {
                    this.comboboxHighlightIndex = this.comboboxHighlightIndex <= -1 ? opts.length - 1 : this.comboboxHighlightIndex - 1;
                    return;
                }
                if (this.comboboxHighlightIndex < 0) {
                    this.comboboxHighlightIndex = opts.length - 1;
                    return;
                }
                this.comboboxHighlightIndex = this.comboboxHighlightIndex <= 0 ? opts.length - 1 : this.comboboxHighlightIndex - 1;
            },

            /** On Enter: confirms the highlighted combobox option and refetches. */
            comboboxConfirmHighlighted() {
                if (!this.comboboxOpen || !this.filterField || !this.filterOptions.length) {
                    this.fetchItems();
                    return;
                }
                if (this.comboboxHighlightIndex === -1) {
                    if (this.showFilterAll) {
                        this.selectFilterOption(null);
                        return;
                    }
                    var first = this.filteredFilterOptions[0];
                    if (first) this.selectFilterOption(first);
                    return;
                }
                var opt = this.filteredFilterOptions[this.comboboxHighlightIndex];
                if (opt) this.selectFilterOption(opt);
            },

            /**
             * Fetches items from the API (initial load or after filter/search change).
             */
            async fetchItems() {
                this.loading = true;
                // Fire counts fetch in parallel — does not block items rendering
                this.fetchCounts();
                try {
                    var statuses = this.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/items?config=' + this.configParam +
                        '&statuses=' + encodeURIComponent(statuses);
                    if (this.filterFieldSelected && this.filterValue) {
                        url += '&filterField=' + encodeURIComponent(this.filterFieldSelected) + '&filterValue=' + encodeURIComponent(this.filterValue);
                    } else if (this.filterField && this.filterValue) {
                        url += '&' + encodeURIComponent(this.filterField) + '=' + encodeURIComponent(this.filterValue);
                    }
                    if (this.searchFields.length && this.searchValue) {
                        url += '&search=' + encodeURIComponent(this.searchValue);
                    }

                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';

                    var response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });

                    if (!response.ok) throw new Error('Fetch failed: ' + response.status);
                    var list = await response.json();
                    this.items = list;
                    this.updateHasMoreByStatus();
                } catch (e) {
                    this.showError(this.translations.errorLoading);
                    console.error('Kanban fetch error:', e);
                } finally {
                    this.loading = false;
                    var self = this;
                    this.$nextTick(function () {
                        self.updateBoardFit();
                    });
                }
            },

            /**
             * Updates hasMoreByStatus: for each column, sets true if item count >= limitPerColumn
             * (so "Load more" button is shown when there may be more items for that column).
             */
            updateHasMoreByStatus() {
                var self = this;
                var statusCounts = {};
                self.items.forEach(function (item) {
                    statusCounts[item.status] = (statusCounts[item.status] || 0) + 1;
                });
                var next = {};
                var limit = self.limitPerColumn;
                self.columns.forEach(function (col) {
                    var count = statusCounts[col.value] || 0;
                    next[col.value] = count >= limit;
                });
                self.hasMoreByStatus = next;
            },

            /**
             * Returns the real total count for a column when available.
             * Supports both legacy numeric responses and extended {count, sum} objects.
             */
            getHeaderCount(status) {
                var v = this.totalCountByStatus ? this.totalCountByStatus[status] : undefined;
                if (v && typeof v === 'object' && v.count !== undefined) return v.count;
                if (v !== undefined) return v;
                return this.getColumnItems(status).length;
            },

            /**
             * Returns the sum value for a column when provided by backend.
             */
            getHeaderSum(status) {
                var v = this.totalCountByStatus ? this.totalCountByStatus[status] : undefined;
                if (v && typeof v === 'object' && v.sum !== undefined && v.sum !== null) {
                    var n = Number(v.sum);
                    return isNaN(n) ? null : n;
                }
                return null;
            },

            /**
             * Formats a number as EUR currency (Italian formatting).
             */
            formatCurrency(amount) {
                try {
                    // it-IT defaults to useGrouping "auto", which omits the thousands separator for values < 10_000.
                    return new Intl.NumberFormat('it-IT', {
                        style: 'currency',
                        currency: 'EUR',
                        useGrouping: 'always',
                    }).format(Number(amount) || 0);
                } catch (e) {
                    return '€ ' + String(amount);
                }
            },

            /**
             * Fetches total counts per status from the backend (same filters as fetchItems).
             * Stores results in totalCountByStatus so the header badge always shows the real total.
             */
            async fetchCounts() {
                var self = this;
                try {
                    var statuses = self.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/counts?config=' + self.configParam +
                        '&statuses=' + encodeURIComponent(statuses);
                    if (self.filterFieldSelected && self.filterValue) {
                        url += '&filterField=' + encodeURIComponent(self.filterFieldSelected) + '&filterValue=' + encodeURIComponent(self.filterValue);
                    } else if (self.filterField && self.filterValue) {
                        url += '&' + encodeURIComponent(self.filterField) + '=' + encodeURIComponent(self.filterValue);
                    }
                    if (self.searchFields.length && self.searchValue) {
                        url += '&search=' + encodeURIComponent(self.searchValue);
                    }
                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';
                    var response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    if (!response.ok) throw new Error('Counts fetch failed: ' + response.status);
                    self.totalCountByStatus = await response.json();
                } catch (e) {
                    console.error('Kanban counts fetch error:', e);
                }
            },

            /**
             * Fetches the next page of items for a single column (load more). Appends to items and updates hasMore for that status.
             * @param {string} status - Column status value to load more for.
             */
            async fetchMore(status) {
                var self = this;
                var currentCount = self.getColumnItems(status).length;
                if (!self.hasMoreByStatus[status]) return;
                self.loadingMoreStatus = status;
                try {
                    var statuses = self.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/items?config=' + encodeURIComponent(JSON.stringify(self.apiConfig)) +
                        '&statuses=' + encodeURIComponent(statuses) +
                        '&singleStatus=' + encodeURIComponent(status) +
                        '&offset=' + encodeURIComponent(currentCount) +
                        '&limit=10';
                    if (self.filterFieldSelected && self.filterValue) {
                        url += '&filterField=' + encodeURIComponent(self.filterFieldSelected) + '&filterValue=' + encodeURIComponent(self.filterValue);
                    } else if (self.filterField && self.filterValue) {
                        url += '&' + encodeURIComponent(self.filterField) + '=' + encodeURIComponent(self.filterValue);
                    }
                    if (self.searchFields.length && self.searchValue) {
                        url += '&search=' + encodeURIComponent(self.searchValue);
                    }
                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';
                    var response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    if (!response.ok) throw new Error('Fetch failed: ' + response.status);
                    var list = await response.json();
                    self.items = self.items.concat(list);
                    self.hasMoreByStatus = Object.assign({}, self.hasMoreByStatus, { [status]: list.length >= 10 });
                } catch (e) {
                    self.showError(self.translations.errorLoading);
                    console.error('Kanban load more error:', e);
                } finally {
                    self.loadingMoreStatus = null;
                }
            },

            /**
             * HTML5 drag start: stores dragged item, sets dataTransfer and adds dragging class.
             */
            onDragStart(event, item) {
                if (!this.canUpdate) { event.preventDefault(); return; }
                this.draggedItem = item;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(item.id));
                event.target.classList.add('kanban-item-dragging');
                this.dropIndicator = null;
            },

            /** HTML5 drag end: removes dragging class, clears dragged item and auto-scroll state. */
            onDragEnd(event) {
                event.target.classList.remove('kanban-item-dragging');
                this.draggedItem = null;
                this.dragOverColumn = null;
                this.dragScrollDirection = 0;
                this.dropIndicator = null;
                this.dragVScrollDirection = 0;
                this.dragVScrollEl = null;
                if (this.dragScrollRAF) {
                    cancelAnimationFrame(this.dragScrollRAF);
                    this.dragScrollRAF = null;
                }
                if (this.dragVScrollRAF) {
                    cancelAnimationFrame(this.dragVScrollRAF);
                    this.dragVScrollRAF = null;
                }
            },

            /**
             * Drag over the board area: detects when near left/right edge to trigger horizontal auto-scroll.
             */
            onBoardDragOver(event) {
                if (!this.canUpdate || !this.draggedItem) return;
                var board = this.$refs.kanbanBoard;
                if (!board) return;
                var rect = board.getBoundingClientRect();
                var zone = 80;
                var dir = 0;
                if (event.clientX < rect.left + zone) dir = -1;
                else if (event.clientX > rect.right - zone) dir = 1;
                this.dragScrollDirection = dir;
                if (dir !== 0 && !this.dragScrollRAF) this.startDragScroll();
                if (dir === 0 && this.dragScrollRAF) {
                    cancelAnimationFrame(this.dragScrollRAF);
                    this.dragScrollRAF = null;
                }
            },

            /** Animation loop: scrolls the board horizontally while dragging near the edge. */
            startDragScroll() {
                var self = this;
                function tick() {
                    if (!self.draggedItem || self.dragScrollDirection === 0) {
                        self.dragScrollRAF = null;
                        return;
                    }
                    var board = self.$refs.kanbanBoard;
                    if (board) board.scrollLeft += self.dragScrollDirection * 6;
                    self.dragScrollRAF = requestAnimationFrame(tick);
                }
                self.dragScrollRAF = requestAnimationFrame(tick);
            },

            /** Vertical auto-scroll inside a single column while dragging near edges. */
            startDragVScroll() {
                var self = this;
                function tick() {
                    if (!self.draggedItem || self.dragVScrollDirection === 0 || !self.dragVScrollEl) {
                        self.dragVScrollRAF = null;
                        return;
                    }
                    self.dragVScrollEl.scrollTop += self.dragVScrollDirection * 6;
                    self.dragVScrollRAF = requestAnimationFrame(tick);
                }
                self.dragVScrollRAF = requestAnimationFrame(tick);
            },

            /** Drag over a column: sets drop effect and stores target column for visual feedback. */
            onDragOver(event, columnValue) {
                event.dataTransfer.dropEffect = 'move';
                this.dragOverColumn = columnValue;
            },

            /** Drag over an item inside the same column for reorder. */
            onItemDragOver(event, targetItem, columnValue) {
                if (!this.canReorder || !this.draggedItem) return;
                if (!targetItem) return;
                this.maybeAutoScrollColumn(event);
                var rect = event.currentTarget.getBoundingClientRect();
                var mid = rect.top + rect.height / 2;
                var insertBefore = event.clientY < mid;
                this.dropIndicator = {
                    status: columnValue,
                    targetId: targetItem.id,
                    before: insertBefore,
                };
                this.dragOverColumn = columnValue;
                event.dataTransfer.dropEffect = 'move';
            },

            /** Drop on an item inside the same column and persist new priority order. */
            async onItemDrop(event, targetItem, columnValue) {
                if (!this.canReorder || !this.draggedItem) return;
                var dragged = this.draggedItem;
                var insertBefore = true;
                if (this.dropIndicator && this.dropIndicator.status === columnValue && String(this.dropIndicator.targetId) === String(targetItem.id)) {
                    insertBefore = !!this.dropIndicator.before;
                }
                this.dropIndicator = null;
                if (dragged.status !== columnValue) {
                    await this.onDrop(event, columnValue, targetItem, insertBefore);
                    return;
                }
                if (String(dragged.id) === String(targetItem.id)) return;

                var columnItems = this.getColumnItems(columnValue).slice();
                var fromIdx = columnItems.findIndex(function (it) { return String(it.id) === String(dragged.id); });
                var toIdx = columnItems.findIndex(function (it) { return String(it.id) === String(targetItem.id); });
                if (fromIdx === -1 || toIdx === -1) return;

                var moved = columnItems.splice(fromIdx, 1)[0];
                if (fromIdx < toIdx) {
                    toIdx -= 1;
                }
                var insertIdx = insertBefore ? toIdx : toIdx + 1;
                insertIdx = Math.max(0, Math.min(insertIdx, columnItems.length));
                columnItems.splice(insertIdx, 0, moved);
                this.applyColumnOrder(columnValue, columnItems);
                await this.persistColumnOrder(columnValue);
            },

            /** When dragging over the column body but not on a specific item, position is "end". */
            onColumnBodyDragOver(event, columnValue) {
                if (!this.canReorder || !this.draggedItem) return;
                this.maybeAutoScrollColumn(event);
                if (!event || !event.target || !event.target.closest) return;
                var closestItem = event.target.closest('.kanban-item');
                if (closestItem) return;
                this.dropIndicator = {
                    status: columnValue,
                    targetId: null,
                    before: false,
                };
                this.dragOverColumn = columnValue;
            },

            /** Detect near top/bottom of column body and auto-scroll vertically. */
            maybeAutoScrollColumn(event) {
                if (!event) return;
                // Find the scroll container for the column.
                var el = null;
                if (event.currentTarget && event.currentTarget.classList && event.currentTarget.classList.contains('kanban-column-body')) {
                    el = event.currentTarget;
                } else if (event.target && event.target.closest) {
                    el = event.target.closest('.kanban-column-body');
                }
                if (!el) return;

                var rect = el.getBoundingClientRect();
                var zone = 60;
                var dir = 0;
                if (event.clientY < rect.top + zone) dir = -1;
                else if (event.clientY > rect.bottom - zone) dir = 1;

                this.dragVScrollEl = el;
                this.dragVScrollDirection = dir;

                if (dir !== 0 && !this.dragVScrollRAF) {
                    this.startDragVScroll();
                }
                if (dir === 0 && this.dragVScrollRAF) {
                    cancelAnimationFrame(this.dragVScrollRAF);
                    this.dragVScrollRAF = null;
                }
            },

            /** Drag leave column: clears target column only when actually leaving the column element. */
            onDragLeave(event) {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    this.dragOverColumn = null;
                }
            },

            /**
             * Drop item into a column: calls API to update status, then updates local state or reverts on error.
             * @param {string} newStatus - Target column status value.
             */
            async onDrop(event, newStatus, targetItem, insertBefore = true) {
                this.dragOverColumn = null;
                this.dropIndicator = null;
                if (!this.canUpdate) return;
                var item = this.draggedItem;
                if (!item) return;
                if (item.status === newStatus) {
                    if (!this.canReorder) return;
                    var currentItems = this.getColumnItems(newStatus).slice();
                    var existingIdx = currentItems.findIndex(function (it) { return String(it.id) === String(item.id); });
                    if (existingIdx === -1 || existingIdx === currentItems.length - 1) return;
                    var movedToEnd = currentItems.splice(existingIdx, 1)[0];
                    currentItems.push(movedToEnd);
                    this.applyColumnOrder(newStatus, currentItems);
                    await this.persistColumnOrder(newStatus);
                    return;
                }

                var oldStatus = item.status;
                item.status = newStatus;
                this.updatingIds.push(item.id);

                try {
                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';

                    var response = await fetch('/nova-vendor/kanban-card/items/' + item.id + '/status', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            status: newStatus,
                            config: this.apiConfig,
                        }),
                    });

                    if (!response.ok) throw new Error('Update failed: ' + response.status);
                    var limit = parseInt(this.statusColumnLimits[newStatus], 10);
                    if (!isNaN(limit) && limit > 0) {
                        await this.fetchItems();
                    } else {
                        if (this.canReorder) {
                            await this.applyCrossColumnPriorityPlacement(item, oldStatus, newStatus, targetItem, insertBefore);
                        }
                        this.fetchCounts();
                    }
                    this.showSuccess(this.translations.statusUpdated);
                } catch (e) {
                    item.status = oldStatus;
                    this.showError(this.translations.errorUpdating);
                    console.error('Kanban update error:', e);
                } finally {
                    this.updatingIds = this.updatingIds.filter(function (id) {
                        return id !== item.id;
                    });
                }
            },

            /**
             * Place moved item into the target column at dropped vertical position and persist priorities.
             */
            async applyCrossColumnPriorityPlacement(item, oldStatus, newStatus, targetItem, insertBefore = true) {
                var targetItems = this.getColumnItems(newStatus).slice().filter(function (it) {
                    return String(it.id) !== String(item.id);
                });
                var insertIdx = targetItems.length;
                // When dropping into a collapsed column (no target item), place it first.
                if (!targetItem && this.isColumnCollapsed(newStatus)) {
                    insertIdx = 0;
                } else if (targetItem) {
                    var idx = targetItems.findIndex(function (it) {
                        return String(it.id) === String(targetItem.id);
                    });
                    if (idx >= 0) insertIdx = insertBefore ? idx : idx + 1;
                }
                targetItems.splice(insertIdx, 0, item);
                this.applyColumnOrder(newStatus, targetItems);
                await this.persistColumnOrder(newStatus);
                if (oldStatus !== newStatus) {
                    await this.persistColumnOrder(oldStatus);
                }
            },

            /** Apply visual order for a status column in local state. */
            applyColumnOrder(status, orderedColumnItems) {
                var statusItemsById = {};
                orderedColumnItems.forEach(function (it) {
                    statusItemsById[String(it.id)] = it;
                });
                var others = this.items.filter(function (it) {
                    return it.status !== status;
                });
                var ordered = orderedColumnItems
                    .map(function (it) { return statusItemsById[String(it.id)] || it; })
                    .filter(Boolean);
                this.items = others.concat(ordered);
            },

            /** Persist status-column order using backend priority field. */
            async persistColumnOrder(status) {
                if (!this.canReorder) return;
                var orderedIds = this.getColumnItems(status).map(function (it) { return Number(it.id); });
                if (!orderedIds.length) return;

                try {
                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';

                    var response = await fetch('/nova-vendor/kanban-card/items/reorder', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            status: status,
                            orderedIds: orderedIds,
                            config: this.apiConfig,
                        }),
                    });
                    if (!response.ok) throw new Error('Reorder failed: ' + response.status);
                } catch (e) {
                    this.showError(this.translations.errorUpdating);
                    await this.fetchItems();
                    console.error('Kanban reorder error:', e);
                }
            },

            /** Navigates to the Nova resource detail page for the given item. */
            openDetail(item) {
                if (this.resourceUri) {
                    var url = '/resources/' + this.resourceUri + '/' + item.id;
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            },

            /** Shows an error toast message for 4 seconds. */
            showError(message) {
                var self = this;
                self.errorMessage = message;
                setTimeout(function () { self.errorMessage = null; }, 4000);
            },

            /** Shows a success toast message for 2 seconds. */
            showSuccess(message) {
                var self = this;
                self.successMessage = message;
                setTimeout(function () { self.successMessage = null; }, 2000);
            },

            /** Toggles the collapsed state of the card and persists it to localStorage. */
            toggleCollapsed() {
                this.isCollapsed = !this.isCollapsed;
                try {
                    localStorage.setItem(this.collapseStorageKey, this.isCollapsed ? '1' : '0');
                } catch (e) {}
            },
            fieldValueClass(field) {
                if (!field || !field.key) return '';
                if (field.key === 'type') return 'kanban-field-type';
                if (field.key === 'description') return 'kanban-field-description';
                return '';
            },
            fieldValueStyle(field) {
                if (!field || field.key !== 'type') return null;
                var type = String(field.value || '').trim().toLowerCase();
                if (type === 'bug') return { color: '#dc2626', fontWeight: '700' };
                if (type === 'feature') return { color: '#2563eb', fontWeight: '700' };
                return { color: '#16a34a', fontWeight: '700' };
            },
            descriptionKey(item, field) {
                return String(item.id) + ':' + String(field.key || field.label || 'description');
            },
            isDescriptionField(field) {
                return !!field && String(field.key || '') === 'description';
            },
            isDescriptionExpanded(item, field) {
                var k = this.descriptionKey(item, field);
                return this.expandedDescriptionMap[k] === true;
            },
            canToggleDescription(item, field) {
                if (!this.isDescriptionField(field)) return false;
                var value = String((field && field.value) || '');
                return value.length > 30;
            },
            getFieldDisplayValue(item, field) {
                var value = String((field && field.value) || '');
                if (!this.isDescriptionField(field)) return value;
                if (this.isDescriptionExpanded(item, field)) return value;
                return value.length > 30 ? value.slice(0, 30) + '...' : value;
            },
            toggleDescription(item, field) {
                var k = this.descriptionKey(item, field);
                this.expandedDescriptionMap[k] = !this.isDescriptionExpanded(item, field);
            },
        },
    });
});
