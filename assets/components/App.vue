<template>
    <div class="container">
        <header class="header">
            <h1>Seaswim</h1>
            <p>Water conditions for sea swimming in the Netherlands</p>
        </header>

        <main class="main">
            <LocationSelector
                :locations="locations"
                :selected="selectedLocation"
                :loading="loadingLocations"
                @select="selectLocation"
            />

            <div v-if="error" class="error">
                {{ error }}
                <button @click="retry">Retry</button>
            </div>

            <ConditionsPanel
                v-if="selectedLocation && conditions"
                :conditions="conditions"
                :loading="loadingConditions"
            />

            <div v-else-if="selectedLocation && loadingConditions" class="loading">
                Loading conditions...
            </div>

            <div v-else-if="!selectedLocation" class="prompt">
                Select a location to view conditions
            </div>
        </main>
    </div>
</template>

<script>
import LocationSelector from './LocationSelector.vue';
import ConditionsPanel from './ConditionsPanel.vue';

export default {
    name: 'App',
    components: {
        LocationSelector,
        ConditionsPanel,
    },
    data() {
        return {
            locations: [],
            selectedLocation: null,
            conditions: null,
            loadingLocations: true,
            loadingConditions: false,
            error: null,
        };
    },
    async mounted() {
        await this.fetchLocations();
    },
    methods: {
        async fetchLocations() {
            this.loadingLocations = true;
            this.error = null;
            try {
                const response = await fetch('/api/locations');
                if (!response.ok) throw new Error('Failed to fetch locations');
                this.locations = await response.json();
            } catch (e) {
                this.error = 'Failed to load locations. Please check your connection.';
            } finally {
                this.loadingLocations = false;
            }
        },
        async selectLocation(location) {
            this.selectedLocation = location;
            this.conditions = null;
            this.error = null;

            if (!location) return;

            this.loadingConditions = true;
            try {
                const response = await fetch(`/api/conditions/${location.id}`);
                if (!response.ok) throw new Error('Failed to fetch conditions');
                this.conditions = await response.json();
            } catch (e) {
                this.error = 'Failed to load conditions. Please try again.';
            } finally {
                this.loadingConditions = false;
            }
        },
        retry() {
            if (this.selectedLocation) {
                this.selectLocation(this.selectedLocation);
            } else {
                this.fetchLocations();
            }
        },
    },
};
</script>
