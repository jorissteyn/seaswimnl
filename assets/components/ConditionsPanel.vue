<template>
    <div class="conditions-panel">
        <SwimMetrics
            v-if="conditions.metrics"
            :data="conditions.metrics"
        />

        <TidesCard
            v-if="conditions.tides && Object.keys(conditions.tides).length > 0"
            :data="conditions.tides"
            :water-height="conditions.water?.waterHeight"
            :measured-at="conditions.water?.measuredAt"
        />

        <WaterConditions
            v-if="conditions.water"
            :data="conditions.water"
        />
        <div v-else class="unavailable">
            Water conditions unavailable
        </div>

        <WeatherConditions
            v-if="conditions.weather"
            :data="conditions.weather"
        />
        <div v-else class="unavailable">
            Weather conditions unavailable
        </div>
    </div>
</template>

<script>
import SwimMetrics from './SwimMetrics.vue';
import TidesCard from './TidesCard.vue';
import WaterConditions from './WaterConditions.vue';
import WeatherConditions from './WeatherConditions.vue';

export default {
    name: 'ConditionsPanel',
    components: {
        SwimMetrics,
        TidesCard,
        WaterConditions,
        WeatherConditions,
    },
    props: {
        conditions: {
            type: Object,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
};
</script>
