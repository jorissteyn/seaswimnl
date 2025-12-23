<template>
    <div class="conditions-card water">
        <h2>Water Conditions</h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Temperature</dt>
                <dd>{{ formatTemp(data.temperature) }}</dd>
            </div>
            <div class="condition-item">
                <dt>Wave Height</dt>
                <dd>{{ formatMeters(data.waveHeight) }}</dd>
            </div>
            <div class="condition-item">
                <dt>Quality</dt>
                <dd :class="'quality-' + data.quality">{{ data.qualityLabel }}</dd>
            </div>
        </dl>
        <p class="timestamp">Last updated: {{ formatTime(data.measuredAt) }}</p>
    </div>
</template>

<script>
export default {
    name: 'WaterConditions',
    props: {
        data: {
            type: Object,
            required: true,
        },
    },
    methods: {
        formatTemp(value) {
            return value !== null ? `${value}°C` : 'N/A';
        },
        formatMeters(value) {
            return value !== null ? `${value}m` : 'N/A';
        },
        formatTime(isoString) {
            if (!isoString) return 'N/A';
            return new Date(isoString).toLocaleString();
        },
    },
};
</script>
