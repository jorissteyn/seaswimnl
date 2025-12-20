<template>
    <div class="conditions-panel">
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
import WaterConditions from './WaterConditions.vue';
import WeatherConditions from './WeatherConditions.vue';

export default {
    name: 'ConditionsPanel',
    components: {
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
