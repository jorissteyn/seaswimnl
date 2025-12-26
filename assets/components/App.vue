<template>
    <div class="container">
        <header class="header">
            <h1>Seaswim NL</h1>
            <p>Water conditions for sea swimming in the Netherlands</p>
        </header>

        <main class="main">
            <SwimmingSpotSelector
                :swimming-spots="swimmingSpots"
                :selected="selectedSpot"
                :loading="loadingSpots"
                @select="selectSpot"
            />

            <div v-if="error" class="error">
                {{ error }}
                <button @click="retry">Retry</button>
            </div>

            <ConditionsPanel
                v-if="selectedSpot && conditions"
                :conditions="conditions"
                :loading="loadingConditions"
                :swimming-spot-id="selectedSpot.id"
            />

            <div v-else-if="selectedSpot && loadingConditions" class="loading">
                Loading conditions...
            </div>

            <div v-else-if="!selectedSpot" class="prompt">
                Select a swimming spot to view conditions
            </div>
        </main>
    </div>
</template>

<script>
import SwimmingSpotSelector from './SwimmingSpotSelector.vue';
import ConditionsPanel from './ConditionsPanel.vue';

export default {
    name: 'App',
    components: {
        SwimmingSpotSelector,
        ConditionsPanel,
    },
    data() {
        return {
            swimmingSpots: [],
            selectedSpot: null,
            conditions: null,
            loadingSpots: true,
            loadingConditions: false,
            error: null,
        };
    },
    async mounted() {
        await this.fetchSwimmingSpots();
    },
    methods: {
        async fetchSwimmingSpots() {
            this.loadingSpots = true;
            this.error = null;
            try {
                const response = await fetch('/api/swimming-spots');
                if (!response.ok) throw new Error('Failed to fetch swimming spots');
                this.swimmingSpots = await response.json();
                this.restoreSavedSpot();
            } catch (e) {
                this.error = 'Failed to load swimming spots. Please check your connection.';
            } finally {
                this.loadingSpots = false;
            }
        },
        restoreSavedSpot() {
            const savedId = localStorage.getItem('seaswim:selectedSpotId');
            if (!savedId) return;

            const spot = this.swimmingSpots.find(s => s.id === savedId);
            if (spot) {
                this.selectSpot(spot);
            } else {
                localStorage.removeItem('seaswim:selectedSpotId');
            }
        },
        async selectSpot(spot) {
            this.selectedSpot = spot;
            this.conditions = null;
            this.error = null;

            if (!spot) {
                localStorage.removeItem('seaswim:selectedSpotId');
                return;
            }

            localStorage.setItem('seaswim:selectedSpotId', spot.id);

            this.loadingConditions = true;
            try {
                const response = await fetch(`/api/conditions/${spot.id}`);
                if (!response.ok) throw new Error('Failed to fetch conditions');
                this.conditions = await response.json();
            } catch (e) {
                this.error = 'Failed to load conditions. Please try again.';
            } finally {
                this.loadingConditions = false;
            }
        },
        retry() {
            if (this.selectedSpot) {
                this.selectSpot(this.selectedSpot);
            } else {
                this.fetchSwimmingSpots();
            }
        },
    },
};
</script>
