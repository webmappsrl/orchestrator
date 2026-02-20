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
                <div v-if="(filterField && filterOptions.length) || searchFields.length" class="kanban-toolbar">
                    <div class="kanban-combobox-wrap" :class="{ 'kanban-combobox-has-filter': filterField && filterOptions.length }">
                        <input
                            type="text"
                            class="kanban-combobox-input"
                            :placeholder="comboboxPlaceholder"
                            :value="comboboxInput"
                            @input="onComboboxInput"
                            @focus="comboboxOpen = true"
                            @keydown.down.prevent="comboboxFocusNext"
                            @keydown.up.prevent="comboboxFocusPrev"
                            @keydown.enter.prevent="comboboxConfirmHighlighted"
                            @keydown.escape="comboboxOpen = false"
                        >
                        <button v-if="comboboxInput" type="button" class="kanban-combobox-clear" @click="clearCombobox" aria-label="Clear">×</button>
                        <div v-show="comboboxOpen && filterField && filterOptions.length" class="kanban-combobox-dropdown" ref="comboboxDropdown">
                            <div class="kanban-combobox-option" :class="{ 'kanban-combobox-option-highlight': comboboxHighlightIndex === -1 }" @mousedown.prevent="selectFilterOption(null)">
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
                        :style="{ borderColor: column.color }"
                        @dragover.prevent="canUpdate && onDragOver($event, column.value)"
                        @dragleave="onDragLeave($event)"
                        @drop="canUpdate && onDrop($event, column.value)"
                    >
                        <!-- Column Header -->
                        <div class="kanban-column-header">
                            <span class="kanban-column-title">{{ column.label }}</span>
                            <span class="kanban-column-count" :style="{ backgroundColor: column.color }">
                                {{ getColumnItems(column.value).length }}
                            </span>
                        </div>

                        <!-- Items -->
                        <div class="kanban-column-body" :class="{ 'kanban-column-dragover': dragOverColumn === column.value }">
                            <div
                                v-for="item in getColumnItems(column.value)"
                                :key="item.id"
                                class="kanban-item"
                                :class="{ 'kanban-item-updating': updatingIds.includes(item.id), 'kanban-item-readonly': !canUpdate }"
                                :draggable="canUpdate"
                                @dragstart="onDragStart($event, item)"
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
                                        <span class="kanban-item-field-value">{{ field.value }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="getColumnItems(column.value).length === 0 && dragOverColumn !== column.value" class="kanban-column-empty">
                                {{ translations.noItems }}
                            </div>
                            <!-- Load more (when column has reached initial limit and there may be more) -->
                            <div v-if="getColumnItems(column.value).length >= limitPerColumn && hasMoreByStatus[column.value]" class="kanban-column-load-more">
                                <button type="button" class="kanban-load-more-btn" :disabled="loadingMoreStatus === column.value" @click.stop="fetchMore(column.value)">
                                    <span v-if="loadingMoreStatus === column.value" class="kanban-load-more-spinner"></span>
                                    <span v-else>{{ translations.loadMore }}</span>
                                </button>
                            </div>
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
            var storageKey = 'kanban_collapsed_' + (this.card.configToken || '').slice(0, 32);
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
                errorMessage: null,
                successMessage: null,
                isCollapsed: saved,
                collapseStorageKey: storageKey,
                hasMoreByStatus: {},
                loadingMoreStatus: null,
                dragScrollRAF: null,
                dragScrollDirection: 0,
                comboboxInput: '',
                comboboxOpen: false,
                comboboxHighlightIndex: -1,
            };
        },

        /** Computed: card config (columns, token, filter options, translations), combobox placeholder and filtered options. */
        computed: {
            columns() {
                return this.card.columns || [];
            },
            configToken() {
                return this.card.configToken || '';
            },
            resourceUri() {
                return this.card.resourceUri || null;
            },
            filterField() {
                return this.card.filterField || null;
            },
            filterOptions() {
                return this.card.filterOptions || [];
            },
            searchFields() {
                return this.card.searchFields || [];
            },
            collapsible() {
                return this.card.collapsible === true;
            },
            canUpdate() {
                return this.card.canUpdate !== false;
            },
            limitPerColumn() {
                var n = parseInt(this.card.limitPerColumn, 10);
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
                if (!this.comboboxInput || !this.filterOptions.length) return this.filterOptions;
                var q = this.comboboxInput.toLowerCase().trim();
                return this.filterOptions.filter(function (o) {
                    return (o.label || '').toLowerCase().indexOf(q) !== -1;
                });
            },
            translations() {
                var t = this.card.translations || {};
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
                };
            },
        },

        /** On mount: restore initial filter label in combobox, set filterFieldSelected when options have filterField, fetch items, bind click-outside for dropdown. */
        mounted() {
            var self = this;
            if (self.filterValue && self.filterOptions.length) {
                var opt = self.filterOptions.find(function (o) { return o.value === self.filterValue; });
                if (opt) {
                    self.comboboxInput = opt.label;
                    self.filterFieldSelected = opt.filterField || self.filterField;
                }
            } else if (self.filterOptions.length && self.filterOptions[0].filterField) {
                self.filterFieldSelected = self.filterField;
            }
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
                return this.items.filter(function (item) {
                    return item.status === status;
                });
            },

            /**
             * Handles combobox text input. Debounces then applies filter (if text matches an option)
             * or search, then refetches items.
             */
            onComboboxInput(event) {
                var self = this;
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
                if (this.$refs.comboboxDropdown && !this.$el.contains(event.target)) {
                    this.comboboxOpen = false;
                }
            },

            /**
             * Clears filter, search and combobox input, then refetches all items.
             */
            clearCombobox() {
                this.comboboxInput = '';
                this.filterValue = '';
                this.filterFieldSelected = null;
                this.searchValue = '';
                this.comboboxOpen = false;
                this.comboboxHighlightIndex = -1;
                this.fetchItems();
            },

            /**
             * Applies the selected filter option (or "All" when opt is null), closes dropdown, refetches.
             * @param {{ value: string, label: string }|null} opt - Selected option or null for "All".
             */
            selectFilterOption(opt) {
                if (!opt) {
                    this.filterValue = '';
                    this.filterFieldSelected = null;
                    this.comboboxInput = '';
                } else {
                    this.filterValue = opt.value;
                    this.filterFieldSelected = opt.filterField || this.filterField;
                    this.comboboxInput = opt.label;
                }
                this.searchValue = '';
                this.comboboxOpen = false;
                this.comboboxHighlightIndex = -1;
                this.fetchItems();
            },

            /** Moves keyboard highlight to the next option in the combobox dropdown (or wraps to "All"). */
            comboboxFocusNext() {
                var opts = this.filteredFilterOptions;
                var max = opts.length;
                this.comboboxHighlightIndex = this.comboboxHighlightIndex >= max - 1 ? -1 : this.comboboxHighlightIndex + 1;
            },

            /** Moves keyboard highlight to the previous option in the combobox dropdown (or wraps to last). */
            comboboxFocusPrev() {
                var opts = this.filteredFilterOptions;
                this.comboboxHighlightIndex = this.comboboxHighlightIndex <= -1 ? opts.length - 1 : this.comboboxHighlightIndex - 1;
            },

            /** On Enter: confirms the currently highlighted combobox option (or "All") and refetches. */
            comboboxConfirmHighlighted() {
                if (!this.comboboxOpen || !this.filterField || !this.filterOptions.length) {
                    this.fetchItems();
                    return;
                }
                if (this.comboboxHighlightIndex === -1) {
                    this.selectFilterOption(null);
                    return;
                }
                var opt = this.filteredFilterOptions[this.comboboxHighlightIndex];
                if (opt) this.selectFilterOption(opt);
            },

            /**
             * Fetches items from the API (initial load or after filter/search change).
             * Builds URL with configToken, statuses, optional filter and search; then updates items and hasMoreByStatus.
             */
            async fetchItems() {
                this.loading = true;
                try {
                    var statuses = this.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/items?configToken=' +
                        encodeURIComponent(this.configToken) +
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
             * Fetches the next page of items for a single column (load more). Appends to items and updates hasMore for that status.
             * @param {string} status - Column status value to load more for.
             */
            async fetchMore(status) {
                var self = this;
                var currentCount = self.getColumnItems(status).length;
                if (currentCount < self.limitPerColumn) return;
                self.loadingMoreStatus = status;
                try {
                    var statuses = self.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/items?configToken=' +
                        encodeURIComponent(self.configToken) +
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
            },

            /** HTML5 drag end: removes dragging class, clears dragged item and auto-scroll state. */
            onDragEnd(event) {
                event.target.classList.remove('kanban-item-dragging');
                this.draggedItem = null;
                this.dragOverColumn = null;
                this.dragScrollDirection = 0;
                if (this.dragScrollRAF) {
                    cancelAnimationFrame(this.dragScrollRAF);
                    this.dragScrollRAF = null;
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
                    if (board) board.scrollLeft += self.dragScrollDirection * 8;
                    self.dragScrollRAF = requestAnimationFrame(tick);
                }
                self.dragScrollRAF = requestAnimationFrame(tick);
            },

            /** Drag over a column: sets drop effect and stores target column for visual feedback. */
            onDragOver(event, columnValue) {
                event.dataTransfer.dropEffect = 'move';
                this.dragOverColumn = columnValue;
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
            async onDrop(event, newStatus) {
                this.dragOverColumn = null;
                if (!this.canUpdate) return;
                var item = this.draggedItem;
                if (!item) return;
                if (item.status === newStatus) return;

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
                            configToken: this.configToken,
                        }),
                    });

                    if (!response.ok) throw new Error('Update failed: ' + response.status);
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

            /** Navigates to the Nova resource detail page for the given item. */
            openDetail(item) {
                if (this.resourceUri) {
                    Nova.visit('/resources/' + this.resourceUri + '/' + item.id);
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
        },
    });
});
