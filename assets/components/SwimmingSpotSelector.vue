<template>
    <div class="swimming-spot-selector" ref="container">
        <label for="spot-input">Select Swimming Spot:</label>
        <div class="autocomplete-wrapper">
            <input
                id="spot-input"
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
                v-show="isOpen"
                class="dropdown-panel"
                ref="dropdown"
            >
                <div class="dropdown-split">
                    <div class="dropdown-list-section">
                        <ul class="spots-list" v-if="filteredSpots.length > 0">
                            <li
                                v-for="(spot, index) in filteredSpots"
                                :key="spot.id"
                                :class="{ highlighted: index === highlightedIndex }"
                                @mousedown.prevent="selectSpot(spot)"
                                @mouseenter="highlightedIndex = index"
                            >
                                {{ spot.name }}
                            </li>
                        </ul>
                        <div v-else class="dropdown-empty-inline">
                            No swimming spots found
                        </div>
                    </div>
                    <div class="dropdown-map-section" ref="mapContainer">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'SwimmingSpotSelector',
    props: {
        swimmingSpots: {
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
            map: null,
            markers: [],
        };
    },
    computed: {
        placeholder() {
            return this.loading ? 'Loading swimming spots...' : 'Type to jump to...';
        },
        filteredSpots() {
            return this.swimmingSpots;
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
        isOpen(newVal) {
            if (newVal) {
                this.$nextTick(() => {
                    this.initMap();
                });
            }
        },
        swimmingSpots: {
            handler() {
                if (this.map) {
                    this.updateMarkers();
                }
            },
            deep: true,
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
        onBlur(event) {
            setTimeout(() => {
                const container = this.$refs.container;
                if (container && container.contains(document.activeElement)) {
                    return;
                }
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
                    } else if (this.highlightedIndex < this.filteredSpots.length - 1) {
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
                    if (this.isOpen && this.filteredSpots[this.highlightedIndex]) {
                        this.selectSpot(this.filteredSpots[this.highlightedIndex]);
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
        selectSpot(spot) {
            this.searchText = spot.name;
            this.isOpen = false;
            this.$emit('select', spot);
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
                const list = dropdown?.querySelector('.spots-list');
                const highlighted = list?.children[this.highlightedIndex];
                if (highlighted) {
                    highlighted.scrollIntoView({ block: 'nearest' });
                }
            });
        },
        scrollToMatch(searchText) {
            const search = searchText.toLowerCase();
            const index = this.filteredSpots.findIndex(spot =>
                spot.name.toLowerCase().includes(search)
            );
            if (index !== -1) {
                this.highlightedIndex = index;
                this.scrollToHighlighted();
            }
        },
        initMap() {
            if (this.map) {
                this.map.invalidateSize();
                return;
            }

            const mapContainer = this.$refs.mapContainer;
            if (!mapContainer) return;

            this.map = L.map(mapContainer, {
                zoomControl: true,
                attributionControl: false,
            }).setView([52.2, 5.0], 7);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(this.map);

            this.updateMarkers();

            // Fix map size after it's visible
            setTimeout(() => {
                this.map.invalidateSize();
            }, 100);
        },
        updateMarkers() {
            if (!this.map) return;

            // Clear existing markers
            this.markers.forEach(marker => marker.remove());
            this.markers = [];

            const gogglesIcon = L.divIcon({
                html: '🥽',
                className: 'goggles-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 12],
            });

            this.swimmingSpots.forEach(spot => {
                const marker = L.marker([spot.latitude, spot.longitude], { icon: gogglesIcon })
                    .addTo(this.map);

                marker.on('click', () => {
                    this.selectSpot(spot);
                });

                marker.bindTooltip(spot.name, {
                    permanent: false,
                    direction: 'top',
                    offset: [0, -10],
                });

                this.markers.push(marker);
            });
        },
    },
    beforeUnmount() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    },
};
</script>

<style scoped>
.swimming-spot-selector {
    position: relative;
    z-index: 1000;
    margin-bottom: 2rem;
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

.dropdown-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--color-card-border);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 119, 182, 0.15);
    z-index: 100;
    overflow: hidden;
    padding: 0.5rem;
}

.dropdown-split {
    display: flex;
    height: 320px;
    border-radius: 12px;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(224, 244, 252, 0.97) 0%, rgba(184, 230, 247, 0.97) 100%);
}

.dropdown-list-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(0, 100, 150, 0.15);
    overflow: hidden;
}

.dropdown-map-section {
    flex: 1;
    min-height: 100%;
}

.spots-list {
    list-style: none;
    margin: 0;
    padding: 0.5rem;
    overflow-y: auto;
    flex: 1;
}

.spots-list li {
    position: relative;
    padding: 0.6rem 0.875rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--color-text);
    font-size: 0.9rem;
}

.spots-list li:hover,
.spots-list li.highlighted {
    background: linear-gradient(135deg, rgba(0, 150, 199, 0.2) 0%, rgba(0, 119, 182, 0.25) 100%);
    color: var(--color-primary);
    transform: translateX(4px);
}

.dropdown-empty-inline {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-text-light);
}

/* Custom scrollbar for spots list */
.spots-list::-webkit-scrollbar {
    width: 6px;
}

.spots-list::-webkit-scrollbar-track {
    background: transparent;
}

.spots-list::-webkit-scrollbar-thumb {
    background: rgba(0, 100, 150, 0.25);
    border-radius: 3px;
}

.spots-list::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 100, 150, 0.4);
}

/* Leaflet marker styling */
:deep(.goggles-marker) {
    font-size: 20px;
    text-align: center;
    line-height: 24px;
    cursor: pointer;
}

:deep(.leaflet-tooltip) {
    background: rgba(30, 58, 95, 0.9);
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

:deep(.leaflet-tooltip-top:before) {
    border-top-color: rgba(30, 58, 95, 0.9);
}
</style>
