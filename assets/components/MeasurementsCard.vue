<template>
    <div class="conditions-card measurements">
        <h2>Raw Measurements</h2>

        <div v-if="loading" class="loading-inline">Loading measurements...</div>

        <div v-else-if="error" class="error-inline">{{ error }}</div>

        <div v-else-if="measurements.length === 0" class="empty-inline">
            No measurements available
        </div>

        <div v-else class="measurements-table-wrapper">
            <table class="measurements-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Measurement</th>
                        <th>Value</th>
                        <th>Compartment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in measurements" :key="`${m.grootheid.code}-${m.compartiment.code}`">
                        <td class="code-cell">
                            <code>{{ m.grootheid.code }}</code>
                        </td>
                        <td class="name-cell">
                            <span class="english">{{ m.grootheid.english }}</span>
                            <span class="dutch">{{ m.grootheid.dutch }}</span>
                        </td>
                        <td class="value-cell">
                            <span class="value">{{ formatValue(m.value, m.grootheid.code) }}</span>
                            <span v-if="m.grootheid.unit" class="unit">{{ m.grootheid.unit }}</span>
                            <span v-if="m.location" v-tooltip="formatLocationTooltip(m)" class="info-icon">ⓘ</span>
                        </td>
                        <td class="compartment-cell">
                            <span v-tooltip="m.compartiment.english" class="compartment-badge">
                                {{ m.compartiment.code }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="lastUpdated" class="timestamp">Last updated: {{ formatTime(lastUpdated) }}</p>
    </div>
</template>

<script>
export default {
    name: 'MeasurementsCard',
    props: {
        locationId: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            measurements: [],
            location: null,
            loading: false,
            error: null,
        };
    },
    computed: {
        lastUpdated() {
            if (this.measurements.length === 0) return null;
            // Find the most recent timestamp
            return this.measurements.reduce((latest, m) => {
                if (!latest || m.timestamp > latest) return m.timestamp;
                return latest;
            }, null);
        },
    },
    watch: {
        locationId: {
            immediate: true,
            handler(newId) {
                if (newId) {
                    this.fetchMeasurements(newId);
                } else {
                    this.measurements = [];
                    this.location = null;
                }
            },
        },
    },
    methods: {
        async fetchMeasurements(locationId) {
            this.loading = true;
            this.error = null;
            this.measurements = [];

            try {
                const response = await fetch(`/api/measurements/${locationId}`);
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Failed to fetch measurements');
                }
                const data = await response.json();
                // Handle both swimming spot (primaryLocation) and direct location responses
                this.location = data.primaryLocation || data.location;
                this.measurements = data.measurements;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },
        formatValue(value, code) {
            if (value === null) return 'N/A';

            // Height values are in cm, convert nicely
            if (['WATHTE', 'Hm0', 'Hmax', 'H1/3', 'H1/10', 'H1/50', 'GOLFHTE'].includes(code)) {
                if (Math.abs(value) >= 100) {
                    return (value / 100).toFixed(2);
                }
                return value.toFixed(1);
            }

            // Temperature
            if (code === 'T') {
                return value.toFixed(1);
            }

            // Wave period
            if (['Tm02', 'Tm01', 'Tmax', 'T1/3'].includes(code)) {
                return value.toFixed(1);
            }

            // Direction (degrees)
            if (['WINDRTG', 'Th3', 'Th0', 'STROOMRTG'].includes(code)) {
                return Math.round(value) + '°';
            }

            // Default: 2 decimal places for most values
            return Number.isInteger(value) ? value : value.toFixed(2);
        },
        formatTime(isoString) {
            if (!isoString) return 'N/A';
            return new Date(isoString).toLocaleString();
        },
        formatLocationTooltip(measurement) {
            const loc = measurement.location;
            const code = measurement.grootheid.code;
            const comp = measurement.compartiment.code;
            return `[RWS] ${loc.name} (${loc.id})\n\n${code} | ${comp} | ${measurement.value} ${measurement.grootheid.unit || ''}`;
        },
    },
};
</script>

<style scoped>
.measurements-table-wrapper {
    overflow-x: auto;
    margin: 0 -1rem;
    padding: 0 1rem;
}

.measurements-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.measurements-table th,
.measurements-table td {
    padding: 0.5rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color, #e0e0e0);
}

.measurements-table th {
    font-weight: 600;
    color: var(--text-secondary, #666);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.measurements-table tbody tr:hover {
    background: var(--hover-bg, rgba(0, 0, 0, 0.02));
}

.code-cell code {
    background: var(--code-bg, #f5f5f5);
    padding: 0.125rem 0.375rem;
    border-radius: 3px;
    font-size: 0.8125rem;
    font-family: monospace;
}

.name-cell {
    min-width: 150px;
}

.name-cell .english {
    display: block;
    font-weight: 500;
}

.name-cell .dutch {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary, #666);
}

.value-cell {
    font-family: monospace;
    white-space: nowrap;
}

.value-cell .value {
    font-weight: 600;
}

.value-cell .unit {
    margin-left: 0.25rem;
    color: var(--text-secondary, #666);
    font-size: 0.8125rem;
}

.compartment-badge {
    display: inline-block;
    background: var(--badge-bg, #e8f4f8);
    color: var(--badge-color, #1976d2);
    padding: 0.125rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: help;
}

.loading-inline,
.error-inline,
.empty-inline {
    padding: 1rem;
    text-align: center;
    color: var(--text-secondary, #666);
}

.error-inline {
    color: var(--error-color, #d32f2f);
}
</style>
