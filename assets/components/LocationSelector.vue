<template>
    <div class="location-selector" ref="container">
        <label for="location-input">Select Location:</label>
        <div class="autocomplete-wrapper">
            <input
                id="location-input"
                ref="input"
                type="text"
                v-model="searchText"
                :placeholder="placeholder"
                :disabled="loading"
                @focus="onFocus"
                @blur="onBlur"
                @keydown="onKeydown"
                autocomplete="off"
            />
            <button
                type="button"
                class="clear-btn"
                v-if="searchText && !loading"
                @mousedown.prevent="clearSelection"
                aria-label="Clear selection"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <button
                type="button"
                class="dropdown-btn"
                :disabled="loading"
                @mousedown.prevent="toggleDropdown"
                aria-label="Toggle dropdown"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline :points="isOpen ? '18 15 12 9 6 15' : '6 9 12 15 18 9'"></polyline>
                </svg>
            </button>
            <div
                v-show="isOpen && filteredLocations.length > 0"
                class="dropdown-list"
                ref="dropdown"
            >
                <label class="show-all-toggle" @mousedown.prevent>
                    <input
                        type="checkbox"
                        v-model="showAllLocations"
                    />
                    <span>Show all RWS locations</span>
                </label>
                <ul class="locations-list">
                    <li
                        v-for="(location, index) in filteredLocations"
                        :key="location.id"
                        :class="{ highlighted: index === highlightedIndex }"
                        @mousedown.prevent="selectLocation(location)"
                        @mouseenter="highlightedIndex = index"
                    >
                        {{ location.name }}
                    </li>
                </ul>
            </div>
            <div v-show="isOpen && filteredLocations.length === 0" class="dropdown-empty">
                No locations found
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LocationSelector',
    props: {
        locations: {
            type: Array,
            required: true,
        },
        selected: {
            type: Object,
            default: null,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['select'],
    data() {
        return {
            searchText: '',
            isOpen: false,
            highlightedIndex: 0,
            showAllLocations: false,
        };
    },
    computed: {
        placeholder() {
            return this.loading ? 'Loading locations...' : 'Type to jump to...';
        },
        simplifiedLocations() {
            if (!this.locations.length) return [];

            // Group locations by base name and geographic proximity
            const groups = new Map();

            for (const loc of this.locations) {
                const baseName = this.extractBaseName(loc.name);
                let foundGroup = false;

                // Check if this location belongs to an existing group
                for (const [key, group] of groups) {
                    const representative = group[0];
                    const sameBaseName = this.extractBaseName(representative.name) === baseName;
                    const isNearby = this.isNearby(loc, representative);

                    if (sameBaseName || isNearby) {
                        group.push(loc);
                        foundGroup = true;
                        break;
                    }
                }

                if (!foundGroup) {
                    groups.set(loc.id, [loc]);
                }
            }

            // Pick best representative from each group
            const simplified = [];
            for (const group of groups.values()) {
                // Sort by name length (prefer shorter, more generic names)
                // Then by whether it doesn't have numbers or technical suffixes
                group.sort((a, b) => {
                    const aScore = this.getNameScore(a.name);
                    const bScore = this.getNameScore(b.name);
                    return aScore - bScore;
                });
                simplified.push(group[0]);
            }

            // Sort alphabetically
            return simplified.sort((a, b) => a.name.localeCompare(b.name));
        },
        activeLocations() {
            return this.showAllLocations ? this.locations : this.simplifiedLocations;
        },
        filteredLocations() {
            return this.activeLocations;
        },
    },
    watch: {
        selected: {
            immediate: true,
            handler(newVal) {
                this.searchText = newVal?.name || '';
            },
        },
        searchText(newVal) {
            if (!newVal.trim() || !this.isOpen) return;
            this.scrollToMatch(newVal);
        },
    },
    methods: {
        onFocus() {
            this.isOpen = true;
            this.highlightedIndex = 0;
            if (this.selected) {
                this.searchText = '';
            }
        },
        onBlur() {
            setTimeout(() => {
                this.isOpen = false;
                if (this.selected) {
                    this.searchText = this.selected.name;
                } else {
                    this.searchText = '';
                }
            }, 150);
        },
        onKeydown(event) {
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    if (!this.isOpen) {
                        this.isOpen = true;
                    } else if (this.highlightedIndex < this.filteredLocations.length - 1) {
                        this.highlightedIndex++;
                        this.scrollToHighlighted();
                    }
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    if (this.highlightedIndex > 0) {
                        this.highlightedIndex--;
                        this.scrollToHighlighted();
                    }
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.isOpen && this.filteredLocations[this.highlightedIndex]) {
                        this.selectLocation(this.filteredLocations[this.highlightedIndex]);
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    this.isOpen = false;
                    if (this.selected) {
                        this.searchText = this.selected.name;
                    }
                    this.$refs.input.blur();
                    break;
            }
        },
        selectLocation(location) {
            this.searchText = location.name;
            this.isOpen = false;
            this.$emit('select', location);
        },
        clearSelection() {
            this.searchText = '';
            this.$emit('select', null);
            this.$refs.input.focus();
        },
        toggleDropdown() {
            if (this.isOpen) {
                this.isOpen = false;
            } else {
                this.isOpen = true;
                this.$refs.input.focus();
            }
        },
        scrollToHighlighted() {
            this.$nextTick(() => {
                const dropdown = this.$refs.dropdown;
                const list = dropdown?.querySelector('.locations-list');
                const highlighted = list?.children[this.highlightedIndex];
                if (highlighted) {
                    highlighted.scrollIntoView({ block: 'nearest' });
                }
            });
        },
        scrollToMatch(searchText) {
            const search = searchText.toLowerCase();
            const index = this.filteredLocations.findIndex(loc =>
                loc.name.toLowerCase().startsWith(search)
            );
            if (index !== -1) {
                this.highlightedIndex = index;
                this.scrollToHighlighted();
            }
        },
        extractBaseName(name) {
            // Extract base name before comma or common suffixes
            let base = name.split(',')[0].trim();
            // Remove trailing numbers
            base = base.replace(/\s+\d+$/, '');
            // Remove common technical suffixes
            base = base.replace(/\s+(meetpunt|punt|boven|beneden|links|rechts|noord|oost|zuid|west|inlaat|uitlaat|platform|badstrand|zwemstrand|strand)$/i, '');
            return base.toLowerCase();
        },
        isNearby(loc1, loc2) {
            // Check if two locations are within ~3km of each other
            const lat1 = loc1.latitude;
            const lon1 = loc1.longitude;
            const lat2 = loc2.latitude;
            const lon2 = loc2.longitude;

            // Quick approximation: 1 degree lat ≈ 111km, 1 degree lon ≈ 67km (at NL latitude)
            const latDiff = Math.abs(lat1 - lat2) * 111;
            const lonDiff = Math.abs(lon1 - lon2) * 67;
            const approxDistance = Math.sqrt(latDiff * latDiff + lonDiff * lonDiff);

            return approxDistance < 3;
        },
        getNameScore(name) {
            // Lower score = better (more suitable as representative)
            let score = name.length;

            // Penalize names with numbers
            if (/\d/.test(name)) score += 50;

            // Penalize names with technical terms
            if (/meetpunt|punt|klep|inlaat|uitlaat|platform/i.test(name)) score += 100;

            // Penalize names with directional suffixes
            if (/boven|beneden|links|rechts|noord|oost|zuid|west/i.test(name)) score += 30;

            // Prefer names with "badstrand" or "zwemstrand" (swimming-relevant)
            if (/badstrand|zwemstrand|strand/i.test(name)) score -= 20;

            return score;
        },
    },
};
</script>

<style scoped>
.location-selector {
    position: relative;
    z-index: 1000;
}

.autocomplete-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.autocomplete-wrapper input {
    width: 100%;
    padding: 1rem 5rem 1rem 1.25rem;
    font-size: 1rem;
    border: 1px solid var(--color-card-border);
    border-radius: 16px;
    background: var(--color-card);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    color: var(--color-text);
    transition: all 0.3s ease;
}

.autocomplete-wrapper input::placeholder {
    color: var(--color-text-light);
}

.autocomplete-wrapper input:hover {
    border-color: var(--color-accent);
    box-shadow: 0 0 30px rgba(144, 224, 239, 0.2);
}

.autocomplete-wrapper input:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(144, 224, 239, 0.3);
}

.autocomplete-wrapper input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.clear-btn,
.dropdown-btn {
    position: absolute;
    background: none;
    border: none;
    color: var(--color-text-light);
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.clear-btn:hover,
.dropdown-btn:hover {
    color: var(--color-primary);
}

.clear-btn:disabled,
.dropdown-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.clear-btn {
    right: 2.75rem;
}

.dropdown-btn {
    right: 1rem;
}

.dropdown-list {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    overflow-x: hidden;
    margin: 0;
    padding: 0.5rem;
    list-style: none;
    background: linear-gradient(180deg, rgba(224, 244, 252, 0.97) 0%, rgba(184, 230, 247, 0.97) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--color-card-border);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 119, 182, 0.15);
    z-index: 100;
}

.dropdown-list::before,
.dropdown-list::after {
    content: '';
    position: absolute;
    left: -100%;
    right: -100%;
    top: -100%;
    bottom: -100%;
    pointer-events: none;
    opacity: 0.5;
    z-index: -1;
}

.dropdown-list::before {
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 100'%3E%3Cpath fill='none' stroke='rgba(135,206,235,0.3)' stroke-width='1.5' d='M0,50 Q50,25 100,50 T200,50'/%3E%3C/svg%3E") repeat;
    background-size: 220px 110px;
    transform: rotate(-12deg);
    animation: dropdown-wave-1 18s linear infinite;
}

.dropdown-list::after {
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 120'%3E%3Cpath fill='none' stroke='rgba(135,206,235,0.2)' stroke-width='1.5' d='M0,60 Q60,90 120,60 T240,60'/%3E%3C/svg%3E") repeat;
    background-size: 260px 130px;
    transform: rotate(-8deg);
    animation: dropdown-wave-2 25s linear infinite;
}

@keyframes dropdown-wave-1 {
    0% { background-position: 0 0; }
    100% { background-position: 220px 110px; }
}

@keyframes dropdown-wave-2 {
    0% { background-position: 260px 0; }
    100% { background-position: 0 130px; }
}

.show-all-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    color: var(--color-text-light);
    cursor: pointer;
    border-bottom: 1px solid rgba(0, 100, 150, 0.1);
    transition: color 0.2s ease;
}

.show-all-toggle:hover {
    color: var(--color-text);
}

.show-all-toggle input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    accent-color: var(--color-primary);
    cursor: pointer;
}

.locations-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.locations-list li {
    position: relative;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s ease;
    color: var(--color-text);
}

.locations-list li:hover,
.locations-list li.highlighted {
    background: linear-gradient(135deg, rgba(0, 150, 199, 0.2) 0%, rgba(0, 119, 182, 0.25) 100%);
    color: var(--color-primary);
    transform: translateX(4px);
    box-shadow: inset 0 0 0 1px rgba(0, 150, 199, 0.2);
}

.dropdown-empty {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    padding: 1rem;
    text-align: center;
    background: linear-gradient(180deg, rgba(224, 244, 252, 0.97) 0%, rgba(184, 230, 247, 0.97) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--color-card-border);
    border-radius: 16px;
    color: var(--color-text-light);
    z-index: 100;
}

/* Custom scrollbar for dropdown */
.dropdown-list::-webkit-scrollbar {
    width: 6px;
}

.dropdown-list::-webkit-scrollbar-track {
    background: transparent;
}

.dropdown-list::-webkit-scrollbar-thumb {
    background: rgba(0, 100, 150, 0.25);
    border-radius: 3px;
}

.dropdown-list::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 100, 150, 0.4);
}
</style>
